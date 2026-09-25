<?php

namespace App\Http\Controllers;

use App\Services\Platform;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class AdminController extends Controller
{
    public function login(Request $r)
    {
        $v = $r->validate(['email' => 'required|email', 'password' => 'required|string']);
        if (! Auth::attempt($v + ['role' => ['admin', 'agency']])) {
            return back()->withErrors(['email' => 'Kredensial admin tidak valid.']);
        }
        $r->session()->regenerate();

        return redirect($r->user()->role === 'agency' ? '/agency' : '/admin');
    }

    public function logout(Request $r)
    {
        Auth::logout();
        $r->session()->invalidate();
        $r->session()->regenerateToken();

        return redirect('/login');
    }

    public function index(Request $r)
    {
        $section = $r->query('section', 'overview');
        abort_unless(in_array($section, ['overview', 'workers', 'agencies', 'users', 'bookings', 'verification_requests', 'safety_reports', 'audit_logs']), 404);
        if ($section === 'verification_requests') {
            $filters = $r->validate(['q' => 'nullable|string|max:100']);
            $users = DB::table('users')->whereExists(function ($query) {
                $query->selectRaw('1')->from('verification_requests')->whereColumn('verification_requests.user_id', 'users.id');
            });
            if (! empty($filters['q'])) {
                $users->where(function ($query) use ($filters) {
                    $query->where('name', 'like', '%'.$filters['q'].'%')->orWhere('email', 'like', '%'.$filters['q'].'%');
                });
            }
            $users = $users->orderByDesc('id')->paginate(15)->withQueryString();
            $documents = DB::table('verification_requests')->whereIn('user_id', $users->pluck('id'))->orderByDesc('id')->get()->groupBy('user_id');

            return view('admin.verification', compact('section', 'users', 'documents', 'filters'));
        }
        $stats = [];
        foreach (['workers', 'agencies', 'bookings', 'safety_reports'] as $t) {
            $stats[$t] = DB::table($t)->count();
        }
        $query = DB::table($section === 'overview' ? 'bookings' : $section);
        $workerFilters = [];
        $filterAgencies = collect();
        $filterCities = collect();
        $agencyFilters = [];
        $agencySummary = [];
        $agencyCities = collect();
        $agencySubscriptions = collect();
        if ($section === 'agencies') {
            $agencyFilters = $r->validate([
                'q' => 'nullable|string|max:100', 'city' => 'nullable|string|max:100',
                'verification' => ['nullable', Rule::in(['pending', 'verified', 'rejected'])],
                'subscription' => 'nullable|string|max:100',
                'registered_from' => 'nullable|date', 'registered_to' => 'nullable|date|after_or_equal:registered_from',
                'tab' => ['nullable', Rule::in(['list', 'pending', 'subscription'])],
                'export' => ['nullable', Rule::in(['csv'])],
            ]);
            $agencyCities = DB::table('agencies')->distinct()->orderBy('city')->pluck('city');
            $agencySubscriptions = DB::table('agencies')->distinct()->orderBy('subscription')->pluck('subscription');
            $agencySummary = ['total' => DB::table('agencies')->count(), 'new' => DB::table('agencies')->where('created_at', '>=', now()->startOfMonth())->count()];
            foreach (['verified', 'pending', 'rejected'] as $status) {
                $agencySummary[$status] = DB::table('agencies')->where('verification', $status)->count();
            }
            $agencySummary['active'] = DB::table('agencies')->where('subscription', 'active')->count();
            $query->select('agencies.*')
                ->selectSub(DB::table('workers')->selectRaw('COUNT(*)')->whereColumn('agency_id', 'agencies.id'), 'worker_count')
                ->selectSub(DB::table('bookings')->selectRaw('COUNT(*)')->whereColumn('agency_id', 'agencies.id')->where('is_demo', false), 'booking_count');
            if ($r->filled('q')) {
                $query->where(function ($q) use ($agencyFilters) {
                    $term = '%'.$agencyFilters['q'].'%';
                    $q->where('name', 'like', $term)->orWhere('city', 'like', $term)->orWhere('legal_number', 'like', $term);
                });
            }
            foreach (['city', 'verification', 'subscription'] as $field) {
                if ($r->filled($field)) {
                    $query->where($field, $agencyFilters[$field]);
                }
            }
            if (($agencyFilters['tab'] ?? '') === 'pending') {
                $query->where('verification', 'pending');
            }
            if (($agencyFilters['tab'] ?? '') === 'subscription') {
                $query->where('subscription', 'active');
            }
            if ($r->filled('registered_from')) {
                $query->whereDate('created_at', '>=', $agencyFilters['registered_from']);
            }
            if ($r->filled('registered_to')) {
                $query->whereDate('created_at', '<=', $agencyFilters['registered_to']);
            }
            if (($agencyFilters['export'] ?? '') === 'csv') {
                return response()->streamDownload(function () use ($query): void {
                    $stream = fopen('php://output', 'w');
                    fputcsv($stream, ['ID', 'Nama agency', 'Kota', 'Pekerja', 'Booking aktual', 'Subscription', 'Status'], ',', '"', '');
                    foreach ($query->orderBy('id')->cursor() as $agency) {
                        $cells = [$agency->id, $agency->name, $agency->city, $agency->worker_count, $agency->booking_count, $agency->subscription, $agency->verification];
                        $cells = array_map(fn ($value) => preg_match('/^[\s]*[=+@\-]/u', (string) $value) ? "'".$value : $value, $cells);
                        fputcsv($stream, $cells, ',', '"', '');
                    }
                    fclose($stream);
                }, 'asista-agency.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store']);
            }
        }
        if ($section === 'workers') {
            $workerFilters = $r->validate([
                'q' => 'nullable|string|max:100',
                'affiliation' => ['nullable', Rule::in(['agency', 'independent'])],
                'agency_id' => 'nullable|integer|exists:agencies,id',
                'category' => ['nullable', Rule::in(['art', 'babysitter'])],
                'verification' => ['nullable', Rule::in(['pending', 'verified', 'rejected'])],
                'available' => ['nullable', Rule::in(['0', '1'])],
                'city' => 'nullable|string|max:100',
                'rate_units' => 'nullable|array|max:3',
                'rate_units.*' => ['string', Rule::in(['hourly', 'daily', 'monthly'])],
                'arrangements' => 'nullable|array|max:2',
                'arrangements.*' => ['string', Rule::in(['live_in', 'live_out'])],
                'sort' => ['nullable', Rule::in(['newest', 'oldest', 'name'])],
            ]);
            $filterAgencies = DB::table('agencies')->orderBy('name')->get(['id', 'name']);
            $filterCities = DB::table('workers')->distinct()->orderBy('city')->pluck('city');
            $query->leftJoin('agencies', 'agencies.id', '=', 'workers.agency_id')->select('workers.*', 'agencies.name as agency_name');
            $query->selectSub(DB::table('verification_requests')->select('id')->whereColumn('user_id', 'workers.user_id')->where('document_type', 'photo')->latest('id')->limit(1), 'photo_id');
            if ($r->filled('q')) {
                $query->where(function ($q) use ($workerFilters) {
                    $term = '%'.$workerFilters['q'].'%';
                    $q->where('workers.name', 'like', $term)->orWhere('workers.city', 'like', $term)->orWhere('workers.category', 'like', $term)->orWhere('workers.skills', 'like', $term);
                });
            }
            if (($workerFilters['affiliation'] ?? '') === 'independent') {
                $query->whereNull('workers.agency_id');
            } elseif (($workerFilters['affiliation'] ?? '') === 'agency') {
                $query->whereNotNull('workers.agency_id');
            }
            foreach (['agency_id', 'category', 'verification', 'available', 'city'] as $field) {
                if ($r->filled($field)) {
                    $query->where('workers.'.$field, $workerFilters[$field]);
                }
            }
            foreach (['rate_units' => 'rate_unit', 'arrangements' => 'arrangement'] as $filter => $column) {
                if (! empty($workerFilters[$filter])) {
                    $query->whereIn('workers.'.$column, $workerFilters[$filter]);
                }
            }
            match ($workerFilters['sort'] ?? 'newest') {
                'name' => $query->orderBy('workers.name')->orderBy('workers.id'),
                'oldest' => $query->orderBy('workers.id'),
                default => $query->orderByDesc('workers.id'),
            };
        }
        if (in_array($section, ['overview', 'bookings'])) {
            if ($section === 'bookings' && $r->filled('agency_id')) {
                $r->validate(['agency_id' => 'integer|exists:agencies,id']);
                $query->where('bookings.agency_id', $r->integer('agency_id'));
            }
            $query->join('workers', 'workers.id', '=', 'bookings.worker_id')
                ->join('users', 'users.id', '=', 'bookings.family_id')
                ->leftJoin('agencies', 'agencies.id', '=', 'bookings.agency_id')
                ->select('bookings.*', 'workers.name as worker_name', 'users.name as family_name', 'agencies.name as agency_name')
                ->orderByDesc('bookings.id');
        } elseif ($section !== 'workers') {
            $query->latest();
        }
        $rows = $query->paginate(20)->withQueryString();
        $agencyWorkers = $section === 'agencies' ? DB::table('workers')->whereIn('agency_id', $rows->pluck('id'))->get()->groupBy('agency_id') : collect();
        $revenue = [];
        if (in_array($section, ['overview', 'bookings'])) {
            foreach ([0 => 'Transaksi aktual', 1 => 'Simulasi booking demo'] as $demo => $label) {
                $paid = DB::table('bookings')->where('is_demo', $demo)->where('payment_status', 'paid')->whereIn('status', ['accepted', 'in_progress', 'completed']);
                $revenue[] = ['label' => $label, 'count' => (clone $paid)->count(), 'total' => (clone $paid)->sum('total'),
                    'worker' => (clone $paid)->sum('worker_pay'), 'agency' => (clone $paid)->sum('agency_fee'), 'platform' => (clone $paid)->sum('platform_fee'),
                    'pending' => DB::table('bookings')->where('is_demo', $demo)->where('payment_status', 'unpaid')->whereIn('status', ['requested', 'accepted'])->sum('platform_fee')];
            }
        }

        $registrations = collect();
        if (in_array($section, ['workers', 'agencies'])) {
            $registrations = DB::table('users')->where('role', $section === 'workers' ? 'worker' : 'agency')
                ->whereNotIn('id', DB::table($section)->select('user_id'))
                ->latest()->paginate(10, ['*'], 'registrations_page')->withQueryString();
        }

        $payments = in_array($section, ['overview', 'bookings'])
            ? DB::table('payments')->whereIn('booking_id', $rows->pluck('id'))->orderByDesc('id')->get(['booking_id', 'order_id', 'status', 'payment_type', 'amount', 'created_at'])->groupBy('booking_id')
            : collect();

        if ($section === 'agencies') {
            return view('admin.agencies', compact('rows', 'registrations', 'agencyWorkers', 'agencyFilters', 'agencySummary', 'agencyCities', 'agencySubscriptions'));
        }

        return view('admin.dashboard', compact('section', 'stats', 'rows', 'registrations', 'payments', 'agencyWorkers', 'revenue', 'workerFilters', 'filterAgencies', 'filterCities'));
    }

    public function verify(Request $r, string $table, int $id)
    {
        abort_unless(in_array($table, ['users', 'workers', 'agencies', 'verification_requests']), 404);
        $v = $r->validate(['status' => ['required', Rule::in(['verified', 'rejected', 'pending'])], 'note' => 'required|string|min:5|max:1000']);
        DB::transaction(function () use ($r, $table, $id, $v) {
            $row = DB::table($table)->find($id);
            abort_unless($row, 404);
            if ($table === 'agencies' && $v['status'] === 'verified') {
                abort_unless(Platform::agencyDocumentStatus($row->user_id) === 'verified', 422, 'Semua dokumen terbaru agency harus terverifikasi terlebih dahulu.');
            }
            DB::table($table)->where('id', $id)->update([($table === 'verification_requests' ? 'status' : 'verification') => $v['status'], 'updated_at' => now()]);
            if ($table === 'verification_requests') {
                DB::table($table)->where('id', $id)->update(['note' => $v['note']]);
                $statuses = DB::table('verification_requests')->where('user_id', $row->user_id)->pluck('status');
                $status = $statuses->contains('rejected') ? 'rejected' : ($statuses->every(fn ($status) => $status === 'verified') ? 'verified' : 'pending');
                DB::table('users')->where('id', $row->user_id)->update(['verification' => $status, 'updated_at' => now()]);
                if (DB::table('users')->where('id', $row->user_id)->value('role') === 'agency') {
                    $status = Platform::agencyDocumentStatus($row->user_id);
                    DB::table('agencies')->where('user_id', $row->user_id)->update(['verification' => $status, 'updated_at' => now()]);
                    DB::table('users')->where('id', $row->user_id)->update(['verification' => $status, 'updated_at' => now()]);
                }
            }
            Platform::audit($r->user()->id, 'verification.'.$v['status'], $table.':'.$id, ['note' => $v['note']]);
        });

        return back()->with('success', 'Status verifikasi diperbarui.');
    }

    public function document(Request $r, int $id)
    {
        $row = DB::table('verification_requests')->find($id);
        abort_unless($row, 404);
        Platform::audit($r->user()->id, 'verification.document_accessed', 'verification:'.$id);

        abort_unless(Storage::disk('local')->exists($row->document_path), 404);
        $mime = Storage::disk('local')->mimeType($row->document_path);
        abort_unless(in_array($mime, ['application/pdf', 'image/jpeg', 'image/png']), 415);

        return Storage::disk('local')->response($row->document_path, 'dokumen-'.$id.'.'.match ($mime) {
            'application/pdf' => 'pdf', 'image/jpeg' => 'jpg', default => 'png',
        }, ['Content-Type' => $mime, 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store'], $r->boolean('download') ? 'attachment' : 'inline');
    }

    public function registrant(int $id): View
    {
        $user = DB::table('users')->find($id);
        abort_unless($user, 404);
        $table = match ($user->role) {
            'worker' => 'workers', 'agency' => 'agencies', default => null
        };
        $profile = $table ? DB::table($table)->where('user_id', $id)->first() : null;
        $documents = DB::table('verification_requests')->where('user_id', $id)->latest()->get();
        if ($user->role === 'worker') {
            $registration = json_decode($user->registration_data ?? '{}', true) ?? [];
            $skills = $profile ? json_decode($profile->skills, true) ?? [] : ($registration['skills'] ?? []);
            $certifications = $profile ? json_decode($profile->certifications ?? '[]', true) ?? [] : [];
            $photo = $documents->firstWhere('document_type', 'photo');
            $agency = $profile?->agency_id ? DB::table('agencies')->find($profile->agency_id) : null;
            $reviewsQuery = DB::table('reviews')->join('bookings', 'bookings.id', '=', 'reviews.booking_id')->where('reviews.target_id', $id)->where('bookings.is_demo', false);
            $rating = (clone $reviewsQuery)->avg('rating');
            $reviewCount = (clone $reviewsQuery)->count();
            $reviews = $reviewsQuery->select('reviews.*')->orderByDesc('reviews.id')->paginate(10, ['*'], 'reviews_page')->withQueryString();
            $history = DB::table('bookings')->where('worker_id', $profile?->id ?? 0)->where('is_demo', false)->where('status', 'completed')->orderByDesc('ends_at')->paginate(10, ['*'], 'history_page')->withQueryString();
            $schedule = DB::table('bookings')->where('worker_id', $profile?->id ?? 0)->where('is_demo', false)->whereIn('status', ['accepted', 'in_progress'])->where('ends_at', '>=', now())->orderBy('starts_at')->paginate(10, ['*'], 'schedule_page')->withQueryString();

            return view('admin.worker-cv', compact('user', 'profile', 'documents', 'registration', 'skills', 'certifications', 'photo', 'agency', 'rating', 'reviewCount', 'reviews', 'history', 'schedule'));
        }

        $agencyWorkers = $table === 'agencies' && $profile ? DB::table('workers')->where('agency_id', $profile->id)->get() : collect();

        return view('admin.registrant', compact('user', 'profile', 'documents', 'table', 'agencyWorkers'));
    }

    public function payment(Request $r, int $id)
    {
        $v = $r->validate(['reference' => 'required|string|min:5|max:150']);
        DB::transaction(function () use ($r, $id, $v) {
            $b = DB::table('bookings')->where('id', $id)->lockForUpdate()->first();
            abort_unless($b, 404);
            abort_if(config('services.midtrans.enabled') || DB::table('payments')->where('booking_id', $id)->exists(), 422, 'Pembayaran Midtrans harus dikonfirmasi melalui status gateway.');
            abort_unless($b->status === 'accepted' && $b->payment_status === 'unpaid', 422);
            DB::table('bookings')->where('id', $id)->update(['payment_status' => 'paid', 'updated_at' => now()]);
            Platform::audit($r->user()->id, 'payment.manually_confirmed', 'booking:'.$id, $v);
        });

        return back()->with('success', 'Pembayaran manual tercatat.');
    }

    public function safety(Request $r, int $id)
    {
        $v = $r->validate(['status' => ['required', Rule::in(['investigating', 'decided'])], 'decision' => 'required|string|min:20|max:5000']);
        DB::transaction(function () use ($r, $id, $v) {
            $report = DB::table('safety_reports')->where('id', $id)->lockForUpdate()->first();
            abort_unless($report, 404);
            $allowed = ['reported' => ['investigating'], 'investigating' => ['decided'], 'appealed' => ['investigating']];
            abort_unless(in_array($v['status'], $allowed[$report->status] ?? []), 422);
            DB::table('safety_reports')->where('id', $id)->update($v + ['updated_at' => now()]);
            Platform::audit($r->user()->id, 'safety.'.$v['status'], 'report:'.$id, ['decision' => $v['decision']]);
        });

        return back()->with('success', 'Penanganan laporan diperbarui.');
    }
}
