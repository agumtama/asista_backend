<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_email_token_changes_password_revokes_sessions_and_cannot_be_reused(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        DB::table('api_tokens')->insert(['user_id' => $user->id, 'hash' => hash('sha256', 'old-token'), 'expires_at' => now()->addDay(), 'created_at' => now(), 'updated_at' => now()]);
        $this->postJson('/api/v1/forgot-password', ['email' => $user->email])->assertOk();
        $token = '';
        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use (&$token) {
            $token = $notification->token;

            return true;
        });
        $this->get('/reset-password/'.$token.'?email='.urlencode($user->email))->assertOk();
        $data = ['email' => $user->email, 'token' => $token, 'password' => 'NewPassword123!', 'password_confirmation' => 'NewPassword123!'];
        $this->post('/reset-password', $data)->assertRedirect('/reset-password/success');
        $this->assertTrue(Hash::check('NewPassword123!', $user->fresh()->password));
        $this->assertDatabaseCount('api_tokens', 0);
        $this->post('/reset-password', $data)->assertSessionHasErrors('email');
    }

    public function test_unknown_email_has_generic_response_and_invalid_token_does_not_change_password(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $hash = $user->password;
        $this->postJson('/api/v1/forgot-password', ['email' => 'missing@example.test'])->assertOk()->assertJsonStructure(['message']);
        Notification::assertNothingSent();
        $this->post('/reset-password', ['email' => $user->email, 'token' => 'invalid', 'password' => 'NewPassword123!', 'password_confirmation' => 'NewPassword123!'])->assertSessionHasErrors('email');
        $this->assertSame($hash, $user->fresh()->password);
    }
}
