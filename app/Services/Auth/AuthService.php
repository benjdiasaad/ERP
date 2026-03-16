<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\Auth\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthService
{
    private const MAX_LOGIN_ATTEMPTS = 5;
    private const LOCKOUT_MINUTES    = 15;
    private const PASSWORD_HISTORY   = 5;

    // ─── Register ─────────────────────────────────────────────────────────────

    /**
     * Register a new user and return the user with a fresh Sanctum token.
     *
     * @param  array{first_name: string, last_name: string, email: string, password: string, phone?: string} $data
     * @return array{user: User, token: string}
     */
    public function register(array $data): array
    {
        return DB::transaction(function () use ($data): array {
            $user = User::create([
                'first_name'          => $data['first_name'],
                'last_name'           => $data['last_name'],
                'name'                => $data['first_name'] . ' ' . $data['last_name'],
                'email'               => $data['email'],
                'password'            => $data['password'], // cast hashes automatically
                'phone'               => $data['phone'] ?? null,
                'is_active'           => true,
                'password_changed_at' => now(),
            ]);

            // Seed password history
            DB::table('password_histories')->insert([
                'user_id'    => $user->id,
                'password'   => $user->getAuthPassword(),
                'created_at' => now(),
            ]);

            $token = $user->createToken('auth_token', ['*'], now()->addMinutes(config('sanctum.expiration', 1440)))->plainTextToken;

            return ['user' => $user->fresh(), 'token' => $token];
        });
    }

    // ─── Login ────────────────────────────────────────────────────────────────

    /**
     * Authenticate a user and return a Sanctum token.
     *
     * Checks rate limiting, account lockout, and active status.
     *
     * @param  array{email: string, password: string, ip: string} $data
     * @return array{user: User, token: string, companies: \Illuminate\Database\Eloquent\Collection}
     *
     * @throws ValidationException on invalid credentials, lockout, or inactive account.
     */
    public function login(array $data): array
    {
        $email = $data['email'];
        $ip    = $data['ip'];
        $key   = $this->throttleKey($email, $ip);

        // Check if locked out
        if (RateLimiter::tooManyAttempts($key, self::MAX_LOGIN_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($key);
            throw ValidationException::withMessages([
                'email' => [trans('auth.throttle', ['seconds' => $seconds, 'minutes' => ceil($seconds / 60)])],
            ]);
        }

        $user = User::where('email', $email)->first();

        if (!$user || !Hash::check($data['password'], $user->password)) {
            RateLimiter::hit($key, self::LOCKOUT_MINUTES * 60);

            DB::table('login_attempts')->insert([
                'ip_address'     => $ip,
                'email'          => $email,
                'attempted_at'   => now(),
                'was_successful' => false,
            ]);

            throw ValidationException::withMessages([
                'email' => [trans('auth.failed')],
            ]);
        }

        if (!$user->is_active) {
            throw ValidationException::withMessages([
                'email' => ['Your account has been deactivated. Please contact an administrator.'],
            ]);
        }

        // Successful login — clear rate limiter and log attempt
        RateLimiter::clear($key);

        DB::table('login_attempts')->insert([
            'ip_address'     => $ip,
            'email'          => $email,
            'attempted_at'   => now(),
            'was_successful' => true,
        ]);

        $user->update([
            'last_login_at' => now(),
            'last_login_ip' => $ip,
        ]);

        $token = $user->createToken('auth_token', ['*'], now()->addMinutes(config('sanctum.expiration', 1440)))->plainTextToken;

        return [
            'user'      => $user->fresh(),
            'token'     => $token,
            'companies' => $user->companies()->withPivot(['is_default', 'joined_at'])->get(),
        ];
    }

    // ─── Logout ───────────────────────────────────────────────────────────────

    /**
     * Revoke the current access token.
     */
    public function logout(User $user): void
    {
        $user->currentAccessToken()->delete();
    }

    // ─── Refresh Token ────────────────────────────────────────────────────────

    /**
     * Revoke the current token and issue a fresh one.
     *
     * @return array{token: string}
     */
    public function refreshToken(User $user): array
    {
        $user->currentAccessToken()->delete();

        $token = $user->createToken('auth_token', ['*'], now()->addMinutes(config('sanctum.expiration', 1440)))->plainTextToken;

        return ['token' => $token];
    }

    // ─── Change Password ──────────────────────────────────────────────────────

    /**
     * Change the authenticated user's password.
     *
     * Validates current password and checks against the last N passwords.
     *
     * @param  array{current_password: string, password: string} $data
     *
     * @throws ValidationException on wrong current password or reused password.
     */
    public function changePassword(User $user, array $data): void
    {
        if (!Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['The current password is incorrect.'],
            ]);
        }

        $this->assertNotRecentPassword($user, $data['password']);

        DB::transaction(function () use ($user, $data): void {
            $user->update([
                'password'            => $data['password'],
                'password_changed_at' => now(),
            ]);

            DB::table('password_histories')->insert([
                'user_id'    => $user->id,
                'password'   => $user->getAuthPassword(),
                'created_at' => now(),
            ]);

            // Revoke all tokens to force re-login
            $user->tokens()->delete();
        });
    }

    // ─── Forgot Password ──────────────────────────────────────────────────────

    /**
     * Send a password reset link to the given email address.
     *
     * @throws ValidationException if the email is not found or the broker throttles the request.
     */
    public function forgotPassword(string $email): void
    {
        $status = Password::sendResetLink(['email' => $email]);

        if ($status !== Password::RESET_LINK_SENT) {
            throw ValidationException::withMessages([
                'email' => [trans($status)],
            ]);
        }
    }

    // ─── Reset Password ───────────────────────────────────────────────────────

    /**
     * Reset the user's password using the broker token.
     *
     * @param  array{email: string, password: string, token: string} $data
     *
     * @throws ValidationException if the token is invalid or expired.
     */
    public function resetPassword(array $data): void
    {
        $status = Password::reset(
            $data,
            function (User $user, string $password): void {
                $this->assertNotRecentPassword($user, $password);

                DB::transaction(function () use ($user, $password): void {
                    $user->forceFill([
                        'password'            => Hash::make($password),
                        'remember_token'      => Str::random(60),
                        'password_changed_at' => now(),
                    ])->save();

                    DB::table('password_histories')->insert([
                        'user_id'    => $user->id,
                        'password'   => $user->password,
                        'created_at' => now(),
                    ]);

                    $user->tokens()->delete();
                });

                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => [trans($status)],
            ]);
        }
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Build the rate-limiter key for login throttling.
     */
    private function throttleKey(string $email, string $ip): string
    {
        return Str::transliterate(Str::lower($email) . '|' . $ip);
    }

    /**
     * Assert the given plain-text password has not been used recently.
     *
     * @throws ValidationException if the password matches one of the last N hashes.
     */
    private function assertNotRecentPassword(User $user, string $plainPassword): void
    {
        $recentHashes = DB::table('password_histories')
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->limit(self::PASSWORD_HISTORY)
            ->pluck('password');

        foreach ($recentHashes as $hash) {
            if (Hash::check($plainPassword, $hash)) {
                throw ValidationException::withMessages([
                    'password' => ['You cannot reuse any of your last ' . self::PASSWORD_HISTORY . ' passwords.'],
                ]);
            }
        }
    }
}
