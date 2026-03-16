<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\Auth\User;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class TokenTest extends TestCase
{
    private const LOGIN_URL         = '/api/auth/login';
    private const LOGOUT_URL        = '/api/auth/logout';
    private const ME_URL            = '/api/auth/me';
    private const REFRESH_TOKEN_URL = '/api/auth/refresh-token';

    // ─── 1. Token creation on login ───────────────────────────────────────────

    public function test_login_returns_token_with_correct_structure(): void
    {
        ['user' => $user] = $this->setUpCompanyAndUser();

        $response = $this->postJson(self::LOGIN_URL, [
            'email'    => $user->email,
            'password' => 'Password1!',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['token', 'user', 'companies']);

        $token = $response->json('token');
        $this->assertNotEmpty($token);
        $this->assertIsString($token);
    }

    public function test_login_creates_a_persisted_sanctum_token(): void
    {
        ['user' => $user] = $this->setUpCompanyAndUser();

        $this->assertDatabaseCount('personal_access_tokens', 0);

        $this->postJson(self::LOGIN_URL, [
            'email'    => $user->email,
            'password' => 'Password1!',
        ])->assertOk();

        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_login_token_has_24h_expiry(): void
    {
        ['user' => $user] = $this->setUpCompanyAndUser();

        $this->postJson(self::LOGIN_URL, [
            'email'    => $user->email,
            'password' => 'Password1!',
        ])->assertOk();

        $token = PersonalAccessToken::first();
        $this->assertNotNull($token->expires_at);

        $expectedExpiry = now()->addMinutes(1440);
        $this->assertTrue(
            $token->expires_at->between(
                $expectedExpiry->copy()->subMinute(),
                $expectedExpiry->copy()->addMinute()
            )
        );
    }

    // ─── 2. Token refresh ─────────────────────────────────────────────────────

    public function test_refresh_token_returns_new_token(): void
    {
        ['user' => $user] = $this->setUpCompanyAndUser();

        $loginResponse = $this->postJson(self::LOGIN_URL, [
            'email'    => $user->email,
            'password' => 'Password1!',
        ])->assertOk();

        $oldToken = $loginResponse->json('token');

        $refreshResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $oldToken,
            'Accept'        => 'application/json',
        ])->postJson(self::REFRESH_TOKEN_URL);

        $refreshResponse->assertOk()
            ->assertJsonStructure(['token']);

        $newToken = $refreshResponse->json('token');
        $this->assertNotEmpty($newToken);
        $this->assertNotEquals($oldToken, $newToken);
    }

    public function test_refresh_token_invalidates_old_token(): void
    {
        ['user' => $user] = $this->setUpCompanyAndUser();

        $loginResponse = $this->postJson(self::LOGIN_URL, [
            'email'    => $user->email,
            'password' => 'Password1!',
        ])->assertOk();

        $oldToken = $loginResponse->json('token');

        // Refresh — old token is revoked
        $this->withHeaders([
            'Authorization' => 'Bearer ' . $oldToken,
            'Accept'        => 'application/json',
        ])->postJson(self::REFRESH_TOKEN_URL)->assertOk();

        // Flush the Sanctum guard cache so the next request re-resolves the token from DB
        $this->app['auth']->forgetGuards();

        // Old token should now be rejected
        $this->withHeaders([
            'Authorization' => 'Bearer ' . $oldToken,
            'Accept'        => 'application/json',
        ])->getJson(self::ME_URL)->assertUnauthorized();
    }

    public function test_refresh_token_new_token_grants_access(): void
    {
        ['user' => $user] = $this->setUpCompanyAndUser();

        $loginResponse = $this->postJson(self::LOGIN_URL, [
            'email'    => $user->email,
            'password' => 'Password1!',
        ])->assertOk();

        $oldToken = $loginResponse->json('token');

        $refreshResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $oldToken,
            'Accept'        => 'application/json',
        ])->postJson(self::REFRESH_TOKEN_URL)->assertOk();

        $newToken = $refreshResponse->json('token');

        $this->withHeaders([
            'Authorization' => 'Bearer ' . $newToken,
            'Accept'        => 'application/json',
        ])->getJson(self::ME_URL)->assertOk();
    }

    // ─── 3. Token expiry ──────────────────────────────────────────────────────

    public function test_expired_token_returns_401(): void
    {
        ['user' => $user] = $this->setUpCompanyAndUser();

        // Create a token that is already expired
        $user->createToken('expired-token', ['*'], now()->subMinute());

        $expiredToken = PersonalAccessToken::where('tokenable_id', $user->id)->first();

        // Manually get the plain text token by creating a fresh one and then expiring it
        $newToken = $user->createToken('test-expired', ['*'], now()->subSecond())->plainTextToken;

        $this->withHeaders([
            'Authorization' => 'Bearer ' . $newToken,
            'Accept'        => 'application/json',
        ])->getJson(self::ME_URL)->assertUnauthorized();
    }

    // ─── 4. Valid token grants access ─────────────────────────────────────────

    public function test_valid_token_grants_access_to_protected_route(): void
    {
        ['user' => $user] = $this->setUpCompanyAndUser();

        $this->withHeaders($this->authHeaders($user))
            ->getJson(self::ME_URL)
            ->assertOk()
            ->assertJsonStructure(['data' => ['id', 'email']]);
    }

    // ─── 5. Missing token returns 401 ─────────────────────────────────────────

    public function test_accessing_protected_route_without_token_returns_401(): void
    {
        $this->withHeaders(['Accept' => 'application/json'])
            ->getJson(self::ME_URL)
            ->assertUnauthorized();
    }

    // ─── 6. Invalid / revoked token returns 401 ───────────────────────────────

    public function test_invalid_token_returns_401(): void
    {
        $this->withHeaders([
            'Authorization' => 'Bearer this-is-not-a-valid-token',
            'Accept'        => 'application/json',
        ])->getJson(self::ME_URL)->assertUnauthorized();
    }

    public function test_revoked_token_returns_401(): void
    {
        ['user' => $user] = $this->setUpCompanyAndUser();

        $plainToken = $user->createToken('revoked-token')->plainTextToken;

        // Revoke it directly in the DB
        $user->tokens()->delete();

        $this->withHeaders([
            'Authorization' => 'Bearer ' . $plainToken,
            'Accept'        => 'application/json',
        ])->getJson(self::ME_URL)->assertUnauthorized();
    }

    // ─── 7. Logout revokes token ──────────────────────────────────────────────

    public function test_logout_revokes_token(): void
    {
        ['user' => $user] = $this->setUpCompanyAndUser();

        $loginResponse = $this->postJson(self::LOGIN_URL, [
            'email'    => $user->email,
            'password' => 'Password1!',
        ])->assertOk();

        $token = $loginResponse->json('token');

        $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Accept'        => 'application/json',
        ])->postJson(self::LOGOUT_URL)->assertOk();

        // Flush the Sanctum guard cache so the next request re-resolves the token from DB
        $this->app['auth']->forgetGuards();

        // Token should now be rejected
        $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Accept'        => 'application/json',
        ])->getJson(self::ME_URL)->assertUnauthorized();
    }

    public function test_logout_removes_token_from_database(): void
    {
        ['user' => $user] = $this->setUpCompanyAndUser();

        $loginResponse = $this->postJson(self::LOGIN_URL, [
            'email'    => $user->email,
            'password' => 'Password1!',
        ])->assertOk();

        $token = $loginResponse->json('token');

        $this->assertDatabaseCount('personal_access_tokens', 1);

        $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Accept'        => 'application/json',
        ])->postJson(self::LOGOUT_URL)->assertOk();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }
}
