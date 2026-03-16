<?php

namespace Tests;

use App\Models\Company\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    /**
     * Create a Company and a User, attach the user to the company,
     * set current_company_id on the user, and return both.
     *
     * @param  array<string, mixed>  $userOverrides
     * @param  array<string, mixed>  $companyOverrides
     * @return array{user: User, company: Company}
     */
    protected function setUpCompanyAndUser(array $userOverrides = [], array $companyOverrides = []): array
    {
        $company = Company::factory()->create($companyOverrides);

        $user = User::factory()->create(array_merge([
            'current_company_id' => $company->id,
        ], $userOverrides));

        // Attach user to company via pivot
        $company->users()->attach($user->id, [
            'is_default' => true,
            'joined_at'  => now(),
        ]);

        return ['user' => $user, 'company' => $company];
    }

    /**
     * Create a Sanctum token for the given user and return auth headers.
     *
     * @return array<string, string>
     */
    protected function authHeaders(User $user): array
    {
        $token = $user->createToken('test-token')->plainTextToken;

        return [
            'Authorization' => 'Bearer ' . $token,
            'X-Company-Id'  => (string) $user->current_company_id,
            'Accept'        => 'application/json',
        ];
    }

    /**
     * Assert that userB cannot access a resource that belongs to userA's company.
     * Expects a 403 or 404 response.
     */
    protected function assertTenantIsolation(
        string $endpoint,
        User $userA,
        User $userB,
        int $resourceId
    ): void {
        $response = $this->withHeaders($this->authHeaders($userB))
            ->getJson("{$endpoint}/{$resourceId}");

        $this->assertContains(
            $response->status(),
            [403, 404],
            "Expected 403 or 404 when userB accesses userA's resource at {$endpoint}/{$resourceId}, got {$response->status()}"
        );
    }
}
