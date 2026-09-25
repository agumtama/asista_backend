<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DemoBookingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AgencyPortalTest extends TestCase
{
    use RefreshDatabase;

    public function test_replacing_document_requires_latest_versions_to_be_verified(): void
    {
        $this->seed();
        Storage::fake('local');
        $agencyUser = User::where('role', 'agency')->firstOrFail();
        foreach (['nib', 'deed', 'npwp', 'business_license'] as $type) {
            DB::table('verification_requests')->insert(['user_id' => $agencyUser->id, 'document_type' => $type, 'document_path' => 'old-'.$type.'.pdf', 'status' => 'verified', 'created_at' => now(), 'updated_at' => now()]);
        }
        $this->actingAs($agencyUser)->post('/agency/documents', ['document_type' => 'nib', 'document' => UploadedFile::fake()->create('new.pdf', 10, 'application/pdf')])->assertRedirect();
        $this->assertDatabaseHas('agencies', ['user_id' => $agencyUser->id, 'verification' => 'pending']);
        $new = DB::table('verification_requests')->latest('id')->first();
        $admin = User::where('role', 'admin')->firstOrFail();
        $agencyId = DB::table('agencies')->where('user_id', $agencyUser->id)->value('id');
        $this->actingAs($admin)->post('/admin/verify/agencies/'.$agencyId, ['status' => 'verified', 'note' => 'Percobaan bypass'])->assertUnprocessable();
        $this->post('/admin/verify/verification_requests/'.$new->id, ['status' => 'verified', 'note' => 'Dokumen sudah diperiksa'])->assertRedirect();
        $this->assertDatabaseHas('agencies', ['user_id' => $agencyUser->id, 'verification' => 'verified']);
        $old = DB::table('verification_requests')->where('document_type', 'nib')->oldest('id')->first();
        $this->post('/admin/verify/verification_requests/'.$old->id, ['status' => 'rejected', 'note' => 'Versi lama tidak berlaku'])->assertRedirect();
        $this->assertDatabaseHas('agencies', ['user_id' => $agencyUser->id, 'verification' => 'verified']);
    }

    public function test_agency_login_tabs_and_tenant_boundaries(): void
    {
        $this->seed();
        $this->post('/login', ['email' => 'agency@asista.test', 'password' => 'DemoAsista123!'])->assertRedirect('/agency');
        foreach (['profile', 'manager', 'documents', 'rates', 'workers'] as $tab) {
            $this->get('/agency?tab='.$tab)->assertOk()->assertSee('Profil Agency');
        }
        $this->get('/admin')->assertForbidden();
        $worker = DB::table('workers')->whereNotNull('agency_id')->first();
        $independent = DB::table('workers')->whereNull('agency_id')->first();
        $this->get('/agency/workers/'.$worker->id)->assertOk()->assertSee($worker->name);
        $this->get('/agency/workers/'.$independent->id)->assertNotFound();
        $this->get('/agency/workers/'.$independent->id.'/photo')->assertNotFound();
        $this->get('/agency/workers/'.$independent->id.'/video')->assertNotFound();
        $this->post('/agency/workers/'.$independent->id.'/video')->assertNotFound();
        $other = User::factory()->create(['role' => 'agency']);
        DB::table('agencies')->insert(['user_id' => $other->id, 'name' => 'Other Agency', 'city' => 'Other City', 'legal_number' => 'OTHER', 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($other)->get('/agency/workers/'.$worker->id)->assertNotFound();
        $this->post('/logout')->assertRedirect('/login');
        $this->get('/agency')->assertRedirect('/login');
    }

    public function test_private_documents_and_rate_application_are_scoped(): void
    {
        $this->seed();
        $this->seed(DemoBookingSeeder::class);
        $bookingSnapshot = DB::table('bookings')->orderBy('id')->get()->toJson();
        Storage::fake('local');
        $user = User::where('email', 'agency@asista.test')->firstOrFail();
        $agency = DB::table('agencies')->where('user_id', $user->id)->first();
        $this->actingAs($user);
        $worker = DB::table('workers')->where('agency_id', $agency->id)->first();
        $rate = ['category' => $worker->category, 'rate_unit' => $worker->rate_unit, 'arrangement' => $worker->arrangement, 'rate' => 222000, 'agency_fee' => 31000];
        $this->post('/agency/rates', $rate)->assertRedirect();
        $rateId = DB::table('agency_rates')->value('id');
        $this->post('/agency/rates/'.$rateId.'/apply')->assertRedirect();
        $this->assertSame($bookingSnapshot, DB::table('bookings')->orderBy('id')->get()->toJson());
        $this->assertDatabaseHas('workers', ['id' => $worker->id, 'rate' => 222000, 'agency_fee' => 31000]);
        $other = User::factory()->create(['role' => 'agency']);
        DB::table('agencies')->insert(['user_id' => $other->id, 'name' => 'Other', 'city' => 'City', 'legal_number' => 'OTHER', 'verification' => 'verified', 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($other)->post('/agency/rates/'.$rateId.'/apply')->assertNotFound();
        $this->actingAs($user)->post('/agency/documents', ['document_type' => 'nib', 'document' => UploadedFile::fake()->create('nib.pdf', 10, 'application/pdf')])->assertRedirect();
        $document = DB::table('verification_requests')->where('user_id', $user->id)->first();
        Storage::disk('local')->assertExists($document->document_path);
        $this->get('/agency/documents/'.$document->id)->assertOk();
        $this->assertDatabaseHas('agencies', ['id' => $agency->id, 'verification' => 'pending']);
        $this->post('/agency/rates/'.$rateId.'/apply')->assertForbidden();
        $this->actingAs($other)->get('/agency/documents/'.$document->id)->assertNotFound();
    }

    public function test_profile_manager_and_video_validation(): void
    {
        $this->seed();
        Storage::fake('local');
        $user = User::where('role', 'agency')->firstOrFail();
        $this->actingAs($user)->post('/agency/manager', ['name' => 'Updated Owner', 'position' => 'Owner', 'phone' => '08123456789'])->assertRedirect();
        $this->assertSame('Updated Owner', $user->fresh()->name);
        $this->post('/agency/profile', ['company_name' => 'Updated Agency', 'city' => 'Bandung', 'address' => 'Jl Contoh', 'company_phone' => '08123456789', 'company_email' => 'company@example.test'])->assertRedirect();
        $this->assertDatabaseHas('agencies', ['user_id' => $user->id, 'name' => 'Updated Agency', 'verification' => 'pending']);
        $worker = DB::table('workers')->whereNotNull('agency_id')->first();
        $this->post('/agency/workers/'.$worker->id.'/video', ['video' => UploadedFile::fake()->create('bad.txt', 10, 'text/plain')])->assertSessionHasErrors('video');
        $this->post('/agency/workers/'.$worker->id.'/video', ['video' => UploadedFile::fake()->create('too-large.mp4', 20481, 'video/mp4')])->assertSessionHasErrors('video');
        $this->post('/agency/workers/'.$worker->id.'/video', ['video' => UploadedFile::fake()->create('intro.mp4', 10240, 'video/mp4')])->assertRedirect()->assertSessionHasNoErrors();
        $path = DB::table('workers')->where('id', $worker->id)->value('video_path');
        Storage::disk('local')->assertExists($path);
    }
}
