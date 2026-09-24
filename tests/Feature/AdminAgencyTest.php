<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DemoBookingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminAgencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_agency_summary_filters_counts_and_export(): void
    {
        $this->seed();
        $this->seed(DemoBookingSeeder::class);
        $agency = DB::table('agencies')->first();
        $user = User::factory()->create();
        DB::table('agencies')->insert(['user_id' => $user->id, 'name' => '=Agency Baru', 'city' => 'Bandung', 'legal_number' => 'LEGAL-TEST', 'verification' => 'pending', 'subscription' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $this->get('/admin?section=agencies&export=csv')->assertRedirect('/login');
        $this->actingAs(User::where('role', 'admin')->first());
        $this->get('/admin?section=agencies')->assertOk()->assertSee('Agency Management')
            ->assertViewHas('agencySummary', fn ($s) => $s['total'] === 2 && $s['verified'] === 1 && $s['pending'] === 1 && $s['active'] === 1)
            ->assertViewHas('rows', fn ($rows) => (int) $rows->firstWhere('id', $agency->id)->worker_count === 2 && (int) $rows->firstWhere('id', $agency->id)->booking_count === 0);
        $this->get('/admin?section=agencies&tab=pending&city=Bandung&subscription=active&q=Baru')->assertOk()->assertViewHas('rows', fn ($rows) => $rows->total() === 1);
        $this->get('/admin?section=agencies&registered_from=2099-01-01')->assertOk()->assertViewHas('rows', fn ($rows) => $rows->total() === 0);
        $this->getJson('/admin?section=agencies&registered_from=2026-09-24&registered_to=2026-09-01')->assertUnprocessable();
        $response = $this->get('/admin?section=agencies&q=Baru&export=csv')->assertOk()->assertDownload('asista-agency.csv');
        $csv = $response->streamedContent();
        $this->assertStringContainsString("'=Agency Baru", $csv);
        $this->assertStringNotContainsString('Mitra Keluarga', $csv);
        $this->get('/admin?section=bookings&agency_id='.$agency->id)->assertOk()->assertViewHas('rows', fn ($rows) => $rows->total() === 2 && $rows->every(fn ($r) => $r->agency_id === $agency->id));
    }
}
