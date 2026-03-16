<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\Auth\User;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    private const REGISTER_URL = '/api/auth/register';

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'jane.doe@example.com',
            'password' => 'Password1!',
            'password_confirmation' => 'Password1!',
        ], $overrides);
    }

    // ─── 1. Successful registration ───────────────────────────────────────────

    public function test_successful_registration_returns_201_with_user_and_token(): void
    {
        $response = $this->postJson(self::REGISTER_URL, $this->validPayload());

        $response->assertCreated()
            ->assertJsonStructure([
                'user'  => ['id', 'matricule', 'first_name', 'last_name', 'email'],
                'token',
            ]);

        $this->assertNotEmpty($response->json('token'));
    }

    // ─── 2. Matricule auto-generation ─────────────────────────────────────────

    public function test_matricule_is_auto_generated_in_correct_format(): void
    {
        $year = now()->format('Y');

        $response = $this->postJson(self::REGISTER_URL, $this->validPayload());

        $response->assertCreated();

        $matricule = $response->json('user.matricule');
        $this->assertMatchesRegularExpression('/^EMP-' . $year . '-\d{5}$/', $matricule);
    }

    public function test_matricule_is_sequential_per_year(): void
    {
        $year = now()->format('Y');

        // First registration
        $first = $this->postJson(self::REGISTER_URL, $this->validPayload([
            'email' => 'first@example.com',
        ]));
        $first->assertCreated();

        // Second registration
        $second = $this->postJson(self::REGISTER_URL, $this->validPayload([
            'email' => 'second@example.com',
        ]));
        $second->assertCreated();

        $firstMatricule  = $first->json('user.matricule');
        $secondMatricule = $second->json('user.matricule');

        // Both match the pattern
        $this->assertMatchesRegularExpression('/^EMP-' . $year . '-\d{5}$/', $firstMatricule);
        $this->assertMatchesRegularExpression('/^EMP-' . $year . '-\d{5}$/', $secondMatricule);

        // Extract sequence numbers and verify second is greater
        $firstSeq  = (int) substr($firstMatricule, -5);
        $secondSeq = (int) substr($secondMatricule, -5);
        $this->assertGreaterThan($firstSeq, $secondSeq);
    }

    // ─── 3. Validation — required fields ─────────────────────────────────────

    public function test_first_name_is_required(): void
    {
        $payload = $this->validPayload();
        unset($payload['first_name']);

        $this->postJson(self::REGISTER_URL, $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['first_name']);
    }

    public function test_last_name_is_required(): void
    {
        $payload = $this->validPayload();
        unset($payload['last_name']);

        $this->postJson(self::REGISTER_URL, $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['last_name']);
    }

    public function test_email_is_required(): void
    {
        $payload = $this->validPayload();
        unset($payload['email']);

        $this->postJson(self::REGISTER_URL, $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_password_is_required(): void
    {
        $payload = $this->validPayload();
        unset($payload['password']);
        unset($payload['password_confirmation']);

        $this->postJson(self::REGISTER_URL, $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    }

    // ─── 4. Validation — email uniqueness ────────────────────────────────────

    public function test_duplicate_email_returns_422(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->postJson(self::REGISTER_URL, $this->validPayload(['email' => 'taken@example.com']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    // ─── 5. Validation — password policy ─────────────────────────────────────

    public function test_password_must_be_at_least_8_characters(): void
    {
        $this->postJson(self::REGISTER_URL, $this->validPayload([
            'password'              => 'Ab1!',
            'password_confirmation' => 'Ab1!',
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    }

    public function test_password_must_contain_mixed_case(): void
    {
        $this->postJson(self::REGISTER_URL, $this->validPayload([
            'password'              => 'password1!',
            'password_confirmation' => 'password1!',
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    }

    public function test_password_must_contain_a_number(): void
    {
        $this->postJson(self::REGISTER_URL, $this->validPayload([
            'password'              => 'Password!',
            'password_confirmation' => 'Password!',
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    }

    public function test_password_must_contain_a_special_character(): void
    {
        $this->postJson(self::REGISTER_URL, $this->validPayload([
            'password'              => 'Password1',
            'password_confirmation' => 'Password1',
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    }

    // ─── 6. Validation — password confirmation ───────────────────────────────

    public function test_password_confirmation_must_match(): void
    {
        $this->postJson(self::REGISTER_URL, $this->validPayload([
            'password'              => 'Password1!',
            'password_confirmation' => 'Different1!',
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    }

    // ─── 7. Registered user is active by default ─────────────────────────────

    public function test_registered_user_is_active_by_default(): void
    {
        $response = $this->postJson(self::REGISTER_URL, $this->validPayload());

        $response->assertCreated();

        $this->assertTrue($response->json('user.is_active'));

        $user = User::where('email', 'jane.doe@example.com')->firstOrFail();
        $this->assertTrue($user->is_active);
    }

    // ─── 8. Response structure ────────────────────────────────────────────────

    public function test_response_includes_full_user_resource_and_token(): void
    {
        $response = $this->postJson(self::REGISTER_URL, $this->validPayload());

        $response->assertCreated()
            ->assertJsonStructure([
                'user' => [
                    'id',
                    'matricule',
                    'first_name',
                    'last_name',
                    'name',
                    'email',
                    'is_active',
                ],
                'token',
            ]);

        $this->assertEquals('Jane', $response->json('user.first_name'));
        $this->assertEquals('Doe', $response->json('user.last_name'));
        $this->assertEquals('jane.doe@example.com', $response->json('user.email'));
    }

    // ─── 9. Optional phone field ──────────────────────────────────────────────

    public function test_registration_succeeds_with_optional_phone(): void
    {
        $response = $this->postJson(self::REGISTER_URL, $this->validPayload([
            'phone' => '+212600000000',
        ]));

        $response->assertCreated();

        $user = User::where('email', 'jane.doe@example.com')->firstOrFail();
        $this->assertEquals('+212600000000', $user->phone);
    }

    public function test_registration_succeeds_without_phone(): void
    {
        $this->postJson(self::REGISTER_URL, $this->validPayload())
            ->assertCreated();
    }
}
