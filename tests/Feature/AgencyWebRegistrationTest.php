<?php

namespace Tests\Feature;

use App\Http\Controllers\AgencyRegistrationController;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AgencyWebRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private function registration(): array
    {
        $data = ['company_name' => 'PT Agency Uji', 'business_type' => 'PT', 'kbli' => '78100', 'company_npwp' => '123456789', 'city' => 'Bandung', 'address' => 'Alamat perusahaan', 'company_phone' => '081234567890', 'company_email' => 'company@example.test', 'name' => 'Pengelola Uji', 'position' => 'Direktur', 'identity_number' => '1234567890123456', 'manager_npwp' => '123456789', 'phone' => '081234567890', 'email' => 'agency@example.test', 'password' => 'SecurePassword123!', 'password_confirmation' => 'SecurePassword123!', 'legal_number' => 'NIB-TEST', 'consent' => '1', 'role' => 'admin'];
        foreach (AgencyRegistrationController::DOCUMENTS as $key => $label) {
            if ($key !== 'amendment') {
                $data[$key] = UploadedFile::fake()->create($key.'.pdf', 10, 'application/pdf');
            }
        }

        return $data;
    }

    public function test_registration_creates_pending_agency_and_private_documents_then_verifies_email(): void
    {
        Storage::fake('local');
        $code = '';
        Mail::shouldReceive('raw')->once()->andReturnUsing(function ($text, $callback) use (&$code): void {
            preg_match('/\b([0-9]{6})\b/', $text, $matches);
            $code = $matches[1];
        });
        $this->get('/register/agency')->assertOk()->assertSee('Dokumen Legalitas');
        $this->post('/register/agency', $this->registration())->assertRedirect('/register/agency/verify');
        $user = User::where('email', 'agency@example.test')->firstOrFail();
        $this->assertSame('agency', $user->role);
        $this->assertSame('pending', $user->verification);
        $this->assertNull($user->email_verified_at);
        $this->assertArrayNotHasKey('password', $user->registration_data);
        $this->assertDatabaseHas('agencies', ['user_id' => $user->id, 'name' => 'PT Agency Uji', 'verification' => 'pending']);
        $this->assertDatabaseCount('verification_requests', 7);
        foreach (DB::table('verification_requests')->get() as $document) {
            Storage::disk('local')->assertExists($document->document_path);
            $this->get('/admin/document/'.$document->id)->assertRedirect('/login');
        }
        $this->post('/register/agency/verify', ['code' => $code])->assertRedirect('/register/agency/verify');
        $this->assertNotNull($user->fresh()->email_verified_at);
        $this->assertSame('pending', $user->fresh()->verification);
        $this->get('/register/agency/verify')->assertOk()->assertSee('Email terverifikasi');
        $this->actingAs($user)->get('/admin')->assertForbidden();
    }

    public function test_invalid_registration_does_not_create_user_and_expired_code_is_rejected(): void
    {
        Storage::fake('local');
        $data = $this->registration();
        unset($data['nib']);
        $this->post('/register/agency', $data)->assertSessionHasErrors('nib');
        $this->assertDatabaseCount('agencies', 0);
        $user = User::factory()->create(['role' => 'agency', 'email_verified_at' => null]);
        $this->withSession(['agency_registration_user' => $user->id, 'agency_code_expires' => time() - 1])->post('/register/agency/verify', ['code' => '123456'])->assertSessionHasErrors('code');
        $this->assertNull($user->fresh()->email_verified_at);
    }
}
