<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class JourneyTest extends TestCase
{
    use RefreshDatabase;

    private int $firstWorkerId;

    private int $secondWorkerId;

    private int $agencyId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->firstWorkerId = DB::table('workers')->where('user_id', User::where('email', 'worker1@asista.test')->value('id'))->value('id');
        $this->secondWorkerId = DB::table('workers')->where('user_id', User::where('email', 'worker2@asista.test')->value('id'))->value('id');
        $this->agencyId = DB::table('agencies')->where('user_id', User::where('email', 'agency@asista.test')->value('id'))->value('id');
    }

    private function auth(string $name): array
    {
        return ['Authorization' => 'Bearer '.$this->postJson('/api/v1/login', ['email' => $name.'@asista.test', 'password' => 'DemoAsista123!'])->assertOk()->json('token')];
    }

    public function test_shortlist_and_matching_belong_to_family(): void
    {
        $family = $this->auth('family');
        $worker = $this->auth('worker1');
        $this->putJson('/api/v1/shortlist/'.$this->firstWorkerId, [], $family)->assertOk();
        $this->putJson('/api/v1/shortlist/'.$this->firstWorkerId, [], $family)->assertOk();
        $this->assertDatabaseCount('shortlists', 1);
        $this->getJson('/api/v1/shortlist', $worker)->assertExactJson([]);
        $id = $this->postJson('/api/v1/needs', ['title' => 'Babysitter harian', 'category' => 'babysitter', 'city' => 'Jakarta Selatan', 'starts_at' => now()->addDays(3)->toDateTimeString(), 'budget' => 200000, 'scope' => 'Menjaga anak dan menyiapkan aktivitas bermain.'], $family)->assertCreated()->json('id');
        $this->getJson("/api/v1/needs/$id/matches", $family)->assertOk()->assertJsonCount(2);
        $this->getJson("/api/v1/needs/$id/matches", $worker)->assertNotFound();
        DB::table('agencies')->where('id', $this->agencyId)->update(['verification' => 'rejected']);
        $this->getJson('/api/v1/shortlist', $family)->assertExactJson([]);
        $this->getJson("/api/v1/needs/$id/matches", $family)->assertExactJson([]);
    }

    public function test_agency_cannot_claim_worker_without_consent_and_release_preserves_profile(): void
    {
        $agency = $this->auth('agency');
        $worker = $this->auth('worker2');
        $this->postJson('/api/v1/agency-invitations', ['email' => 'worker2@asista.test', 'note' => 'Dukungan administrasi dan pencarian pekerjaan.'], $agency)->assertOk();
        $id = DB::table('agency_relationships')->value('id');
        $this->patchJson("/api/v1/agency-relationships/$id", ['status' => 'accepted'], $agency)->assertForbidden();
        $this->patchJson("/api/v1/agency-relationships/$id", ['status' => 'accepted'], $worker)->assertOk();
        $this->assertDatabaseHas('workers', ['id' => $this->secondWorkerId, 'agency_id' => $this->agencyId]);
        $this->postJson('/api/v1/agency-transfer', ['note' => 'Saya ingin melanjutkan sebagai pekerja mandiri.'], $worker)->assertOk();
        $release = DB::table('agency_relationships')->where('type', 'release')->value('id');
        $this->patchJson("/api/v1/agency-relationships/$release", ['status' => 'accepted'], $agency)->assertOk();
        $this->assertDatabaseHas('workers', ['id' => $this->secondWorkerId, 'agency_id' => null, 'name' => 'Rina Wulandari']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'relationship.release.accepted', 'subject' => 'worker:'.$this->secondWorkerId]);
    }

    public function test_interview_needs_other_participant_approval(): void
    {
        $family = $this->auth('family');
        $worker = $this->auth('worker1');
        $bid = $this->postJson('/api/v1/bookings', ['worker_id' => $this->firstWorkerId, 'starts_at' => now()->addDays(5)->toDateTimeString(), 'units' => 1, 'address' => 'Alamat keluarga Jakarta', 'scope' => 'Menjaga anak sesuai kesepakatan keluarga.'], $family)->assertCreated()->json('id');
        $id = $this->postJson("/api/v1/bookings/$bid/interviews", ['scheduled_at' => now()->addDays(2)->toDateTimeString(), 'method' => 'chat'], $family)->assertCreated()->json('id');
        $this->patchJson("/api/v1/interviews/$id", ['status' => 'accepted'], $family)->assertUnprocessable();
        $this->patchJson("/api/v1/interviews/$id", ['status' => 'accepted'], $worker)->assertOk();
    }
}
