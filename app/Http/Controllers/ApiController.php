<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Platform;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ApiController extends Controller
{
    public function register(Request $r)
    {
        $v = $r->validate(['name' => 'required|string|max:100', 'email' => 'required|email|max:150|unique:users', 'password' => 'required|string|min:10|max:128', 'role' => ['required', Rule::in(['family', 'worker', 'agency'])]]);
        $types = match ($v['role']) {
            'worker' => ['ktp', 'photo'],
            'agency' => ['deed', 'nib', 'npwp', 'business_license'],
            default => [],
        };
        $rules = [];
        foreach ($types as $type) {
            $rules[$type] = 'required|file|mimes:'.($type === 'photo' ? 'jpg,jpeg,png' : 'pdf,jpg,jpeg,png').'|max:5120';
        }
        if ($types !== []) {
            $rules['supporting_document'] = 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120';
        }
        $r->validate($rules);
        $paths = [];
        try {
            $session = DB::transaction(function () use ($r, $v, $types, &$paths): array {
                $u = User::create(collect($v)->only(['name', 'email', 'password'])->all());
                $u->role = $v['role'];
                $u->save();
                foreach (array_merge($types, $types === [] ? [] : ['supporting_document']) as $type) {
                    if (! $r->hasFile($type)) {
                        continue;
                    }
                    $file = $r->file($type);
                    $path = $file->store('verification/'.$u->id, 'local');
                    if ($path === false) {
                        throw new \RuntimeException('Dokumen gagal disimpan. Silakan coba lagi.');
                    }
                    $paths[] = $path;
                    DB::table('verification_requests')->insert([
                        'user_id' => $u->id, 'document_path' => $path,
                        'document_type' => $type, 'original_name' => mb_substr(basename($file->getClientOriginalName()), 0, 255),
                        'mime_type' => $file->getMimeType(), 'file_size' => $file->getSize(),
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                }

                return $this->session($u->fresh());
            });
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($paths);
            throw $exception;
        }

        return response()->json($session, 201);
    }

    public function login(Request $r)
    {
        $v = $r->validate(['email' => 'required|email', 'password' => 'required|string']);
        $u = User::where('email', $v['email'])->first();
        abort_unless($u && Hash::check($v['password'], $u->password), 422, 'Email atau kata sandi salah.');

        return $this->session($u);
    }

    private function session(User $u): array
    {
        $token = Str::random(64);
        DB::table('api_tokens')->insert(['user_id' => $u->id, 'hash' => hash('sha256', $token), 'expires_at' => now()->addDays(7), 'created_at' => now(), 'updated_at' => now()]);

        return ['token' => $token, 'user' => $u];
    }

    public function logout(Request $r)
    {
        DB::table('api_tokens')->where('hash', hash('sha256', $r->bearerToken()))->delete();

        return ['message' => 'Berhasil keluar.'];
    }

    public function workers(Request $r)
    {
        $q = DB::table('workers')->where('verification', 'verified')->where('available', true)
            ->where(function ($q) {
                $q->whereNull('agency_id')->orWhereIn('agency_id', DB::table('agencies')->select('id')->where('verification', 'verified'));
            });
        foreach (['category', 'rate_unit', 'arrangement'] as $field) {
            if ($r->filled($field)) {
                $q->where($field, $r->input($field));
            }
        }
        if ($r->filled('q')) {
            $q->where(function ($q) use ($r) {
                $q->where('name', 'like', '%'.$r->string('q').'%')->orWhere('city', 'like', '%'.$r->string('q').'%');
            });
        }
        $data = $q->orderBy('id')->paginate(20);
        $data->setCollection($data->getCollection()->map(fn ($w) => Platform::worker($w)));

        return $data;
    }

    public function worker(int $id)
    {
        $w = DB::table('workers')->where('verification', 'verified')->find($id);
        abort_unless($w, 404);
        if ($w->agency_id) {
            abort_unless(DB::table('agencies')->where('id', $w->agency_id)->where('verification', 'verified')->exists(), 404);
        }
        $data = Platform::worker($w);
        $data['reviews'] = DB::table('reviews')->where('target_id', $w->user_id)->select('rating', 'comment', 'created_at')->latest()->limit(20)->get();

        return $data;
    }

    public function profile(Request $r)
    {
        abort_unless($r->user()->role === 'worker', 403);
        $v = $r->validate(['name' => 'required|string|max:100', 'category' => ['required', Rule::in(['art', 'babysitter'])], 'city' => 'required|string|max:100', 'bio' => 'required|string|max:2000', 'skills' => 'required|array|max:20', 'skills.*' => 'string|max:80', 'certifications' => 'nullable|array|max:20', 'certifications.*' => 'string|max:120', 'experience_years' => 'required|integer|min:0|max:60', 'arrangement' => ['required', Rule::in(['live_in', 'live_out'])], 'rate_unit' => ['required', Rule::in(['hourly', 'daily', 'monthly'])], 'rate' => 'required|integer|min:10000|max:100000000', 'available' => 'required|boolean']);
        $v['skills'] = json_encode($v['skills']);
        $v['certifications'] = json_encode($v['certifications'] ?? []);
        $v['verification'] = 'pending';
        $v['updated_at'] = now();
        DB::table('workers')->updateOrInsert(['user_id' => $r->user()->id], $v + ['created_at' => now()]);
        Platform::audit($r->user()->id, 'worker.profile_updated', 'user:'.$r->user()->id);

        return ['message' => 'Profil disimpan dan menunggu verifikasi admin.'];
    }

    public function agency(Request $r)
    {
        abort_unless($r->user()->role === 'agency', 403);
        $v = $r->validate(['name' => 'required|string|max:120', 'city' => 'required|string|max:100', 'legal_number' => 'required|string|max:150']);
        DB::table('agencies')->updateOrInsert(['user_id' => $r->user()->id], $v + ['verification' => 'pending', 'created_at' => now(), 'updated_at' => now()]);

        return ['message' => 'Agency diajukan untuk verifikasi legalitas.'];
    }

    public function verification(Request $r)
    {
        $r->validate(['document' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120']);
        $path = $r->file('document')->store('verification', 'local');
        DB::table('verification_requests')->insert(['user_id' => $r->user()->id, 'document_path' => $path, 'created_at' => now(), 'updated_at' => now()]);

        return response()->json(['message' => 'Dokumen privat diterima untuk ditinjau.'], 201);
    }

    public function bookings(Request $r)
    {
        $u = $r->user();

        return DB::table('bookings')->where(function ($q) use ($u) {
            $q->where('family_id', $u->id)->orWhereIn('worker_id', DB::table('workers')->select('id')->where('user_id', $u->id))
                ->orWhereIn('agency_id', DB::table('agencies')->select('id')->where('user_id', $u->id));
        })->latest()->paginate(30);
    }

    public function book(Request $r)
    {
        abort_unless($r->user()->role === 'family', 403);
        abort_unless($r->user()->verification === 'verified', 403, 'Verifikasi identitas keluarga diperlukan sebelum booking.');
        $v = $r->validate(['worker_id' => 'required|integer|exists:workers,id', 'starts_at' => 'required|date|after:now', 'units' => 'required|integer|min:1|max:365', 'address' => 'required|string|max:250', 'scope' => 'required|string|min:10|max:3000']);

        return DB::transaction(function () use ($r, $v) {
            $w = DB::table('workers')->lockForUpdate()->find($v['worker_id']);
            abort_unless($w->verification === 'verified' && $w->available, 422, 'Pekerja belum tersedia atau belum terverifikasi.');
            if ($w->agency_id) {
                abort_unless(DB::table('agencies')->where('id', $w->agency_id)->where('verification', 'verified')->exists(), 422, 'Agency belum terverifikasi.');
            }
            $start = Carbon::parse($v['starts_at']);
            $end = $start->copy();
            match ($w->rate_unit) {
                'hourly' => $end->addHours($v['units']),'monthly' => $end->addMonthsNoOverflow($v['units']),default => $end->addDays($v['units'])
            };
            abort_if(DB::table('bookings')->where('worker_id', $w->id)->whereNotIn('status', ['cancelled', 'completed'])->where('starts_at', '<', $end)->where('ends_at', '>', $start)->exists(), 422, 'Jadwal pekerja sudah dipesan.');
            $pay = $w->rate * $v['units'];
            $fee = 15000;
            $data = ['family_id' => $r->user()->id, 'worker_id' => $w->id, 'agency_id' => $w->agency_id, 'starts_at' => $start, 'ends_at' => $end, 'units' => $v['units'], 'address' => $v['address'], 'scope' => $v['scope'], 'rate_unit' => $w->rate_unit, 'worker_pay' => $pay, 'agency_fee' => $w->agency_fee, 'platform_fee' => $fee, 'total' => $pay + $w->agency_fee + $fee, 'contract' => 'Penawaran kerja: '.$v['scope'].'. Tarif pekerja Rp '.$pay.'; fee agency Rp '.$w->agency_fee.'; fee platform Rp '.$fee.'. Pekerja menyetujui penawaran dengan menerima booking. Perubahan lingkup harus disepakati melalui chat.', 'created_at' => now(), 'updated_at' => now()];
            $id = DB::table('bookings')->insertGetId($data);
            Platform::audit($r->user()->id, 'booking.requested', 'booking:'.$id);

            return response()->json(DB::table('bookings')->find($id), 201);
        });
    }

    private function accessible(Request $r, int $id)
    {
        $b = DB::table('bookings')->find($id);
        abort_unless($b, 404);
        abort_unless(Platform::participant($b, $r->user()), 403);

        return $b;
    }

    public function booking(Request $r, int $id)
    {
        $b = $this->accessible($r, $id);
        $worker = DB::table('workers')->find($b->worker_id);
        $ids = [$b->family_id, $worker->user_id];
        if ($b->agency_id) {
            $ids[] = DB::table('agencies')->where('id', $b->agency_id)->value('user_id');
        }
        $b->participants = DB::table('users')->whereIn('id', $ids)->get(['id', 'name', 'role']);
        $b->payment_provider = config('services.midtrans.enabled') ? 'midtrans' : 'manual';

        return $b;
    }

    public function transition(Request $r, int $id)
    {
        $v = $r->validate(['status' => ['required', Rule::in(['accepted', 'in_progress', 'completed', 'cancelled'])]]);

        return DB::transaction(function () use ($r, $id, $v) {
            DB::table('bookings')->where('id', $id)->lockForUpdate()->first();
            $b = $this->accessible($r, $id);
            $next = $v['status'];
            $worker = DB::table('workers')->find($b->worker_id);
            $ok = match ($next) {
                'accepted' => $b->status === 'requested' && $r->user()->id === $worker->user_id,
                'in_progress' => $b->status === 'accepted' && $b->payment_status === 'paid' && $r->user()->id === $worker->user_id,
                'completed' => $b->status === 'in_progress' && $r->user()->id === $b->family_id,
                'cancelled' => $b->status === 'requested', default => false
            };
            abort_unless($ok, 422, 'Perubahan status tidak diizinkan.');
            DB::table('bookings')->where('id', $id)->update(['status' => $next, 'updated_at' => now()]);
            Platform::audit($r->user()->id, 'booking.'.$next, 'booking:'.$id);

            return DB::table('bookings')->find($id);
        });
    }

    public function messages(Request $r, int $id)
    {
        $this->accessible($r, $id);

        return DB::table('messages')->join('users', 'users.id', '=', 'messages.user_id')->where('booking_id', $id)->select('messages.*', 'users.name')->orderBy('messages.id')->paginate(100);
    }

    public function message(Request $r, int $id)
    {
        $this->accessible($r, $id);
        $v = $r->validate(['body' => 'required|string|max:4000']);
        $mid = DB::table('messages')->insertGetId(['booking_id' => $id, 'user_id' => $r->user()->id, 'body' => $v['body'], 'created_at' => now(), 'updated_at' => now()]);

        return response()->json(DB::table('messages')->find($mid), 201);
    }

    public function review(Request $r, int $id)
    {
        $b = $this->accessible($r, $id);
        abort_unless($b->status === 'completed', 422, 'Ulasan hanya untuk pekerjaan selesai.');
        $v = $r->validate(['target_id' => 'required|integer|exists:users,id', 'rating' => 'required|integer|min:1|max:5', 'comment' => 'required|string|max:2000']);
        abort_if((int) $v['target_id'] === $r->user()->id, 422);
        abort_unless(Platform::participant($b, User::findOrFail($v['target_id'])), 422);
        $key = ['booking_id' => $id, 'author_id' => $r->user()->id, 'target_id' => $v['target_id']];
        abort_if(DB::table('reviews')->where($key)->exists(), 422, 'Ulasan sudah diberikan.');
        DB::table('reviews')->insert($key + ['rating' => $v['rating'], 'comment' => $v['comment'], 'created_at' => now(), 'updated_at' => now()]);

        return response()->json(['message' => 'Ulasan tersimpan.'], 201);
    }

    public function report(Request $r, int $id)
    {
        $this->accessible($r, $id);
        $v = $r->validate(['category' => ['required', Rule::in(['violence', 'theft', 'harassment', 'fraud', 'identity', 'other'])], 'description' => 'required|string|min:20|max:5000', 'evidence' => 'nullable|string|max:5000']);
        $rid = DB::table('safety_reports')->insertGetId($v + ['booking_id' => $id, 'reporter_id' => $r->user()->id, 'created_at' => now(), 'updated_at' => now()]);
        Platform::audit($r->user()->id, 'safety.reported', 'report:'.$rid);

        return response()->json(['id' => $rid, 'message' => 'Laporan privat diterima. Tim akan meninjau bukti.'], 201);
    }

    public function reports(Request $r)
    {
        return DB::table('safety_reports')->where('reporter_id', $r->user()->id)->latest()->paginate(30);
    }

    public function appeal(Request $r, int $id)
    {
        $v = $r->validate(['appeal' => 'required|string|min:20|max:5000']);
        $report = DB::table('safety_reports')->find($id);
        abort_unless($report, 404);
        abort_unless($report->reporter_id === $r->user()->id, 403);
        abort_unless($report->status === 'decided', 422);
        DB::table('safety_reports')->where('id', $id)->update($v + ['status' => 'appealed', 'updated_at' => now()]);
        Platform::audit($r->user()->id, 'safety.appealed', 'report:'.$id);

        return ['message' => 'Banding diajukan.'];
    }
}
