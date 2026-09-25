<?php

namespace App\Http\Controllers;

use App\Services\Platform;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class AgencyPortalController extends Controller
{
    private function agency(Request $request): object
    {
        $agency = DB::table('agencies')->where('user_id', $request->user()->id)->first();
        abort_unless($agency, 403, 'Lengkapi profil agency melalui aplikasi ASISTA terlebih dahulu.');

        return $agency;
    }

    public function index(Request $request): View
    {
        $agency = $this->agency($request);
        $filters = $request->validate(['tab' => 'nullable|in:profile,manager,documents,rates,workers', 'q' => 'nullable|string|max:100', 'category' => 'nullable|in:art,babysitter', 'verification' => 'nullable|in:pending,verified,rejected', 'city' => 'nullable|string|max:100']);
        $tab = $filters['tab'] ?? 'profile';
        $details = $request->user()->registration_data ?? [];
        $documents = DB::table('verification_requests')->where('user_id', $request->user()->id)->latest('id')->get();
        $rateFilters = $request->validate(['rate_category' => 'nullable|in:art,babysitter', 'rate_unit' => 'nullable|in:hourly,daily,monthly', 'rate_arrangement' => 'nullable|in:live_in,live_out']);
        $ratesQuery = DB::table('agency_rates')->where('agency_id', $agency->id);
        foreach (['rate_category' => 'category', 'rate_unit' => 'rate_unit', 'rate_arrangement' => 'arrangement'] as $filter => $column) {
            if (! empty($rateFilters[$filter])) {
                $ratesQuery->where($column, $rateFilters[$filter]);
            }
        }
        $rates = $ratesQuery->orderBy('category')->orderBy('rate_unit')->get();
        $workers = DB::table('workers')->where('agency_id', $agency->id);
        $cities = (clone $workers)->distinct()->orderBy('city')->pluck('city');
        foreach (['category', 'verification', 'city'] as $field) {
            if (! empty($filters[$field])) {
                $workers->where($field, $filters[$field]);
            }
        }
        $workers->select('workers.*')->selectSub(DB::table('verification_requests')->select('id')->whereColumn('user_id', 'workers.user_id')->where('document_type', 'photo')->latest('id')->limit(1), 'photo_id');
        if (! empty($filters['q'])) {
            $workers->where(function ($query) use ($filters): void {
                $query->where('name', 'like', '%'.$filters['q'].'%')->orWhere('city', 'like', '%'.$filters['q'].'%');
            });
        }
        $workers = $workers->orderBy('name')->paginate(15)->withQueryString();

        return view('agency.portal', compact('agency', 'tab', 'details', 'documents', 'rates', 'workers', 'cities'));
    }

    public function profile(Request $request): RedirectResponse
    {
        $agency = $this->agency($request);
        $data = $request->validate(['company_name' => 'required|string|max:120', 'city' => 'required|string|max:100', 'address' => 'required|string|max:500', 'company_phone' => 'required|string|max:30', 'company_email' => 'required|email|max:150', 'website' => 'nullable|url:http,https|max:250', 'social_media' => 'nullable|string|max:250', 'description' => 'nullable|string|max:2000']);
        DB::transaction(function () use ($request, $data, $agency): void {
            $request->user()->registration_data = array_merge($request->user()->registration_data ?? [], $data);
            $request->user()->save();
            DB::table('agencies')->where('id', $agency->id)->update(['name' => $data['company_name'], 'city' => $data['city'], 'verification' => 'pending', 'updated_at' => now()]);
            Platform::audit($request->user()->id, 'agency.profile_updated', 'agency:'.$agency->id);
        });

        return back()->with('success', 'Profil disimpan dan diajukan kembali untuk verifikasi.');
    }

    public function manager(Request $request): RedirectResponse
    {
        $this->agency($request);
        $data = $request->validate(['name' => 'required|string|max:100', 'position' => 'required|string|max:100', 'phone' => 'required|string|max:30', 'manager_address' => 'nullable|string|max:500']);
        $user = $request->user();
        $user->name = $data['name'];
        $user->registration_data = array_merge($user->registration_data ?? [], $data);
        $user->save();
        Platform::audit($user->id, 'agency.manager_updated', 'user:'.$user->id);

        return back()->with('success', 'Data pengelola disimpan.');
    }

    public function uploadDocument(Request $request): RedirectResponse
    {
        $agency = $this->agency($request);
        $data = $request->validate(['document_type' => ['required', Rule::in(array_keys(AgencyRegistrationController::DOCUMENTS))], 'document' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120']);
        $file = $request->file('document');
        $path = $file->store('verification/'.$request->user()->id, 'local');
        abort_unless($path, 500);
        try {
            DB::transaction(function () use ($request, $agency, $data, $file, $path): void {
                DB::table('verification_requests')->insert(['user_id' => $request->user()->id, 'document_type' => $data['document_type'], 'document_path' => $path, 'original_name' => mb_substr(basename($file->getClientOriginalName()), 0, 255), 'mime_type' => $file->getMimeType(), 'file_size' => $file->getSize(), 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()]);
                DB::table('agencies')->where('id', $agency->id)->update(['verification' => 'pending', 'updated_at' => now()]);
                DB::table('users')->where('id', $request->user()->id)->update(['verification' => 'pending']);
                Platform::audit($request->user()->id, 'agency.document_uploaded', 'agency:'.$agency->id);
            });
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }

        return back()->with('success', 'Dokumen baru menunggu verifikasi. Riwayat dokumen lama tetap tersimpan.');
    }

    public function document(Request $request, int $id): Response
    {
        $this->agency($request);
        $document = DB::table('verification_requests')->where('user_id', $request->user()->id)->find($id);
        abort_unless($document, 404);

        return app(AdminController::class)->document($request, $id);
    }

    public function rate(Request $request): RedirectResponse
    {
        $agency = $this->agency($request);
        $data = $request->validate(['category' => 'required|in:art,babysitter', 'rate_unit' => 'required|in:hourly,daily,monthly', 'arrangement' => 'required|in:live_in,live_out', 'rate' => 'required|integer|min:10000|max:100000000', 'agency_fee' => 'required|integer|min:0|max:100000000']);
        DB::table('agency_rates')->updateOrInsert(['agency_id' => $agency->id] + collect($data)->only(['category', 'rate_unit', 'arrangement'])->all(), $data + ['updated_at' => now(), 'created_at' => now()]);
        Platform::audit($request->user()->id, 'agency.rate_saved', 'agency:'.$agency->id, $data);

        return back()->with('success', 'Tarif disimpan. Klik Terapkan untuk memperbarui pekerja yang sesuai.');
    }

    public function previewRate(Request $request, int $id): JsonResponse
    {
        $agency = $this->agency($request);
        abort_unless($agency->verification === 'verified', 403);
        $rate = DB::table('agency_rates')->where('agency_id', $agency->id)->find($id);
        abort_unless($rate, 404);
        $workers = DB::table('workers')->where('agency_id', $agency->id)->where('category', $rate->category)->where('rate_unit', $rate->rate_unit)->where('arrangement', $rate->arrangement)->orderBy('name')->get(['id', 'name', 'rate', 'agency_fee']);

        return response()->json(['rate' => $rate, 'workers' => $workers])->header('Cache-Control', 'private, no-store');
    }

    public function applyRate(Request $request, int $id): RedirectResponse
    {
        $agency = $this->agency($request);
        abort_unless($agency->verification === 'verified', 403, 'Agency harus terverifikasi sebelum menerapkan tarif.');
        $rate = DB::table('agency_rates')->where('agency_id', $agency->id)->find($id);
        abort_unless($rate, 404);
        $request->validate(['worker_ids' => 'sometimes|required|array|min:1', 'worker_ids.*' => 'integer']);
        $query = DB::table('workers')->where('agency_id', $agency->id)->where('category', $rate->category)->where('rate_unit', $rate->rate_unit)->where('arrangement', $rate->arrangement);
        if ($request->has('worker_ids')) {
            $query->whereIn('id', $request->input('worker_ids'));
        }
        $count = $query->update(['rate' => $rate->rate, 'agency_fee' => $rate->agency_fee, 'updated_at' => now()]);
        Platform::audit($request->user()->id, 'agency.rate_applied', 'agency:'.$agency->id, ['rate_id' => $id, 'workers' => $count]);

        return back()->with('success', 'Tarif diterapkan ke '.$count.' pekerja yang sesuai. Booking lama tetap menggunakan nominal sebelumnya.');
    }

    private function ownedWorker(Request $request, int $id): object
    {
        $worker = DB::table('workers')->where('agency_id', $this->agency($request)->id)->find($id);
        abort_unless($worker, 404);

        return $worker;
    }

    public function worker(Request $request, int $id): View
    {
        $worker = $this->ownedWorker($request, $id);
        $hasPhoto = DB::table('verification_requests')->where('user_id', $worker->user_id)->where('document_type', 'photo')->exists();
        $history = DB::table('bookings')->where('worker_id', $worker->id)->where('agency_id', $worker->agency_id)->where('is_demo', false)->where('status', 'completed')->latest('ends_at')->paginate(10)->withQueryString();

        $workerUser = DB::table('users')->find($worker->user_id);
        $registration = json_decode($workerUser->registration_data ?? '{}', true) ?? [];
        $reviews = DB::table('reviews')->join('bookings', 'bookings.id', '=', 'reviews.booking_id')->where('reviews.target_id', $worker->user_id)->where('bookings.agency_id', $worker->agency_id)->where('bookings.is_demo', false);
        $rating = (clone $reviews)->avg('reviews.rating');
        $reviewCount = $reviews->count();
        $documentStatuses = DB::table('verification_requests')->where('user_id', $worker->user_id)->latest('id')->get(['document_type', 'status'])->unique('document_type');

        return view('agency.worker', compact('worker', 'history', 'hasPhoto', 'workerUser', 'registration', 'rating', 'reviewCount', 'documentStatuses'));
    }

    public function photo(Request $request, int $id): Response
    {
        $worker = $this->ownedWorker($request, $id);
        $photo = DB::table('verification_requests')->where('user_id', $worker->user_id)->where('document_type', 'photo')->latest('id')->first();
        abort_unless($photo, 404);

        return app(AdminController::class)->document($request, $photo->id);
    }

    public function uploadVideo(Request $request, int $id): RedirectResponse
    {
        $worker = $this->ownedWorker($request, $id);
        $request->validate(['video' => 'required|file|mimes:mp4,webm|max:20480'], [
            'video.uploaded' => 'Video gagal diterima oleh server. Batas upload PHP aktif: '.ini_get('upload_max_filesize').' per file, '.ini_get('post_max_size').' per request. Periksa juga ruang penyimpanan dan folder sementara upload pada server.',
            'video.max' => 'Ukuran video maksimal 20 MB.',
            'video.mimes' => 'Gunakan video berformat MP4 atau WebM.',
        ]);
        $path = $request->file('video')->store('worker-videos/'.$id, 'local');
        abort_unless($path, 500);
        try {
            DB::table('workers')->where('id', $worker->id)->where('agency_id', $worker->agency_id)->update(['video_path' => $path, 'updated_at' => now()]);
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }
        if ($worker->video_path) {
            Storage::disk('local')->delete($worker->video_path);
        }
        Platform::audit($request->user()->id, 'agency.worker_video_uploaded', 'worker:'.$id);

        return back()->with('success', 'Video pekerja berhasil disimpan.');
    }

    public function video(Request $request, int $id): Response
    {
        $worker = $this->ownedWorker($request, $id);
        abort_unless($worker->video_path && Storage::disk('local')->exists($worker->video_path), 404);
        $mime = Storage::disk('local')->mimeType($worker->video_path);
        abort_unless(in_array($mime, ['video/mp4', 'video/webm']), 415);
        Platform::audit($request->user()->id, 'agency.worker_video_accessed', 'worker:'.$id);

        return response()->file(Storage::disk('local')->path($worker->video_path), ['Content-Type' => $mime, 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
