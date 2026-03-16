<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\Auth\User;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class LoginTest extends TestCase
{
    private const LOGIN_URL = '/api/auth/login';

    // ─── 1. Successful login ──────────────────────────────────────────────────

    public function test_successful_login_returns_token_user_and_companies(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();

        $response = $this->postJson(self::LOGIN_URL, [
            'email'    => $user->email,
            'password' => 'Password1!',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'user'  => ['id', 'email', 'first_name', 'last_name'],
                'token',
                'companies',
            ]);

        $this->assertNotEmpty($response->json('token'));
        $this->assertIsArray($response->json('companies'));
        $this->assertCount(1, $response->json('companies'));
    }

    // ─── 2. Invalid credentials ───────────────────────────────────────────────

    public function test_invalid_password_returns_422(): void
    {
        ['user' => $user] = $this->setUpCompanyAndUser();

        $response = $this->postJson(self::LOGIN_URL, [
            'email'    => $user->email,
            'password' => 'WrongPassword!',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_unknown_email_returns_422(): void
    {
        $response = $this->postJson(self::LOGIN_URL, [
            'email'    => 'nobody@example.com',
            'password' => 'Password1!',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    // ─── 3. Inactive user ─────────────────────────────────────────────────────

    public function test_inactive_user_cannot_login(): void
    {
        ['user' => $user] = $this->setUpCompanyAndUser(['is_active' => false]);

        $response = $this->postJson(self::LOGIN_URL, [
            'email'    => $user->email,
            'password' => 'Password1!',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);

        $this->assertStringContainsString(
            'deactivated',
            $response->json('errors.email.0')
        );
    }

    // ─── 4. Rate limiting (throttle middleware → 429) ─────────────────────────

    public function test_rate_limiting_returns_429_after_five_failed_attempts(): void
    {
        ['user' => $user] = $this->setUpCompanyAndUser();

        // Make 5 failed attempts to exhaust the rate limiter
        for ($i = 0; $i < 5; $i++) {
            $this->postJson(self::LOGIN_URL, [
                'email'    => $user->email,
                'password' => 'WrongPassword!',
            ]);
        }

        // The 6th attempt should be blocked by the throttle middleware
        $response = $this->postJson(self::LOGIN_URL, [
            'email'    => $user->email,
            'password' => 'WrongPassword!',
        ]);

        $response->assertStatus(429);
    }

    // ─── 5 & 6. Account lockout ───────────────────────────────────────────────

    public function test_account_lockout_after_five_failed_attempts(): void
    {
        ['user' => $user] = $this->setUpCompanyAndUser();

        // Simulate 5 failed attempts directly via the rate limiter
        // (bypassing the throttle middleware so we can test the service-level lockout)
        $key = $this->throttleKey($user->email, '127.0.0.1');
        for ($i = 0; $i < 5; $i++) {
            RateLimiter::hit($key, 15 * 60);
        }

        // Now the service itself should detect lockout and return 422 with throttle message
        $response = $this->postJson(self::LOGIN_URL, [
            'email'    => $user->email,
            'password' => 'Password1!',
        ]);

        // Either 422 (service-level lockout) or 429 (middleware throttle)
        $this->assertContains($response->status(), [422, 429]);
    }

    public function test_locked_account_returns_appropriate_error_message(): void
    {
        ['user' => $user] = $this->setUpCompanyAndUser();

        $key = $this->throttleKey($user->email, '127.0.0.1');
        for ($i = 0; $i < 5; $i++) {
            RateLimiter::hit($key, 15 * 60);
        }

        $response = $this->postJson(self::LOGIN_URL, [
            'email'    => $user->email,
            'password' => 'Password1!',
        ]);

        // 422 from service: errors.email contains throttle message
        // 429 from middleware: message field present
        if ($response->status() === 422) {
            $response->assertJsonValidationErrors(['email']);
        } else {
            $response->assertStatus(429);
        }
    }

    // ─── 7. Audit fields updated on successful login ──────────────────────────

    public function test_successful_login_updates_last_login_at_and_last_login_ip(): void
    {
        ['user' => $user] = $this->setUpCompanyAndUser();

        $this->assertNull($user->last_login_at);
        $this->assertNull($user->last_login_ip);

        $this->postJson(self::LOGIN_URL, [
            'email'    => $user->email,
            'password' => 'Password1!',
        ])->assertOk();

        $user->refresh();

        $this->assertNotNull($user->last_login_at);
        $this->assertNotNull($user->last_login_ip);
    }

    // ─── 8. Validation — required fields ─────────────────────────────────────

    public function test_login_requires_email_field(): void
    {
        $response = $this->postJson(self::LOGIN_URL, [
            'password' => 'Password1!',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_login_requires_password_field(): void
    {
        $response = $this->postJson(self::LOGIN_URL, [
            'email' => 'user@example.com',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    }

    public function test_login_requires_valid_email_format(): void
    {
        $response = $this->postJson(self::LOGIN_URL, [
            'email'    => 'not-an-email',
            'password' => 'Password1!',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Replicate the throttle key logic from AuthService.
     */
    private function throttleKey(string $email, string $ip): string
    {
        return \Illuminate\Support\Str::transliterate(
            \Illuminate\Support\Str::lower($email) . '|' . $ip
        );
    }
}
