<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Auth\User;
use App\Models\Company\Company;
use App\Services\Auth\AuthService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AuthServiceTest extends TestCase
{
    private AuthService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AuthService();
    }

    // ─── register() ───────────────────────────────────────────────────────────

    public function test_register_creates_user_in_database(): void
    {
        $this->service->register([
            'first_name' => 'Jane',
            'last_name'  => 'Doe',
            'email'      => 'jane@example.com',
            'password'   => 'Password1!',
        ]);

        $this->assertDatabaseHas('users', ['email' => 'jane@example.com']);
    }

    public function test_register_generates_matricule_in_correct_format(): void
    {
        $year = now()->format('Y');

        $result = $this->service->register([
            'first_name' => 'Jane',
            'last_name'  => 'Doe',
            'email'      => 'jane@example.com',
            'password'   => 'Password1!',
        ]);

        $this->assertMatchesRegularExpression(
            '/^EMP-' . $year . '-\d{5}$/',
            $result['user']->matricule
        );
    }

    public function test_register_returns_user_and_token(): void
    {
        $result = $this->service->register([
            'first_name' => 'Jane',
            'last_name'  => 'Doe',
            'email'      => 'jane@example.com',
            'password'   => 'Password1!',
        ]);

        $this->assertArrayHasKey('user', $result);
        $this->assertArrayHasKey('token', $result);
        $this->assertInstanceOf(User::class, $result['user']);
        $this->assertNotEmpty($result['token']);
    }

    public function test_register_seeds_password_history(): void
    {
        $result = $this->service->register([
            'first_name' => 'Jane',
            'last_name'  => 'Doe',
            'email'      => 'jane@example.com',
            'password'   => 'Password1!',
        ]);

        $this->assertDatabaseHas('password_histories', [
            'user_id' => $result['user']->id,
        ]);
    }

    public function test_register_sets_user_as_active(): void
    {
        $result = $this->service->register([
            'first_name' => 'Jane',
            'last_name'  => 'Doe',
            'email'      => 'jane@example.com',
            'password'   => 'Password1!',
        ]);

        $this->assertTrue($result['user']->is_active);
    }

    public function test_register_stores_optional_phone(): void
    {
        $result = $this->service->register([
            'first_name' => 'Jane',
            'last_name'  => 'Doe',
            'email'      => 'jane@example.com',
            'password'   => 'Password1!',
            'phone'      => '+212600000000',
        ]);

        $this->assertSame('+212600000000', $result['user']->phone);
    }

    // ─── login() ──────────────────────────────────────────────────────────────

    public function test_login_with_valid_credentials_returns_user_token_and_companies(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();

        $result = $this->service->login([
            'email'    => $user->email,
            'password' => 'Password1!',
            'ip'       => '127.0.0.1',
        ]);

        $this->assertArrayHasKey('user', $result);
        $this->assertArrayHasKey('token', $result);
        $this->assertArrayHasKey('companies', $result);
        $this->assertNotEmpty($result['token']);
        $this->assertSame($user->id, $result['user']->id);
        $this->assertCount(1, $result['companies']);
    }

    public function test_login_with_invalid_password_throws_validation_exception(): void
    {
        ['user' => $user] = $this->setUpCompanyAndUser();

        $this->expectException(ValidationException::class);

        $this->service->login([
            'email'    => $user->email,
            'password' => 'WrongPassword!',
            'ip'       => '127.0.0.1',
        ]);
    }

    public function test_login_with_unknown_email_throws_validation_exception(): void
    {
        $this->expectException(ValidationException::class);

        $this->service->login([
            'email'    => 'nobody@example.com',
            'password' => 'Password1!',
            'ip'       => '127.0.0.1',
        ]);
    }

    public function test_login_with_inactive_account_throws_validation_exception(): void
    {
        ['user' => $user] = $this->setUpCompanyAndUser(['is_active' => false]);

        $this->expectException(ValidationException::class);

        $this->service->login([
            'email'    => $user->email,
            'password' => 'Password1!',
            'ip'       => '127.0.0.1',
        ]);
    }

    public function test_login_with_inactive_account_error_mentions_deactivated(): void
    {
        ['user' => $user] = $this->setUpCompanyAndUser(['is_active' => false]);

        try {
            $this->service->login([
                'email'    => $user->email,
                'password' => 'Password1!',
                'ip'       => '127.0.0.1',
            ]);
            $this->fail('Expected ValidationException was not thrown.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('deactivated', $e->errors()['email'][0]);
        }
    }

    public function test_login_with_locked_account_throws_validation_exception(): void
    {
        ['user' => $user] = $this->setUpCompanyAndUser();

        $key = $this->throttleKey($user->email, '127.0.0.1');
        for ($i = 0; $i < 5; $i++) {
            RateLimiter::hit($key, 15 * 60);
        }

        $this->expectException(ValidationException::class);

        $this->service->login([
            'email'    => $user->email,
            'password' => 'Password1!',
            'ip'       => '127.0.0.1',
        ]);
    }

    public function test_login_updates_last_login_at_and_ip_on_success(): void
    {
        ['user' => $user] = $this->setUpCompanyAndUser();

        $this->assertNull($user->last_login_at);

        $this->service->login([
            'email'    => $user->email,
            'password' => 'Password1!',
            'ip'       => '192.168.1.1',
        ]);

        $user->refresh();
        $this->assertNotNull($user->last_login_at);
        $this->assertSame('192.168.1.1', $user->last_login_ip);
    }

    public function test_login_records_failed_attempt_in_login_attempts_table(): void
    {
        ['user' => $user] = $this->setUpCompanyAndUser();

        try {
            $this->service->login([
                'email'    => $user->email,
                'password' => 'WrongPassword!',
                'ip'       => '127.0.0.1',
            ]);
        } catch (ValidationException) {
            // expected
        }

        $this->assertDatabaseHas('login_attempts', [
            'email'          => $user->email,
            'was_successful' => false,
        ]);
    }

    public function test_login_records_successful_attempt_in_login_attempts_table(): void
    {
        ['user' => $user] = $this->setUpCompanyAndUser();

        $this->service->login([
            'email'    => $user->email,
            'password' => 'Password1!',
            'ip'       => '127.0.0.1',
        ]);

        $this->assertDatabaseHas('login_attempts', [
            'email'          => $user->email,
            'was_successful' => true,
        ]);
    }

    // ─── logout() ─────────────────────────────────────────────────────────────

    public function test_logout_revokes_current_token(): void
    {
        ['user' => $user] = $this->setUpCompanyAndUser();

        // Create a token and set it on the user model directly (as Sanctum does)
        $tokenResult = $user->createToken('test');
        $user->withAccessToken($tokenResult->accessToken);

        $this->assertCount(1, $user->tokens);

        $this->service->logout($user);

        $this->assertCount(0, $user->fresh()->tokens);
    }

    // ─── changePassword() ─────────────────────────────────────────────────────

    public function test_change_password_succeeds_with_valid_data(): void
    {
        ['user' => $user] = $this->setUpCompanyAndUser();

        // Seed password history for the current password
        DB::table('password_histories')->insert([
            'user_id'    => $user->id,
            'password'   => $user->password,
            'created_at' => now(),
        ]);

        $this->service->changePassword($user, [
            'current_password' => 'Password1!',
            'password'         => 'NewPassword2@',
        ]);

        $user->refresh();
        $this->assertTrue(Hash::check('NewPassword2@', $user->password));
    }

    public function test_change_password_fails_when_current_password_is_wrong(): void
    {
        ['user' => $user] = $this->setUpCompanyAndUser();

        $this->expectException(ValidationException::class);

        $this->service->changePassword($user, [
            'current_password' => 'WrongPassword!',
            'password'         => 'NewPassword2@',
        ]);
    }

    public function test_change_password_fails_when_reusing_recent_password(): void
    {
        ['user' => $user] = $this->setUpCompanyAndUser();

        // Seed the current password into history
        DB::table('password_histories')->insert([
            'user_id'    => $user->id,
            'password'   => $user->password,
            'created_at' => now(),
        ]);

        $this->expectException(ValidationException::class);

        $this->service->changePassword($user, [
            'current_password' => 'Password1!',
            'password'         => 'Password1!', // same as current
        ]);
    }

    public function test_change_password_prevents_reuse_of_last_5_passwords(): void
    {
        ['user' => $user] = $this->setUpCompanyAndUser();

        // Build exactly 5 history entries (oldest → newest).
        // The password we'll try to reuse is entry #1 (oldest of the 5).
        $targetPassword = 'OldPassword3#';

        $histories = [
            ['password' => Hash::make($targetPassword), 'offset' => 4],
            ['password' => Hash::make('Filler1Pass!'),  'offset' => 3],
            ['password' => Hash::make('Filler2Pass!'),  'offset' => 2],
            ['password' => Hash::make('Filler3Pass!'),  'offset' => 1],
            ['password' => $user->password,             'offset' => 0], // current
        ];

        foreach ($histories as $entry) {
            DB::table('password_histories')->insert([
                'user_id'    => $user->id,
                'password'   => $entry['password'],
                'created_at' => now()->subMinutes($entry['offset']),
            ]);
        }

        $this->expectException(ValidationException::class);

        $this->service->changePassword($user, [
            'current_password' => 'Password1!',
            'password'         => $targetPassword,
        ]);
    }

    public function test_change_password_revokes_all_tokens(): void
    {
        ['user' => $user] = $this->setUpCompanyAndUser();

        $user->createToken('token-1');
        $user->createToken('token-2');

        DB::table('password_histories')->insert([
            'user_id'    => $user->id,
            'password'   => $user->password,
            'created_at' => now(),
        ]);

        $this->service->changePassword($user, [
            'current_password' => 'Password1!',
            'password'         => 'NewPassword2@',
        ]);

        $this->assertCount(0, $user->fresh()->tokens);
    }

    // ─── forgotPassword() ─────────────────────────────────────────────────────

    public function test_forgot_password_sends_reset_link_for_existing_email(): void
    {
        ['user' => $user] = $this->setUpCompanyAndUser();

        Password::shouldReceive('sendResetLink')
            ->once()
            ->with(['email' => $user->email])
            ->andReturn(Password::RESET_LINK_SENT);

        // Should not throw
        $this->service->forgotPassword($user->email);
        $this->assertTrue(true);
    }

    public function test_forgot_password_throws_for_non_existent_email(): void
    {
        Password::shouldReceive('sendResetLink')
            ->once()
            ->andReturn(Password::INVALID_USER);

        $this->expectException(ValidationException::class);

        $this->service->forgotPassword('nobody@example.com');
    }

    // ─── resetPassword() ──────────────────────────────────────────────────────

    public function test_reset_password_with_valid_token_updates_password(): void
    {
        ['user' => $user] = $this->setUpCompanyAndUser();

        // Seed password history so assertNotRecentPassword can run
        DB::table('password_histories')->insert([
            'user_id'    => $user->id,
            'password'   => $user->password,
            'created_at' => now(),
        ]);

        $token = Password::createToken($user);

        $this->service->resetPassword([
            'email'                 => $user->email,
            'password'              => 'ResetPassword3#',
            'password_confirmation' => 'ResetPassword3#',
            'token'                 => $token,
        ]);

        $user->refresh();
        $this->assertTrue(Hash::check('ResetPassword3#', $user->password));
    }

    public function test_reset_password_with_invalid_token_throws_validation_exception(): void
    {
        ['user' => $user] = $this->setUpCompanyAndUser();

        $this->expectException(ValidationException::class);

        $this->service->resetPassword([
            'email'                 => $user->email,
            'password'              => 'ResetPassword3#',
            'password_confirmation' => 'ResetPassword3#',
            'token'                 => 'invalid-token',
        ]);
    }

    public function test_reset_password_revokes_all_tokens(): void
    {
        ['user' => $user] = $this->setUpCompanyAndUser();

        $user->createToken('existing-token');

        DB::table('password_histories')->insert([
            'user_id'    => $user->id,
            'password'   => $user->password,
            'created_at' => now(),
        ]);

        $token = Password::createToken($user);

        $this->service->resetPassword([
            'email'                 => $user->email,
            'password'              => 'ResetPassword3#',
            'password_confirmation' => 'ResetPassword3#',
            'token'                 => $token,
        ]);

        $this->assertCount(0, $user->fresh()->tokens);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function throttleKey(string $email, string $ip): string
    {
        return \Illuminate\Support\Str::transliterate(
            \Illuminate\Support\Str::lower($email) . '|' . $ip
        );
    }
}
