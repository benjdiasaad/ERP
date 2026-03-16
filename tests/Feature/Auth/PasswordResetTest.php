<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\Auth\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    private const FORGOT_URL = '/api/auth/forgot-password';
    private const RESET_URL  = '/api/auth/reset-password';

    // ─── Forgot Password ──────────────────────────────────────────────────────

    public function test_forgot_password_sends_reset_link_for_existing_email(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->postJson(self::FORGOT_URL, ['email' => $user->email])
            ->assertOk()
            ->assertJsonFragment(['message' => 'Password reset link sent to your email.']);

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_forgot_password_returns_422_for_unknown_email(): void
    {
        $this->postJson(self::FORGOT_URL, ['email' => 'nobody@example.com'])
            ->assertUnprocessable()
            ->assertJsonStructure(['errors' => ['email']]);
    }

    public function test_forgot_password_requires_email_field(): void
    {
        $this->postJson(self::FORGOT_URL, [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_forgot_password_requires_valid_email_format(): void
    {
        $this->postJson(self::FORGOT_URL, ['email' => 'not-an-email'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    // ─── Reset Password ───────────────────────────────────────────────────────

    public function test_reset_password_succeeds_with_valid_token(): void
    {
        $user  = User::factory()->create();
        $token = Password::createToken($user);

        $this->postJson(self::RESET_URL, [
            'token'                 => $token,
            'email'                 => $user->email,
            'password'              => 'NewPassword1!',
            'password_confirmation' => 'NewPassword1!',
        ])
            ->assertOk()
            ->assertJsonFragment(['message' => 'Password has been reset successfully.']);

        $user->refresh();
        $this->assertTrue(Hash::check('NewPassword1!', $user->password));
    }

    public function test_reset_password_fails_with_invalid_token(): void
    {
        $user = User::factory()->create();

        $this->postJson(self::RESET_URL, [
            'token'                 => 'invalid-token',
            'email'                 => $user->email,
            'password'              => 'NewPassword1!',
            'password_confirmation' => 'NewPassword1!',
        ])
            ->assertUnprocessable()
            ->assertJsonStructure(['errors' => ['email']]);
    }

    public function test_reset_password_fails_with_wrong_email_for_token(): void
    {
        $user  = User::factory()->create();
        $other = User::factory()->create();
        $token = Password::createToken($user);

        $this->postJson(self::RESET_URL, [
            'token'                 => $token,
            'email'                 => $other->email,
            'password'              => 'NewPassword1!',
            'password_confirmation' => 'NewPassword1!',
        ])
            ->assertUnprocessable()
            ->assertJsonStructure(['errors' => ['email']]);
    }

    public function test_reset_password_requires_token_field(): void
    {
        $user = User::factory()->create();

        $this->postJson(self::RESET_URL, [
            'email'                 => $user->email,
            'password'              => 'NewPassword1!',
            'password_confirmation' => 'NewPassword1!',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['token']);
    }

    public function test_reset_password_requires_password_confirmation(): void
    {
        $user  = User::factory()->create();
        $token = Password::createToken($user);

        $this->postJson(self::RESET_URL, [
            'token'                 => $token,
            'email'                 => $user->email,
            'password'              => 'NewPassword1!',
            'password_confirmation' => 'DifferentPassword1!',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    }

    // ─── Password History ─────────────────────────────────────────────────────

    public function test_reset_password_rejects_recently_used_password(): void
    {
        $user = User::factory()->create(['password' => Hash::make('OldPassword1!')]);

        // Seed the password history with the current password
        DB::table('password_histories')->insert([
            'user_id'    => $user->id,
            'password'   => $user->password,
            'created_at' => now(),
        ]);

        $token = Password::createToken($user);

        $this->postJson(self::RESET_URL, [
            'token'                 => $token,
            'email'                 => $user->email,
            'password'              => 'OldPassword1!',
            'password_confirmation' => 'OldPassword1!',
        ])
            ->assertUnprocessable()
            ->assertJsonStructure(['errors' => ['email']]);
    }

    // ─── Post-reset side effects ──────────────────────────────────────────────

    public function test_reset_password_invalidates_existing_tokens(): void
    {
        ['user' => $user, 'token' => $authToken] = $this->setUpCompanyAndUser();

        $resetToken = Password::createToken($user);

        $this->postJson(self::RESET_URL, [
            'token'                 => $resetToken,
            'email'                 => $user->email,
            'password'              => 'NewPassword1!',
            'password_confirmation' => 'NewPassword1!',
        ])->assertOk();

        // Old auth token should no longer work
        $this->getJson('/api/auth/me', ['Authorization' => 'Bearer ' . $authToken])
            ->assertUnauthorized();
    }

    public function test_reset_password_updates_password_changed_at(): void
    {
        $user  = User::factory()->create();
        $token = Password::createToken($user);

        $before = now()->subSecond();

        $this->postJson(self::RESET_URL, [
            'token'                 => $token,
            'email'                 => $user->email,
            'password'              => 'NewPassword1!',
            'password_confirmation' => 'NewPassword1!',
        ])->assertOk();

        $user->refresh();
        $this->assertTrue($user->password_changed_at->isAfter($before));
    }

    public function test_reset_password_stores_new_password_in_history(): void
    {
        $user  = User::factory()->create();
        $token = Password::createToken($user);

        $this->postJson(self::RESET_URL, [
            'token'                 => $token,
            'email'                 => $user->email,
            'password'              => 'NewPassword1!',
            'password_confirmation' => 'NewPassword1!',
        ])->assertOk();

        $user->refresh();

        $history = DB::table('password_histories')
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->first();

        $this->assertNotNull($history);
        $this->assertTrue(Hash::check('NewPassword1!', $history->password));
    }
}
