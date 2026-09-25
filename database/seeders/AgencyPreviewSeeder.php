<?php

namespace Database\Seeders;

use App\Http\Controllers\AgencyRegistrationController;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class AgencyPreviewSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            throw new \RuntimeException('Data preview hanya untuk lokal/testing.');
        }
        if (! extension_loaded('gd')) {
            throw new \RuntimeException('Ekstensi GD diperlukan untuk contoh dokumen PNG.');
        }
        $user = User::firstOrCreate(['email' => 'agency.preview@asista.test'], ['name' => 'Nadia Demo', 'password' => 'DemoAsista123!']);
        if (! $user->wasRecentlyCreated && ($user->registration_data['preview_seed'] ?? null) !== 'agency-cms-v1') {
            throw new \RuntimeException('Email preview sudah digunakan akun lain; tidak diubah.');
        }
        if ($user->wasRecentlyCreated) {
            $user->role = 'agency';
            $user->verification = 'verified';
            $user->email_verified_at = now();
            $user->registration_data = ['preview_seed' => 'agency-cms-v1', 'company_name' => 'ASISTA Demo Agency', 'company_phone' => '080000000000', 'company_email' => 'company.preview@asista.test', 'address' => 'Jalan Contoh No. 10 (alamat fiktif)', 'city' => 'Jakarta Selatan', 'website' => 'https://example.com', 'social_media' => '@agency_demo_fiktif', 'description' => 'Agency demonstrasi untuk preview CMS ASISTA. Semua profil dan dokumen pada akun ini adalah data fiktif.', 'name' => 'Nadia Demo', 'position' => 'Pengelola Demo', 'phone' => '080000000001', 'manager_address' => 'Alamat pengelola fiktif'];
            $user->save();
        }
        DB::table('agencies')->insertOrIgnore(['user_id' => $user->id, 'name' => 'ASISTA Demo Agency', 'city' => 'Jakarta Selatan', 'legal_number' => 'DEMO-BUKAN-NIB-ASLI', 'verification' => 'verified', 'subscription' => 'demo', 'created_at' => now(), 'updated_at' => now()]);
        $agencyId = DB::table('agencies')->where('user_id', $user->id)->value('id');
        foreach (['art', 'babysitter'] as $category) {
            foreach (['hourly' => 50000, 'daily' => 200000, 'monthly' => 3500000] as $unit => $rate) {
                DB::table('agency_rates')->insertOrIgnore(['agency_id' => $agencyId, 'category' => $category, 'rate_unit' => $unit, 'arrangement' => $unit === 'monthly' ? 'live_in' : 'live_out', 'rate' => $rate, 'agency_fee' => 25000, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
        foreach (['Ayu Demo', 'Rani Demo', 'Dina Demo'] as $index => $name) {
            $workerUser = User::firstOrCreate(['email' => 'worker.preview'.($index + 1).'@asista.test'], ['name' => $name, 'password' => 'DemoAsista123!']);
            if ($workerUser->wasRecentlyCreated) {
                $workerUser->role = 'worker';
                $workerUser->registration_data = ['preview_seed' => 'agency-cms-v1'];
                $workerUser->save();
            } elseif (($workerUser->registration_data['preview_seed'] ?? null) !== 'agency-cms-v1') {
                throw new \RuntimeException('Email pekerja preview sudah digunakan; tidak diubah.');
            }
            DB::table('workers')->insertOrIgnore(['user_id' => $workerUser->id, 'agency_id' => $agencyId, 'name' => $name, 'category' => $index === 1 ? 'art' : 'babysitter', 'city' => ['Jakarta Selatan', 'Depok', 'Tangerang'][$index], 'bio' => 'Profil fiktif untuk demonstrasi CV. Teliti, komunikatif, dan berpengalaman mendampingi keluarga.', 'skills' => json_encode(['Komunikasi baik', 'Memasak', 'Merawat anak']), 'certifications' => json_encode(['Pelatihan layanan keluarga (DEMO)']), 'experience_years' => $index + 2, 'arrangement' => 'live_out', 'rate_unit' => 'daily', 'rate' => 200000, 'agency_fee' => 25000, 'verification' => $index === 2 ? 'pending' : 'verified', 'available' => $index !== 2, 'created_at' => now(), 'updated_at' => now()]);
        }
        foreach (AgencyRegistrationController::DOCUMENTS as $type => $label) {
            $path = 'verification/'.$user->id.'/preview-'.$type.'.png';
            if (DB::table('verification_requests')->where('user_id', $user->id)->where('document_path', $path)->exists()) {
                continue;
            }
            $canvas = imagecreatetruecolor(1100, 720);
            $cream = imagecolorallocate($canvas, 246, 247, 241);
            $green = imagecolorallocate($canvas, 28, 77, 64);
            $gray = imagecolorallocate($canvas, 103, 120, 110);
            imagefill($canvas, 0, 0, $cream);
            imagerectangle($canvas, 30, 30, 1069, 689, $green);
            imagestring($canvas, 5, 65, 70, 'ASISTA | DOKUMEN CONTOH', $green);
            imagestring($canvas, 5, 65, 145, strtoupper($label), $green);
            imagestring($canvas, 5, 65, 235, 'ASISTA Demo Agency', $green);
            imagestring($canvas, 4, 65, 285, 'Nomor: DEMO-'.strtoupper($type).'-001', $gray);
            imagestring($canvas, 5, 65, 400, 'DATA FIKTIF - TIDAK SAH UNTUK KEPERLUAN LEGAL', $green);
            imagestring($canvas, 4, 65, 460, 'Contoh ini hanya untuk preview thumbnail dan modal CMS.', $gray);
            imagestring($canvas, 4, 65, 585, 'Tidak memuat identitas, tanda tangan, atau stempel asli.', $gray);
            ob_start();
            imagepng($canvas);
            $bytes = ob_get_clean();
            imagedestroy($canvas);
            Storage::disk('local')->put($path, $bytes);
            DB::table('verification_requests')->insert(['user_id' => $user->id, 'document_path' => $path, 'document_type' => $type, 'original_name' => 'DEMO-'.$type.'.png', 'mime_type' => 'image/png', 'file_size' => strlen($bytes), 'status' => $type === 'amendment' ? 'pending' : 'verified', 'note' => 'Dokumen fiktif untuk preview, bukan hasil pemeriksaan legalitas.', 'created_at' => now(), 'updated_at' => now()]);
        }
        $this->command?->info('Preview tersedia: agency.preview@asista.test (password awal DemoAsista123!).');
    }
}
