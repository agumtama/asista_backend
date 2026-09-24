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
        if (! Auth::attempt($v + ['role' => 'admin'])) {
            return back()->withErrors(['email' => 'Kredensial admin tidak valid.']);
        }
        $r->session()->regenerate();

        return redirect('/admin');
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
        $stats = [];
        foreach (['workers', 'agencies', 'bookings', 'safety_reports'] as $t) {
            $stats[$t] = DB::table($t)->count();
        }
        $rows = DB::table($section === 'overview' ? 'bookings' : $section)->latest()->paginate(20)->withQueryString();

        $registrations = collect();
        if (in_array($section, ['workers', 'agencies'])) {
            $registrations = DB::table('users')->where('role', $section === 'workers' ? 'worker' : 'agency')
                ->whereNotIn('id', DB::table($section)->select('user_id'))
                ->latest()->paginate(10, ['*'], 'registrations_page')->withQueryString();
        }

        $payments = in_array($section, ['overview', 'bookings'])
            ? DB::table('payments')->whereIn('booking_id', $rows->pluck('id'))->orderByDesc('id')->get(['booking_id', 'order_id', 'status', 'payment_type', 'amount', 'created_at'])->groupBy('booking_id')
            : collect();

        return view('admin.dashboard', compact('section', 'stats', 'rows', 'registrations', 'payments'));
    }

    public function verify(Request $r, string $table, int $id)
    {
        abort_unless(in_array($table, ['users', 'workers', 'agencies', 'verification_requests']), 404);
        $v = $r->validate(['status' => ['required', Rule::in(['verified', 'rejected', 'pending'])], 'note' => 'required|string|min:5|max:1000']);
        DB::transaction(function () use ($r, $table, $id, $v) {
            $row = DB::table($table)->find($id);
            abort_unless($row, 404);
            DB::table($table)->where('id', $id)->update([($table === 'verification_requests' ? 'status' : 'verification') => $v['status'], 'updated_at' => now()]);
            if ($table === 'verification_requests') {
                DB::table($table)->where('id', $id)->update(['note' => $v['note']]);
                $statuses = DB::table('verification_requests')->where('user_id', $row->user_id)->pluck('status');
                $status = $statuses->contains('rejected') ? 'rejected' : ($statuses->every(fn ($status) => $status === 'verified') ? 'verified' : 'pending');
                DB::table('users')->where('id', $row->user_id)->update(['verification' => $status, 'updated_at' => now()]);
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

        return view('admin.registrant', compact('user', 'profile', 'documents', 'table'));
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
