<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            throw new \RuntimeException('Demo seeder hanya untuk lokal/testing.');
        }
        foreach ([['Admin ASISTA', 'admin@asista.test', 'admin'], ['Keluarga Anindya', 'family@asista.test', 'family'], ['Mitra Keluarga Agency', 'agency@asista.test', 'agency']] as [$name,$email,$role]) {
            $u = User::firstOrCreate(['email' => $email], ['name' => $name, 'password' => 'DemoAsista123!']);
            $u->forceFill(['role' => $role, 'verification' => 'verified'])->save();
        }
        $agencyUser = User::where('role', 'agency')->first();
        DB::table('agencies')->updateOrInsert(['user_id' => $agencyUser->id], ['name' => 'Mitra Keluarga', 'city' => 'Jakarta Selatan', 'legal_number' => 'DEMO-LEGAL-001', 'verification' => 'verified', 'subscription' => 'demo', 'created_at' => now(), 'updated_at' => now()]);
        $agency = DB::table('agencies')->first();
        $people = [['Siti Aminah', 'babysitter', 'Jakarta Selatan', 5, 180000, 'daily', ['Toddler care', 'MPASI', 'Pertolongan pertama']], ['Rina Wulandari', 'art', 'Tangerang Selatan', 7, 150000, 'daily', ['Bersih-bersih', 'Setrika', 'Memasak']], ['Dewi Lestari', 'babysitter', 'Jakarta Selatan', 4, 45000, 'hourly', ['Newborn care', 'Rutinitas tidur']], ['Nur Aisyah', 'art', 'Depok', 6, 2800000, 'monthly', ['Cuci & setrika', 'Memasak', 'Bersih-bersih']]];
        foreach ($people as $i => [$name,$category,$city,$years,$rate,$unit,$skills]) {
            $u = User::firstOrCreate(['email' => 'worker'.($i + 1).'@asista.test'], ['name' => $name, 'password' => 'DemoAsista123!']);
            $u->forceFill(['role' => 'worker', 'verification' => 'verified'])->save();
            DB::table('workers')->updateOrInsert(['user_id' => $u->id], ['name' => $name, 'category' => $category, 'city' => $city, 'experience_years' => $years, 'rate' => $rate, 'rate_unit' => $unit, 'skills' => json_encode($skills), 'certifications' => json_encode(['Pelatihan dasar layanan keluarga (data demo)']), 'bio' => 'Pendamping keluarga yang teliti, sabar, dan berpengalaman. Siap membantu rutinitas rumah dengan komunikasi yang terbuka. Profil ini merupakan data demonstrasi.', 'agency_id' => $i % 2 === 0 ? $agency->id : null, 'agency_fee' => $i % 2 === 0 ? 25000 : 0, 'verification' => 'verified', 'available' => true, 'arrangement' => $unit === 'monthly' ? 'live_in' : 'live_out', 'created_at' => now(), 'updated_at' => now()]);
        }
    }
}
