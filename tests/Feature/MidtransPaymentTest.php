<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MidtransPaymentTest extends TestCase
{
    use RefreshDatabase;

    private array $headers;

    private int $bookingId;

    private array $statusResponse = [];

    private bool $snapFails = false;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        config(['services.midtrans.enabled' => true, 'services.midtrans.server_key' => 'test-server-key', 'services.midtrans.production' => false]);
        Http::preventStrayRequests();
        Http::fake(function ($request) {
            if (str_contains($request->url(), '/snap/v1/transactions')) {
                if ($this->snapFails) {
                    return Http::failedConnection();
                }

                return Http::response(['token' => 'snap-test', 'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v2/vtweb/snap-test'], 201);
            }

            return Http::response($this->statusResponse);
        });
        $token = $this->postJson('/api/v1/login', ['email' => 'family@asista.test', 'password' => 'DemoAsista123!'])->json('token');
        $this->headers = ['Authorization' => 'Bearer '.$token];
        $worker = DB::table('workers')->where('user_id', User::where('email', 'worker1@asista.test')->value('id'))->first();
        $this->bookingId = $this->postJson('/api/v1/bookings', [
            'worker_id' => $worker->id, 'starts_at' => now()->addDays(2)->toDateTimeString(), 'units' => 2,
            'address' => 'Jakarta', 'scope' => 'Menjaga anak dan membantu keluarga.',
        ], $this->headers)->assertCreated()->json('id');
        DB::table('bookings')->where('id', $this->bookingId)->update(['status' => 'accepted']);
    }

    private function checkout(): string
    {
        $this->snapFails = false;

        return $this->postJson('/api/v1/bookings/'.$this->bookingId.'/payment', ['amount' => 1], $this->headers)->assertOk()->json('order_id');
    }

    private function fakeStatus(string $order, string $status, string $fraud = 'accept', int $amount = 400000): void
    {
        $this->statusResponse = [
            'order_id' => $order, 'status_code' => '200', 'gross_amount' => (string) $amount,
            'transaction_status' => $status, 'fraud_status' => $fraud, 'transaction_id' => 'tx-123', 'currency' => 'IDR', 'payment_type' => 'bank_transfer',
        ];
    }

    private function notification(string $order): array
    {
        return ['order_id' => $order, 'status_code' => '200', 'gross_amount' => '400000.00',
            'signature_key' => hash('sha512', $order.'200400000.00test-server-key')];
    }

    public function test_checkout_uses_server_price_and_reuses_the_order(): void
    {
        $order = $this->checkout();
        $this->postJson('/api/v1/bookings/'.$this->bookingId.'/payment', [], $this->headers)->assertOk()->assertJsonPath('order_id', $order);
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request['transaction_details']['gross_amount'] === 400000
            && $request->hasHeader('Authorization', 'Basic '.base64_encode('test-server-key:')));
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseHas('bookings', ['id' => $this->bookingId, 'payment_status' => 'unpaid']);
    }

    public function test_only_owner_of_accepted_unpaid_booking_can_checkout(): void
    {
        $url = '/api/v1/bookings/'.$this->bookingId.'/payment';
        $this->postJson($url)->assertUnauthorized();
        $token = $this->postJson('/api/v1/login', ['email' => 'worker1@asista.test', 'password' => 'DemoAsista123!'])->assertOk()->json('token');
        $this->postJson($url, [], ['Authorization' => 'Bearer '.$token])->assertForbidden();
        foreach ([['status' => 'requested', 'payment_status' => 'unpaid'], ['status' => 'accepted', 'payment_status' => 'paid']] as $state) {
            DB::table('bookings')->where('id', $this->bookingId)->update($state);
            $this->postJson($url, [], $this->headers)->assertUnprocessable();
        }
        Http::assertNothingSent();
    }

    public function test_signed_notification_uses_canonical_status_and_is_idempotent(): void
    {
        $order = $this->checkout();
        $this->fakeStatus($order, 'settlement');
        $payload = $this->notification($order);
        $this->postJson('/api/v1/payments/midtrans/notification', array_replace($payload, ['signature_key' => str_repeat('a', 128)]))->assertForbidden();
        $this->postJson('/api/v1/payments/midtrans/notification', $payload)->assertOk();
        $this->postJson('/api/v1/payments/midtrans/notification', $payload)->assertOk();
        $this->assertDatabaseHas('bookings', ['id' => $this->bookingId, 'payment_status' => 'paid']);
        $this->assertSame(1, DB::table('audit_logs')->where('action', 'payment.midtrans.settlement')->count());
        $this->fakeStatus($order, 'pending');
        $this->postJson('/api/v1/payments/midtrans/notification', $payload)->assertOk();
        $this->assertDatabaseHas('bookings', ['id' => $this->bookingId, 'payment_status' => 'paid']);
    }

    public function test_challenge_amount_mismatch_and_browser_callback_cannot_mark_paid(): void
    {
        $order = $this->checkout();
        $this->get('/payments/finish?transaction_status=settlement')->assertOk();
        $this->fakeStatus($order, 'capture', 'challenge');
        $this->postJson('/api/v1/bookings/'.$this->bookingId.'/payment/refresh', [], $this->headers)->assertOk()->assertJsonPath('payment_status', 'unpaid');
        $this->fakeStatus($order, 'settlement', 'accept', 1);
        $this->postJson('/api/v1/payments/midtrans/notification', $this->notification($order))->assertUnprocessable();
        $this->assertDatabaseHas('bookings', ['id' => $this->bookingId, 'payment_status' => 'unpaid']);
    }

    public function test_timeout_keeps_order_and_verified_expiry_allows_new_attempt(): void
    {
        $this->snapFails = true;
        $url = '/api/v1/bookings/'.$this->bookingId.'/payment';
        $this->postJson($url, [], $this->headers)->assertStatus(503);
        $order = DB::table('payments')->value('order_id');
        $this->assertSame($order, $this->checkout());
        $this->fakeStatus($order, 'expire');
        $this->postJson('/api/v1/bookings/'.$this->bookingId.'/payment/refresh', [], $this->headers)->assertOk();
        $this->assertNotSame($order, $this->checkout());
    }

    public function test_refunds_are_recorded_and_manual_confirmation_is_blocked(): void
    {
        $order = $this->checkout();
        $this->fakeStatus($order, 'settlement');
        $this->postJson('/api/v1/payments/midtrans/notification', $this->notification($order))->assertOk();
        $this->fakeStatus($order, 'refund');
        $this->postJson('/api/v1/payments/midtrans/notification', $this->notification($order))->assertOk();
        $this->assertDatabaseHas('bookings', ['id' => $this->bookingId, 'payment_status' => 'refund']);
        $this->actingAs(User::where('role', 'admin')->first())->postJson('/admin/bookings/'.$this->bookingId.'/payment', ['reference' => 'MANUAL-123'])->assertUnprocessable();
    }

    public function test_missing_key_returns_clear_error_without_external_requests(): void
    {
        config(['services.midtrans.server_key' => '']);
        $this->postJson('/api/v1/bookings/'.$this->bookingId.'/payment', [], $this->headers)->assertStatus(503)->assertJsonPath('message', 'Pembayaran Midtrans belum dikonfigurasi oleh pengelola.');
        Http::assertNothingSent();
    }
}
