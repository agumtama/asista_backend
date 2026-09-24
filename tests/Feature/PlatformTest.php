<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PlatformTest extends TestCase
{
    use RefreshDatabase;

    private int $workerId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->workerId = DB::table('workers')->where('user_id', User::where('email', 'worker1@asista.test')->value('id'))->value('id');
    }

    private function headers(string $email): array
    {
        $token = $this->postJson('/api/v1/login', ['email' => $email, 'password' => 'DemoAsista123!'])->assertOk()->json('token');

        return ['Authorization' => 'Bearer '.$token];
    }

    private function payload(): array
    {
        return ['worker_id' => $this->workerId, 'starts_at' => now()->addDays(2)->toDateTimeString(), 'units' => 2, 'address' => 'Jl. Melati 10, Jakarta', 'scope' => 'Menjaga anak dan menyiapkan makan siang.', 'total' => 1];
    }

    public function test_registration_cannot_create_admin_and_password_is_hidden(): void
    {
        $this->postJson('/api/v1/register', ['name' => 'Intruder', 'email' => 'i@test.id', 'password' => 'Secret12345', 'role' => 'admin'])->assertUnprocessable();
        $this->postJson('/api/v1/register', ['name' => 'New Family', 'email' => 'new@test.id', 'password' => 'Secret12345', 'role' => 'family'])->assertCreated()->assertJsonPath('user.verification', 'pending')->assertJsonMissingPath('user.password');
    }

    public function test_unverified_families_and_guests_cannot_book(): void
    {
        $this->postJson('/api/v1/bookings', $this->payload())->assertUnauthorized();
        User::where('role', 'family')->update(['verification' => 'pending']);
        $this->postJson('/api/v1/bookings', $this->payload(), $this->headers('family@asista.test'))->assertForbidden();
    }

    public function test_booking_lifecycle_prices_and_reviews(): void
    {
        $family = $this->headers('family@asista.test');
        $worker = $this->headers('worker1@asista.test');
        $b = $this->postJson('/api/v1/bookings', $this->payload(), $family)->assertCreated()->assertJsonPath('total', 400000)->json();
        $id = $b['id'];
        $this->postJson('/api/v1/bookings', $this->payload(), $family)->assertUnprocessable();
        $this->patchJson("/api/v1/bookings/$id/status", ['status' => 'accepted'], $family)->assertUnprocessable();
        $this->patchJson("/api/v1/bookings/$id/status", ['status' => 'accepted'], $worker)->assertOk();
        $this->patchJson("/api/v1/bookings/$id/status", ['status' => 'in_progress'], $worker)->assertUnprocessable();
        $review = ['target_id' => User::where('email', 'worker1@asista.test')->value('id'), 'rating' => 5, 'comment' => 'Komunikasi baik dan pekerjaan sesuai kesepakatan.'];
        $this->postJson("/api/v1/bookings/$id/reviews", $review, $family)->assertUnprocessable();
        $this->actingAs(User::where('role', 'admin')->first())->post("/admin/bookings/$id/payment", ['reference' => 'BANK-VERIFIED-123'])->assertRedirect();
        $this->patchJson("/api/v1/bookings/$id/status", ['status' => 'in_progress'], $worker)->assertOk();
        $this->patchJson("/api/v1/bookings/$id/status", ['status' => 'completed'], $worker)->assertUnprocessable();
        $this->patchJson("/api/v1/bookings/$id/status", ['status' => 'completed'], $family)->assertOk();
        $this->postJson("/api/v1/bookings/$id/reviews", $review, $family)->assertCreated();
        $this->postJson("/api/v1/bookings/$id/reviews", $review, $family)->assertUnprocessable();
        $this->getJson('/api/v1/workers/'.$this->workerId)->assertJsonPath('completed_jobs', 1)->assertJsonPath('rating', 5);
        $this->assertDatabaseHas('audit_logs', ['action' => 'payment.manually_confirmed']);
    }

    public function test_private_data_cannot_be_accessed_by_other_worker(): void
    {
        $family = $this->headers('family@asista.test');
        $other = $this->headers('worker2@asista.test');
        $id = $this->postJson('/api/v1/bookings', $this->payload(), $family)->assertCreated()->json('id');
        $this->getJson("/api/v1/bookings/$id", $other)->assertForbidden();
        $this->getJson("/api/v1/bookings/$id/messages", $other)->assertForbidden();
        $this->postJson("/api/v1/bookings/$id/messages", ['body' => 'Private message'], $family)->assertCreated();
        $this->postJson("/api/v1/bookings/$id/reports", ['category' => 'other', 'description' => 'Laporan kejadian untuk investigasi privat.'], $other)->assertForbidden();
        $this->getJson('/api/v1/workers')->assertJsonMissingPath('data.0.user_id');
    }

    public function test_safety_investigation_decision_appeal_is_audited(): void
    {
        $family = $this->headers('family@asista.test');
        $bid = $this->postJson('/api/v1/bookings', $this->payload(), $family)->assertCreated()->json('id');
        $rid = $this->postJson("/api/v1/bookings/$bid/reports", ['category' => 'other', 'description' => 'Mohon investigasi kejadian sesuai bukti yang tersedia.'], $family)->assertCreated()->json('id');
        $admin = User::where('role', 'admin')->first();
        $this->actingAs($admin)->post("/admin/reports/$rid", ['status' => 'decided', 'decision' => 'Belum boleh langsung mengambil keputusan.'])->assertUnprocessable();
        $this->actingAs($admin)->post("/admin/reports/$rid", ['status' => 'investigating', 'decision' => 'Tim meninjau bukti dan meminta penjelasan para pihak.'])->assertRedirect();
        $this->actingAs($admin)->post("/admin/reports/$rid", ['status' => 'decided', 'decision' => 'Keputusan dicatat setelah investigasi para pihak.'])->assertRedirect();
        $this->postJson("/api/v1/reports/$rid/appeal", ['appeal' => 'Saya mengajukan bukti tambahan untuk ditinjau kembali.'], $family)->assertOk();
        $this->assertDatabaseHas('safety_reports', ['id' => $rid, 'status' => 'appealed']);
    }

    public function test_cms_requires_admin_and_all_sections_render(): void
    {
        $this->get('/admin')->assertRedirect('/login');
        $this->actingAs(User::where('role', 'family')->first())->get('/admin')->assertForbidden();
        foreach (['overview', 'workers', 'users', 'agencies', 'bookings', 'safety_reports', 'verification_requests', 'audit_logs'] as $section) {
            $this->actingAs(User::where('role', 'admin')->first())->get('/admin?section='.$section)->assertOk();
        }
    }

    public function test_logout_revokes_api_token(): void
    {
        $h = $this->headers('family@asista.test');
        $this->postJson('/api/v1/logout', [], $h)->assertOk();
        $this->getJson('/api/v1/me', $h)->assertUnauthorized();
    }
}
