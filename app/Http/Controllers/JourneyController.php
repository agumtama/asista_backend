<?php

namespace App\Http\Controllers;

use App\Services\Platform;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class JourneyController extends Controller
{
    public function shortlist(Request $r)
    {
        return $this->verifiedWorkers()->whereIn('id', DB::table('shortlists')->select('worker_id')->where('user_id', $r->user()->id))->get()->map(fn ($w) => Platform::worker($w));
    }

    public function saveWorker(Request $r, int $id)
    {
        abort_unless($r->user()->role === 'family', 403);
        abort_unless(DB::table('workers')->where('id', $id)->where('verification', 'verified')->exists(), 404);
        DB::table('shortlists')->updateOrInsert(['user_id' => $r->user()->id, 'worker_id' => $id], ['created_at' => now(), 'updated_at' => now()]);

        return ['message' => 'Pendamping disimpan.'];
    }

    public function removeWorker(Request $r, int $id)
    {
        DB::table('shortlists')->where('user_id', $r->user()->id)->where('worker_id', $id)->delete();

        return ['message' => 'Pendamping dihapus dari daftar tersimpan.'];
    }

    public function needs(Request $r)
    {
        return DB::table('job_needs')->where('user_id', $r->user()->id)->latest()->get();
    }

    public function need(Request $r)
    {
        abort_unless($r->user()->role === 'family', 403);
        $v = $r->validate(['title' => 'required|string|max:120', 'category' => ['required', Rule::in(['art', 'babysitter'])], 'city' => 'required|string|max:100', 'starts_at' => 'required|date|after:now', 'budget' => 'required|integer|min:10000|max:100000000', 'scope' => 'required|string|min:10|max:3000']);
        $id = DB::table('job_needs')->insertGetId($v + ['user_id' => $r->user()->id, 'created_at' => now(), 'updated_at' => now()]);

        return response()->json(['id' => $id, 'message' => 'Kebutuhan tersimpan. Lihat kandidat yang cocok.'], 201);
    }

    public function matches(Request $r, int $id)
    {
        $need = DB::table('job_needs')->where('user_id', $r->user()->id)->find($id);
        abort_unless($need, 404);

        return $this->verifiedWorkers()->where('category', $need->category)->where('city', 'like', '%'.$need->city.'%')->where('rate', '<=', $need->budget)->where('available', true)->get()->map(fn ($w) => Platform::worker($w));
    }

    private function verifiedWorkers()
    {
        return DB::table('workers')->where('verification', 'verified')->where(function ($query) {
            $query->whereNull('agency_id')->orWhereIn('agency_id', DB::table('agencies')->select('id')->where('verification', 'verified'));
        });
    }

    private function booking(Request $r, int $id)
    {
        $b = DB::table('bookings')->find($id);
        abort_unless($b, 404);
        abort_unless(Platform::participant($b, $r->user()), 403);

        return $b;
    }

    public function interviews(Request $r, int $id)
    {
        $this->booking($r, $id);

        return DB::table('interviews')->where('booking_id', $id)->latest()->get();
    }

    public function interview(Request $r, int $id)
    {
        $b = $this->booking($r, $id);
        abort_unless(in_array($b->status, ['requested', 'accepted']), 422);
        $v = $r->validate(['scheduled_at' => 'required|date|after:now', 'method' => ['required', Rule::in(['chat', 'external_video', 'in_person'])]]);
        $iid = DB::table('interviews')->insertGetId($v + ['booking_id' => $id, 'proposed_by' => $r->user()->id, 'created_at' => now(), 'updated_at' => now()]);
        Platform::audit($r->user()->id, 'interview.proposed', 'interview:'.$iid);

        return response()->json(['id' => $iid, 'message' => 'Jadwal interview diajukan.'], 201);
    }

    public function interviewStatus(Request $r, int $id)
    {
        $v = $r->validate(['status' => ['required', Rule::in(['accepted', 'cancelled'])]]);
        $i = DB::table('interviews')->find($id);
        abort_unless($i, 404);
        $this->booking($r, $i->booking_id);
        abort_unless($i->status === 'proposed', 422);
        if ($v['status'] === 'accepted') {
            abort_if($i->proposed_by === $r->user()->id, 422, 'Jadwal harus disetujui pihak lain.');
        }
        DB::table('interviews')->where('id', $id)->update($v + ['updated_at' => now()]);
        Platform::audit($r->user()->id, 'interview.'.$v['status'], 'interview:'.$id);

        return ['message' => 'Status interview diperbarui.'];
    }

    public function ownProfile(Request $r)
    {
        $table = $r->user()->role === 'agency' ? 'agencies' : 'workers';
        $p = DB::table($table)->where('user_id', $r->user()->id)->first();
        if ($p && $table === 'workers') {
            $p->skills = json_decode($p->skills, true);
            $p->certifications = json_decode($p->certifications ?? '[]', true);
        }

        return response()->json($p);
    }

    public function agencyWorkspace(Request $r)
    {
        $u = $r->user();
        abort_unless(in_array($u->role, ['agency', 'worker']), 403);
        $agency = $u->role === 'agency' ? DB::table('agencies')->where('user_id', $u->id)->first() : null;
        $worker = $u->role === 'worker' ? DB::table('workers')->where('user_id', $u->id)->first() : null;
        $requests = DB::table('agency_relationships')->join('workers', 'workers.id', '=', 'agency_relationships.worker_id')->join('agencies', 'agencies.id', '=', 'agency_relationships.agency_id')->select('agency_relationships.*', 'workers.name as worker_name', 'agencies.name as agency_name');
        if ($agency) {
            $requests->where('agency_relationships.agency_id', $agency->id);
        } elseif ($worker) {
            $requests->where('worker_id', $worker->id);
        } else {
            $requests->whereRaw('1=0');
        }

        return ['agency' => $agency, 'worker' => $worker, 'workers' => $agency ? DB::table('workers')->where('agency_id', $agency->id)->get()->map(fn ($w) => Platform::worker($w)) : [], 'requests' => $requests->orderByDesc('agency_relationships.id')->get()];
    }

    public function invite(Request $r)
    {
        abort_unless($r->user()->role === 'agency', 403);
        $v = $r->validate(['email' => 'required|email', 'note' => 'required|string|min:10|max:1000']);
        $agency = DB::table('agencies')->where('user_id', $r->user()->id)->where('verification', 'verified')->first();
        abort_unless($agency, 422, 'Agency harus terverifikasi.');
        $worker = DB::table('workers')->whereIn('user_id', DB::table('users')->select('id')->where('email', $v['email']))->whereNull('agency_id')->first();
        abort_unless($worker, 422, 'Pekerja mandiri dengan email tersebut tidak tersedia.');
        abort_if(DB::table('agency_relationships')->where('worker_id', $worker->id)->where('status', 'pending')->exists(), 422, 'Masih ada permintaan yang diproses.');
        DB::table('agency_relationships')->insert(['worker_id' => $worker->id, 'agency_id' => $agency->id, 'requested_by' => $r->user()->id, 'type' => 'join', 'note' => $v['note'], 'created_at' => now(), 'updated_at' => now()]);

        return ['message' => 'Undangan dikirim; pekerja perlu menyetujui.'];
    }

    public function transfer(Request $r)
    {
        abort_unless($r->user()->role === 'worker', 403);
        $v = $r->validate(['note' => 'required|string|min:10|max:1000']);
        $w = DB::table('workers')->where('user_id', $r->user()->id)->first();
        abort_unless($w && $w->agency_id, 422);
        abort_if(DB::table('agency_relationships')->where('worker_id', $w->id)->where('status', 'pending')->exists(), 422, 'Masih ada permintaan yang diproses.');
        DB::table('agency_relationships')->insert(['worker_id' => $w->id, 'agency_id' => $w->agency_id, 'requested_by' => $r->user()->id, 'type' => 'release', 'note' => $v['note'], 'created_at' => now(), 'updated_at' => now()]);

        return ['message' => 'Permintaan menjadi mandiri dikirim ke agency. Riwayat pekerjaan tetap melekat.'];
    }

    public function relationship(Request $r, int $id)
    {
        $v = $r->validate(['status' => ['required', Rule::in(['accepted', 'rejected'])]]);

        return DB::transaction(function () use ($r, $id, $v) {
            $row = DB::table('agency_relationships')->where('id', $id)->lockForUpdate()->first();
            abort_unless($row, 404);
            abort_unless($row->status === 'pending', 422);
            $w = DB::table('workers')->where('id', $row->worker_id)->lockForUpdate()->first();
            $a = DB::table('agencies')->find($row->agency_id);
            abort_unless($r->user()->id === ($row->type === 'join' ? $w->user_id : $a->user_id), 403);
            if ($v['status'] === 'accepted') {
                if ($row->type === 'join') {
                    abort_unless(! $w->agency_id && $a->verification === 'verified', 422);
                } else {
                    abort_unless($w->agency_id === $a->id, 422);
                    abort_if(DB::table('bookings')->where('worker_id', $w->id)->whereNotIn('status', ['completed', 'cancelled'])->exists(), 422, 'Selesaikan booking aktif sebelum perpindahan.');
                }
                DB::table('workers')->where('id', $w->id)->update(['agency_id' => $row->type === 'join' ? $a->id : null, 'agency_fee' => 0, 'updated_at' => now()]);
            }
            DB::table('agency_relationships')->where('id', $id)->update($v + ['updated_at' => now()]);
            Platform::audit($r->user()->id, 'relationship.'.$row->type.'.'.$v['status'], 'worker:'.$w->id);

            return ['message' => 'Hubungan kerja diperbarui.'];
        });
    }

    public function agencyFee(Request $r, int $id)
    {
        abort_unless($r->user()->role === 'agency', 403);
        $v = $r->validate(['agency_fee' => 'required|integer|min:0|max:100000000']);
        $w = DB::table('workers')->find($id);
        abort_unless($w, 404);
        abort_unless(DB::table('agencies')->where('id',$w->agency_id)->where('user_id',$r->user()->id)->where('verification','verified')->exists(), 403);
        DB::table('workers')->where('id',$id)->update($v + ['updated_at' => now()]);
        Platform::audit($r->user()->id,'agency.fee_updated','worker:'.$id,$v);

        return ['message' => 'Fee agency diperbarui untuk booking berikutnya.'];
    }
}
