<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class AgencyRegistrationController extends Controller
{
    public const DOCUMENTS = ['nib' => 'NIB (Nomor Induk Berusaha)', 'deed' => 'Akta pendirian perusahaan', 'amendment' => 'Akta perubahan terakhir (opsional)', 'business_license' => 'SK Kemenkumham / pengesahan badan usaha', 'npwp' => 'Kartu NPWP badan usaha', 'domicile' => 'SKDU / perjanjian sewa / bukti kepemilikan', 'bank_account' => 'Rekening bank perusahaan', 'manager_identity' => 'KTP / paspor pengelola'];

    public function create(): View
    {
        return view('agency.register', ['documents' => self::DOCUMENTS]);
    }

    public function store(Request $request): RedirectResponse
    {
        $rules = [
            'company_name' => 'required|string|max:120', 'business_type' => 'required|in:PT,CV,Koperasi,Yayasan,Lainnya',
            'kbli' => 'required|string|max:150', 'company_npwp' => 'required|string|max:32',
            'city' => 'required|string|max:100', 'address' => 'required|string|max:500',
            'company_phone' => 'required|string|regex:/^\+?[0-9]{9,15}$/', 'company_email' => 'required|email|max:150',
            'website' => 'nullable|url:http,https|max:250', 'social_media' => 'nullable|string|max:250',
            'name' => 'required|string|max:100', 'position' => 'required|string|max:100',
            'identity_number' => 'required|string|max:32', 'manager_npwp' => 'required|string|max:32',
            'phone' => 'required|string|regex:/^\+?[0-9]{9,15}$/', 'email' => 'required|email|max:150|unique:users,email',
            'manager_address' => 'nullable|string|max:500', 'password' => 'required|string|min:10|max:128|confirmed',
            'legal_number' => 'required|string|max:150', 'consent' => 'accepted',
        ];
        foreach (self::DOCUMENTS as $type => $label) {
            $rules[$type] = ($type === 'amendment' ? 'nullable' : 'required').'|file|mimes:pdf,jpg,jpeg,png|max:5120';
        }
        $data = $request->validate($rules);
        $paths = [];
        try {
            $user = DB::transaction(function () use ($request, $data, &$paths): User {
                $user = User::create(collect($data)->only(['name', 'email', 'password'])->all());
                $user->role = 'agency';
                $user->verification = 'pending';
                $user->registration_data = collect($data)->except(array_merge(array_keys(self::DOCUMENTS), ['password', 'password_confirmation', 'consent']))->all();
                $user->save();
                DB::table('agencies')->insert(['user_id' => $user->id, 'name' => $data['company_name'], 'city' => $data['city'], 'legal_number' => $data['legal_number'], 'verification' => 'pending', 'subscription' => 'inactive', 'created_at' => now(), 'updated_at' => now()]);
                foreach (self::DOCUMENTS as $type => $label) {
                    if (! $request->hasFile($type)) {
                        continue;
                    }
                    $file = $request->file($type);
                    $path = $file->store('verification/'.$user->id, 'local');
                    if ($path === false) {
                        throw new \RuntimeException('Dokumen gagal disimpan.');
                    }
                    $paths[] = $path;
                    DB::table('verification_requests')->insert(['user_id' => $user->id, 'document_path' => $path, 'document_type' => $type, 'original_name' => mb_substr(basename($file->getClientOriginalName()), 0, 255), 'mime_type' => $file->getMimeType(), 'file_size' => $file->getSize(), 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()]);
                }

                return $user;
            });
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($paths);
            throw $exception;
        }
        $request->session()->regenerate();
        $request->session()->put('agency_registration_user', $user->id);

        return $this->sendCode($request);
    }

    public function verification(Request $request): View|RedirectResponse
    {
        $user = User::find($request->session()->get('agency_registration_user'));
        if (! $user || $user->role !== 'agency') {
            return redirect()->route('agency.register');
        }

        return view('agency.verify', compact('user'));
    }

    public function sendCode(Request $request): RedirectResponse
    {
        $user = User::findOrFail($request->session()->get('agency_registration_user'));
        abort_unless($user->role === 'agency', 403);
        if ($user->email_verified_at) {
            return redirect()->route('agency.verify');
        }
        if ($request->session()->get('agency_code_sent', 0) > time() - 60) {
            return redirect()->route('agency.verify')->withErrors(['code' => 'Tunggu 60 detik sebelum meminta kode baru.']);
        }
        $request->session()->forget(['agency_code_hash', 'agency_code_expires']);
        $code = (string) random_int(100000, 999999);
        try {
            Mail::raw('Kode verifikasi email ASISTA Anda: '.$code.'. Berlaku 10 menit. Jangan bagikan kode ini.', function ($message) use ($user): void {
                $message->to($user->email)->subject('Verifikasi email agency ASISTA');
            });
        } catch (\Throwable $exception) {
            report($exception);

            return redirect()->route('agency.verify')->withErrors(['code' => 'Pendaftaran tersimpan, tetapi email belum berhasil dikirim. Silakan coba kirim ulang.']);
        }
        $request->session()->put(['agency_code_hash' => Hash::make($code), 'agency_code_expires' => time() + 600, 'agency_code_sent' => time(), 'agency_code_attempts' => 0]);

        return redirect()->route('agency.verify')->with('success', 'Kode verifikasi telah dikirim. Periksa email pengelola, termasuk folder spam.');
    }

    public function verify(Request $request): RedirectResponse
    {
        $request->validate(['code' => 'required|digits:6']);
        $user = User::findOrFail($request->session()->get('agency_registration_user'));
        abort_unless($user->role === 'agency', 403);
        $attempts = (int) $request->session()->get('agency_code_attempts', 0);
        $request->session()->put('agency_code_attempts', $attempts + 1);
        if ($attempts >= 5 || $request->session()->get('agency_code_expires', 0) < time() || ! Hash::check($request->string('code')->toString(), $request->session()->get('agency_code_hash', ''))) {
            return back()->withErrors(['code' => 'Kode salah, kedaluwarsa, atau batas percobaan tercapai. Minta kode baru jika diperlukan.']);
        }
        $user->email_verified_at = now();
        $user->save();
        $request->session()->forget(['agency_code_hash', 'agency_code_expires']);

        return redirect()->route('agency.verify');
    }
}
