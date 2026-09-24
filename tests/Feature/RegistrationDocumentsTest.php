<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RegistrationDocumentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_details_are_validated_stored_and_hidden_from_session(): void
    {
        $details = ['phone' => '+6281234567890', 'province' => 'Jawa Barat', 'city' => 'Bandung', 'district' => 'Coblong', 'address' => 'Jalan contoh 10', 'occupation' => 'Karyawan'];
        $this->postJson('/api/v1/register', $this->data('family') + ['registration_data' => $details])
            ->assertCreated()->assertJsonMissingPath('user.registration_data');
        $user = User::where('email', 'family@example.test')->firstOrFail();
        $this->assertSame('Bandung', $user->registration_data['city']);
        $this->postJson('/api/v1/register', array_replace($this->data('family'), ['email' => 'invalid@example.test', 'registration_data' => ['phone' => 'invalid']]))
            ->assertUnprocessable()->assertJsonValidationErrors(['registration_data.phone', 'registration_data.address']);
    }

    private function data(string $role): array
    {
        return ['name' => 'Pendaftar Baru', 'email' => $role.'@example.test', 'password' => 'Password123!', 'role' => $role];
    }

    public function test_required_documents_are_enforced_without_creating_accounts(): void
    {
        foreach (['worker' => ['ktp', 'photo'], 'agency' => ['deed', 'nib', 'npwp', 'business_license']] as $role => $fields) {
            $this->postJson('/api/v1/register', $this->data($role))->assertUnprocessable()->assertJsonValidationErrors($fields);
            $this->assertDatabaseMissing('users', ['email' => $role.'@example.test']);
        }
    }

    public function test_worker_documents_are_private_and_available_before_profile_completion(): void
    {
        Storage::fake('local');
        $this->postJson('/api/v1/register', $this->data('worker') + [
            'ktp' => UploadedFile::fake()->create('ktp.pdf', 20, 'application/pdf'),
            'photo' => UploadedFile::fake()->image('foto.jpg'),
        ])->assertCreated()->assertJsonPath('user.verification', 'pending');
        $user = User::where('email', 'worker@example.test')->firstOrFail();
        $documents = DB::table('verification_requests')->where('user_id', $user->id)->get();
        $this->assertCount(2, $documents);
        foreach ($documents as $document) {
            Storage::disk('local')->assertExists($document->document_path);
        }
        $photo = $documents->firstWhere('document_type', 'photo');
        $this->get('/admin/document/'.$photo->id)->assertRedirect('/login');
        $this->actingAs($user)->get('/admin/document/'.$photo->id)->assertForbidden();
        $this->get('/admin/registrants/'.$user->id)->assertForbidden();
        $admin = User::factory()->create();
        $admin->role = 'admin';
        $admin->save();
        $this->actingAs($admin)->get('/admin?section=workers')->assertOk()->assertSee('worker@example.test');
        $this->get('/admin/registrants/'.$user->id)->assertOk()->assertSee('KTP')->assertSee('Foto diri');
        $this->get('/admin/document/'.$photo->id)->assertOk()->assertHeader('Content-Type', 'image/jpeg')->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertDatabaseHas('audit_logs', ['action' => 'verification.document_accessed', 'user_id' => $admin->id]);
        $this->post('/admin/verify/verification_requests/'.$photo->id, ['status' => 'verified', 'note' => 'Foto telah diperiksa'])->assertRedirect();
        $this->assertSame('pending', $user->fresh()->verification);
        $this->post('/admin/verify/verification_requests/'.$documents->firstWhere('document_type', 'ktp')->id, ['status' => 'verified', 'note' => 'KTP telah diperiksa'])->assertRedirect();
        $this->assertSame('verified', $user->fresh()->verification);
    }

    public function test_agency_requires_all_four_legal_documents(): void
    {
        Storage::fake('local');
        $files = [];
        foreach (['deed', 'nib', 'npwp', 'business_license'] as $type) {
            $files[$type] = UploadedFile::fake()->create($type.'.pdf', 20, 'application/pdf');
        }
        $this->postJson('/api/v1/register', $this->data('agency') + $files)->assertCreated();
        $this->assertDatabaseCount('verification_requests', 4);
        $this->assertCount(4, Storage::disk('local')->allFiles());
    }

    public function test_invalid_files_leave_no_account_or_stored_documents(): void
    {
        Storage::fake('local');
        $this->postJson('/api/v1/register', $this->data('worker') + [
            'ktp' => UploadedFile::fake()->create('ktp.pdf', 5121, 'application/pdf'),
            'photo' => UploadedFile::fake()->create('foto.pdf', 20, 'application/pdf'),
        ])->assertUnprocessable()->assertJsonValidationErrors(['ktp', 'photo']);
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('verification_requests', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }
}
