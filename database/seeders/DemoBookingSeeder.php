<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoBookingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            throw new \RuntimeException('Booking demo hanya boleh dibuat pada lingkungan lokal/testing.');
        }
        $family = DB::table('users')->where('email', 'family@asista.test')->first();
        if (! $family) {
            throw new \RuntimeException('Akun demo keluarga belum tersedia.');
        }
        DB::transaction(function () use ($family): void {
            foreach ([['worker1', 2, 'completed', 'paid'], ['worker2', 1, 'completed', 'paid'], ['worker3', 4, 'accepted', 'unpaid']] as $index => [$email, $units, $status, $payment]) {
                $worker = DB::table('workers')->join('users', 'users.id', '=', 'workers.user_id')->where('users.email', $email.'@asista.test')->select('workers.*')->first();
                if (! $worker) {
                    throw new \RuntimeException('Profil pekerja demo belum tersedia.');
                }
                $scope = '[DEMO ASISTA '.($index + 1).'] Contoh perhitungan layanan keluarga; bukan transaksi nyata.';
                if (DB::table('bookings')->where('is_demo', true)->where('scope', $scope)->exists()) {
                    continue;
                }
                $start = now()->subDays(30 + $index * 5)->startOfDay();
                $end = match ($worker->rate_unit) {
                    'hourly' => $start->copy()->addHours($units), 'monthly' => $start->copy()->addMonthsNoOverflow($units), default => $start->copy()->addDays($units),
                };
                $pay = $worker->rate * $units;
                DB::table('bookings')->insert([
                    'family_id' => $family->id, 'worker_id' => $worker->id, 'agency_id' => $worker->agency_id,
                    'starts_at' => $start, 'ends_at' => $end, 'address' => 'Alamat contoh ASISTA (data demo)',
                    'scope' => $scope, 'rate_unit' => $worker->rate_unit, 'units' => $units,
                    'worker_pay' => $pay, 'agency_fee' => $worker->agency_fee, 'platform_fee' => 15000,
                    'total' => $pay + $worker->agency_fee + 15000, 'status' => $status, 'payment_status' => $payment,
                    'is_demo' => true, 'contract' => 'Simulasi pembagian pembayaran untuk demonstrasi CMS. Tidak ada pembayaran atau pencairan nyata.',
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        });
    }
}
