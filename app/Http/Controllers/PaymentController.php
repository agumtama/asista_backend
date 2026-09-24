<?php

namespace App\Http\Controllers;

use App\Services\Midtrans;
use App\Services\Platform;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PaymentController extends Controller
{
    public function __construct(private Midtrans $midtrans) {}

    private function ownedBooking(Request $request, int $id): object
    {
        $booking = DB::table('bookings')->find($id);
        abort_unless($booking, 404);
        abort_unless($request->user()->role === 'family' && $booking->family_id === $request->user()->id, 403);

        return $booking;
    }

    public function checkout(Request $request, int $id): array
    {
        $this->ownedBooking($request, $id);
        $this->midtrans->ready();
        // Persist the order before contacting Midtrans so a timeout can never generate a second order.
        $paymentId = DB::transaction(function () use ($id): int {
            $booking = DB::table('bookings')->where('id', $id)->lockForUpdate()->first();
            abort_unless($booking->status === 'accepted' && $booking->payment_status === 'unpaid', 422, 'Booking harus diterima dan belum dibayar.');
            $payment = DB::table('payments')->where('booking_id', $id)->latest('id')->first();
            if ($payment && ! in_array($payment->status, ['deny', 'cancel', 'expire', 'failure'])) {
                return $payment->id;
            }

            return DB::table('payments')->insertGetId([
                'booking_id' => $id, 'order_id' => 'ASISTA-'.Str::uuid(), 'amount' => $booking->total,
                'production' => (bool) config('services.midtrans.production'), 'created_at' => now(), 'updated_at' => now(),
            ]);
        });

        return DB::transaction(function () use ($request, $id, $paymentId): array {
            $booking = DB::table('bookings')->where('id', $id)->lockForUpdate()->first();
            $payment = DB::table('payments')->where('id', $paymentId)->lockForUpdate()->first();
            abort_unless($booking->payment_status === 'unpaid' && $booking->status === 'accepted', 422, 'Booking tidak dapat dibayar.');
            $this->checkEnvironment($payment);
            if (! $payment->snap_token) {
                $payload = [
                    'transaction_details' => ['order_id' => $payment->order_id, 'gross_amount' => (int) $payment->amount],
                    'customer_details' => ['first_name' => $request->user()->name, 'email' => $request->user()->email],
                    'item_details' => [['id' => 'booking-'.$id, 'price' => (int) $payment->amount, 'quantity' => 1, 'name' => 'Layanan ASISTA booking #'.$id]],
                    'credit_card' => ['secure' => true],
                    'callbacks' => ['finish' => config('services.midtrans.finish_url') ?: url('/payments/finish')],
                ];
                $result = $this->midtrans->request('POST', '/transactions', $payload, true);
                abort_unless(is_string($result['token'] ?? null) && filled($result['token']) && $this->midtrans->validRedirect($result['redirect_url'] ?? ''), 502, 'Respons pembayaran tidak valid.');
                DB::table('payments')->where('id', $paymentId)->update([
                    'snap_token' => $result['token'], 'redirect_url' => $result['redirect_url'], 'updated_at' => now(),
                ]);
                Platform::audit($request->user()->id, 'payment.snap_created', 'booking:'.$id, ['order_id' => $payment->order_id]);
                $payment = DB::table('payments')->find($paymentId);
            }

            return ['order_id' => $payment->order_id, 'redirect_url' => $payment->redirect_url, 'status' => $payment->status];
        });
    }

    private function checkEnvironment(object $payment): void
    {
        abort_unless((bool) $payment->production === (bool) config('services.midtrans.production'), 409, 'Lingkungan Midtrans berbeda dengan transaksi ini. Hubungi admin.');
    }

    private function synchronize(object $payment): array
    {
        return DB::transaction(function () use ($payment): array {
            $booking = DB::table('bookings')->where('id', $payment->booking_id)->lockForUpdate()->first();
            $payment = DB::table('payments')->where('id', $payment->id)->lockForUpdate()->first();
            $this->checkEnvironment($payment);
            $data = $this->midtrans->request('GET', '/'.rawurlencode($payment->order_id).'/status');
            $status = $data['transaction_status'] ?? '';
            if ($status === 'not_found') {
                return ['status' => $payment->status, 'payment_status' => $booking->payment_status];
            }
            abort_unless(($data['order_id'] ?? null) === $payment->order_id
                && is_numeric($data['gross_amount'] ?? null) && (float) $data['gross_amount'] === (float) $payment->amount
                && ($data['currency'] ?? 'IDR') === 'IDR', 422, 'Identitas atau nominal transaksi tidak sesuai.');
            abort_unless(in_array($status, ['pending', 'capture', 'settlement', 'deny', 'cancel', 'expire', 'failure', 'refund', 'partial_refund', 'chargeback', 'partial_chargeback', 'authorize']), 422, 'Status Midtrans tidak dikenali.');
            $paid = $status === 'settlement' || ($status === 'capture' && ($data['fraud_status'] ?? '') === 'accept');
            $refund = in_array($status, ['refund', 'partial_refund', 'chargeback', 'partial_chargeback']);
            // Ignore late notifications that would move a paid/refunded transaction backwards.
            if (($payment->paid_at && ! $paid && ! $refund) || (in_array($payment->status, ['refund', 'partial_refund', 'chargeback', 'partial_chargeback']) && ! $refund)) {
                return ['status' => $payment->status, 'payment_status' => $booking->payment_status];
            }
            DB::table('payments')->where('id', $payment->id)->update([
                'status' => $status, 'transaction_id' => $data['transaction_id'] ?? null,
                'payment_type' => $data['payment_type'] ?? null,
                'paid_at' => $payment->paid_at ?? ($paid ? now() : null), 'updated_at' => now(),
            ]);
            $bookingStatus = $refund ? $status : ($paid ? 'paid' : $booking->payment_status);
            DB::table('bookings')->where('id', $booking->id)->update(['payment_status' => $bookingStatus, 'updated_at' => now()]);
            if ($payment->status !== $status || $booking->payment_status !== $bookingStatus) {
                Platform::audit($booking->family_id, 'payment.midtrans.'.$status, 'booking:'.$booking->id, ['order_id' => $payment->order_id, 'source' => 'midtrans_status_api']);
            }

            return ['status' => $status, 'payment_status' => $bookingStatus];
        });
    }

    public function refresh(Request $request, int $id): array
    {
        $booking = $this->ownedBooking($request, $id);
        $payment = DB::table('payments')->where('booking_id', $id)->latest('id')->first();

        return $payment ? $this->synchronize($payment) : ['status' => 'not_started', 'payment_status' => $booking->payment_status];
    }

    public function notification(Request $request): array
    {
        $this->midtrans->ready();
        $data = $request->validate(['order_id' => 'required|string|max:50', 'status_code' => 'required|string|max:3', 'gross_amount' => 'required|string|max:30', 'signature_key' => 'required|string|size:128']);
        $signature = hash('sha512', $data['order_id'].$data['status_code'].$data['gross_amount'].config('services.midtrans.server_key'));
        abort_unless(hash_equals($signature, $data['signature_key']), 403, 'Signature Midtrans tidak valid.');
        $payment = DB::table('payments')->where('order_id', $data['order_id'])->first();
        abort_unless($payment, 404);
        $this->synchronize($payment);

        return ['message' => 'Notifikasi diterima.'];
    }
}
