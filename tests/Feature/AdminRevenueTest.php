<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DemoBookingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminRevenueTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_sees_rates_booking_snapshots_and_separate_demo_revenue(): void
    {
        $this->seed();
        $this->seed(DemoBookingSeeder::class);
        $this->seed(DemoBookingSeeder::class);
        $this->assertDatabaseCount('bookings', 3);
        $admin = User::where('role', 'admin')->first();
        $this->actingAs($admin)->get('/admin?section=workers')->assertOk()->assertSee('Tarif pekerja: Rp 180.000')->assertSee('Fee agency: Rp 25.000');
        $this->get('/admin?section=agencies')->assertOk()->assertSee('fee Rp 25.000');
        $booking = DB::table('bookings')->where('is_demo', true)->where('total', 400000)->first();
        DB::table('workers')->where('id', $booking->worker_id)->update(['rate' => 999000]);
        $this->get('/admin?section=bookings')->assertOk()->assertSee('Tarif saat booking Rp 180.000')->assertSee('DEMO')->assertViewHas('revenue', function ($rows) {
            return (int) $rows[0]['platform'] === 0 && (int) $rows[1]['platform'] === 30000
                && (int) $rows[1]['total'] === 565000 && (int) $rows[1]['pending'] === 15000;
        });
        DB::table('bookings')->where('id', $booking->id)->update(['is_demo' => false]);
        $this->get('/admin')->assertOk()->assertViewHas('revenue', fn ($rows) => (int) $rows[0]['platform'] === 15000);
        DB::table('bookings')->where('id', $booking->id)->update(['payment_status' => 'refund']);
        $this->get('/admin')->assertOk()->assertViewHas('revenue', fn ($rows) => (int) $rows[0]['platform'] === 0);
    }

    public function test_demo_booking_cannot_create_a_real_payment(): void
    {
        $this->seed();
        $this->seed(DemoBookingSeeder::class);
        $token = $this->postJson('/api/v1/login', ['email' => 'family@asista.test', 'password' => 'DemoAsista123!'])->json('token');
        $booking = DB::table('bookings')->where('payment_status', 'unpaid')->first();
        $this->postJson('/api/v1/bookings/'.$booking->id.'/payment', [], ['Authorization' => 'Bearer '.$token])->assertUnprocessable();
        $this->assertDatabaseCount('payments', 0);
    }
}
