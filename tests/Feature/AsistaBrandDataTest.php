<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AsistaBrandDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_existing_accounts_are_renamed_without_changing_ids_or_custom_passwords(): void
    {
        $admin = User::factory()->create(['name' => 'Admin Rumah Percaya', 'email' => 'admin@rumahpercaya.test', 'password' => 'DemoRumah123!']);
        $family = User::factory()->create(['email' => 'family@rumahpercaya.test', 'password' => 'MyPersonalSecret123!']);
        $migration = require database_path('migrations/2026_09_24_121605_rebrand_existing_platform_data_to_asista.php');
        $migration->up();
        $this->assertSame('admin@asista.test', $admin->fresh()->email);
        $this->assertSame('Admin ASISTA', $admin->fresh()->name);
        $this->assertTrue(Hash::check('DemoAsista123!', $admin->fresh()->password));
        $this->assertSame('family@asista.test', $family->fresh()->email);
        $this->assertTrue(Hash::check('MyPersonalSecret123!', $family->fresh()->password));
        $this->assertDatabaseCount('users', 2);
        $migration->up();
        $this->assertDatabaseCount('users', 2);
    }
}
