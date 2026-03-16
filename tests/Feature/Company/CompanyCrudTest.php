<?php

declare(strict_types=1);

namespace Tests\Feature\Company;

use App\Models\Company\Company;
use App\Models\Auth\User;
use Tests\TestCase;

class CompanyCrudTest extends TestCase
{
    // ─── Index ────────────────────────────────────────────────────────────────

    public function test_authenticated_user_can_list_their_companies(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/companies');

        $response->assertOk()
            ->assertJsonFragment(['id' => $company->id]);
    }

    public function test_index_only_returns_companies_the_user_belongs_to(): void
    {
        ['user' => $user] = $this->setUpCompanyAndUser();

        // A company the user does NOT belong to
        Company::factory()->create(['name' => 'Other Corp']);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/companies');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }

    public function test_unauthenticated_user_cannot_list_companies(): void
    {
        $this->getJson('/api/companies')
            ->assertUnauthorized();
    }

    // ─── Show ─────────────────────────────────────────────────────────────────

    public function test_user_can_view_a_company_they_belong_to(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson("/api/companies/{$company->id}");

        $response->assertOk()
            ->assertJsonFragment([
                'id'   => $company->id,
                'name' => $company->name,
            ]);
    }

    public function test_user_cannot_view_a_company_they_do_not_belong_to(): void
    {
        ['user' => $user] = $this->setUpCompanyAndUser();

        $otherCompany = Company::factory()->create();

        $this->withHeaders($this->authHeaders($user))
            ->getJson("/api/companies/{$otherCompany->id}")
            ->assertStatus(403);
    }

    // ─── Store ────────────────────────────────────────────────────────────────

    public function test_user_can_create_a_company(): void
    {
        ['user' => $user] = $this->setUpCompanyAndUser();

        $payload = [
            'name'     => 'New Company SA',
            'email'    => 'contact@newcompany.com',
            'currency' => 'MAD',
        ];

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/companies', $payload);

        $response->assertCreated()
            ->assertJsonFragment(['name' => 'New Company SA']);

        $this->assertDatabaseHas('companies', ['name' => 'New Company SA']);
    }

    public function test_store_requires_name(): void
    {
        ['user' => $user] = $this->setUpCompanyAndUser();

        $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/companies', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_store_validates_currency_length(): void
    {
        ['user' => $user] = $this->setUpCompanyAndUser();

        $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/companies', [
                'name'     => 'Test Co',
                'currency' => 'TOOLONG',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['currency']);
    }

    public function test_creating_company_attaches_creator_as_member(): void
    {
        ['user' => $user] = $this->setUpCompanyAndUser();

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/companies', ['name' => 'Creator Corp']);

        $response->assertCreated();

        $newCompanyId = $response->json('data.id');
        $this->assertDatabaseHas('company_user', [
            'user_id'    => $user->id,
            'company_id' => $newCompanyId,
        ]);
    }

    // ─── Update ───────────────────────────────────────────────────────────────

    public function test_user_can_update_a_company_they_belong_to(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();

        $response = $this->withHeaders($this->authHeaders($user))
            ->putJson("/api/companies/{$company->id}", [
                'name' => 'Updated Name',
            ]);

        $response->assertOk()
            ->assertJsonFragment(['name' => 'Updated Name']);

        $this->assertDatabaseHas('companies', [
            'id'   => $company->id,
            'name' => 'Updated Name',
        ]);
    }

    public function test_user_cannot_update_a_company_they_do_not_belong_to(): void
    {
        ['user' => $user] = $this->setUpCompanyAndUser();

        $otherCompany = Company::factory()->create();

        $this->withHeaders($this->authHeaders($user))
            ->putJson("/api/companies/{$otherCompany->id}", ['name' => 'Hacked'])
            ->assertStatus(403);
    }

    public function test_update_validates_currency_size(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();

        $this->withHeaders($this->authHeaders($user))
            ->putJson("/api/companies/{$company->id}", ['currency' => 'TOOLONG'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['currency']);
    }

    // ─── Destroy ──────────────────────────────────────────────────────────────

    public function test_user_can_delete_a_company_with_no_other_members(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();

        // User is the sole member — service auto-detaches them and soft-deletes
        $this->withHeaders($this->authHeaders($user))
            ->deleteJson("/api/companies/{$company->id}")
            ->assertNoContent();

        $this->assertSoftDeleted('companies', ['id' => $company->id]);
    }

    public function test_cannot_delete_company_that_still_has_other_users(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();

        // Attach a second user so the company has other members
        $otherUser = User::factory()->create();
        $company->users()->attach($otherUser->id, ['is_default' => false, 'joined_at' => now()]);

        $this->withHeaders($this->authHeaders($user))
            ->deleteJson("/api/companies/{$company->id}")
            ->assertUnprocessable();
    }

    public function test_user_cannot_delete_a_company_they_do_not_belong_to(): void
    {
        ['user' => $user] = $this->setUpCompanyAndUser();

        $otherCompany = Company::factory()->create();

        $this->withHeaders($this->authHeaders($user))
            ->deleteJson("/api/companies/{$otherCompany->id}")
            ->assertStatus(403);
    }

    // ─── Add User ─────────────────────────────────────────────────────────────

    public function test_can_add_a_user_to_a_company(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();

        $newUser = User::factory()->create();

        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/companies/{$company->id}/users", [
                'user_id' => $newUser->id,
            ])
            ->assertOk();

        $this->assertDatabaseHas('company_user', [
            'company_id' => $company->id,
            'user_id'    => $newUser->id,
        ]);
    }

    public function test_cannot_add_a_user_who_is_already_a_member(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();

        // $user is already a member
        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/companies/{$company->id}/users", [
                'user_id' => $user->id,
            ])
            ->assertUnprocessable();
    }

    public function test_add_user_requires_valid_user_id(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();

        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/companies/{$company->id}/users", [
                'user_id' => 99999,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['user_id']);
    }

    // ─── Remove User ──────────────────────────────────────────────────────────

    public function test_can_remove_a_user_from_a_company(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();

        $memberUser = User::factory()->create(['current_company_id' => $company->id]);
        $company->users()->attach($memberUser->id, [
            'is_default' => false,
            'joined_at'  => now(),
        ]);

        $this->withHeaders($this->authHeaders($user))
            ->deleteJson("/api/companies/{$company->id}/users/{$memberUser->id}")
            ->assertOk();

        $this->assertDatabaseMissing('company_user', [
            'company_id' => $company->id,
            'user_id'    => $memberUser->id,
        ]);
    }

    public function test_cannot_remove_a_user_who_is_not_a_member(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();

        $nonMember = User::factory()->create();

        $this->withHeaders($this->authHeaders($user))
            ->deleteJson("/api/companies/{$company->id}/users/{$nonMember->id}")
            ->assertUnprocessable();
    }

    // ─── Switch Company ───────────────────────────────────────────────────────

    public function test_user_can_switch_to_a_company_they_belong_to(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();

        // Create and attach a second company
        $secondCompany = Company::factory()->create();
        $secondCompany->users()->attach($user->id, [
            'is_default' => false,
            'joined_at'  => now(),
        ]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/companies/{$secondCompany->id}/switch");

        $response->assertOk()
            ->assertJsonFragment(['id' => $secondCompany->id]);

        $this->assertDatabaseHas('users', [
            'id'                 => $user->id,
            'current_company_id' => $secondCompany->id,
        ]);
    }

    public function test_user_cannot_switch_to_a_company_they_do_not_belong_to(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();

        $otherCompany = Company::factory()->create();

        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/companies/{$otherCompany->id}/switch")
            ->assertStatus(403);
    }

    // ─── Tenant Isolation ─────────────────────────────────────────────────────

    public function test_user_from_company_b_cannot_view_company_a(): void
    {
        ['user' => $userA, 'company' => $companyA] = $this->setUpCompanyAndUser();
        ['user' => $userB] = $this->setUpCompanyAndUser();

        // userB tries to access companyA's detail
        $this->withHeaders($this->authHeaders($userB))
            ->getJson("/api/companies/{$companyA->id}")
            ->assertStatus(403);
    }

    public function test_user_from_company_b_cannot_update_company_a(): void
    {
        ['user' => $userA, 'company' => $companyA] = $this->setUpCompanyAndUser();
        ['user' => $userB] = $this->setUpCompanyAndUser();

        $this->withHeaders($this->authHeaders($userB))
            ->putJson("/api/companies/{$companyA->id}", ['name' => 'Hijacked'])
            ->assertStatus(403);
    }

    public function test_user_from_company_b_cannot_delete_company_a(): void
    {
        ['user' => $userA, 'company' => $companyA] = $this->setUpCompanyAndUser();
        ['user' => $userB] = $this->setUpCompanyAndUser();

        $this->withHeaders($this->authHeaders($userB))
            ->deleteJson("/api/companies/{$companyA->id}")
            ->assertStatus(403);
    }

    public function test_user_from_company_b_cannot_add_users_to_company_a(): void
    {
        ['user' => $userA, 'company' => $companyA] = $this->setUpCompanyAndUser();
        ['user' => $userB] = $this->setUpCompanyAndUser();

        $newUser = User::factory()->create();

        $this->withHeaders($this->authHeaders($userB))
            ->postJson("/api/companies/{$companyA->id}/users", [
                'user_id' => $newUser->id,
            ])
            ->assertStatus(403);
    }

    public function test_user_from_company_b_cannot_remove_users_from_company_a(): void
    {
        ['user' => $userA, 'company' => $companyA] = $this->setUpCompanyAndUser();
        ['user' => $userB] = $this->setUpCompanyAndUser();

        $this->withHeaders($this->authHeaders($userB))
            ->deleteJson("/api/companies/{$companyA->id}/users/{$userA->id}")
            ->assertStatus(403);
    }

    public function test_index_does_not_leak_companies_across_tenants(): void
    {
        ['user' => $userA, 'company' => $companyA] = $this->setUpCompanyAndUser();
        ['user' => $userB, 'company' => $companyB] = $this->setUpCompanyAndUser();

        // UserA sees only their company
        $responseA = $this->withHeaders($this->authHeaders($userA))
            ->getJson('/api/companies');

        $responseA->assertOk();
        $idsA = collect($responseA->json('data'))->pluck('id')->toArray();
        $this->assertContains($companyA->id, $idsA);
        $this->assertNotContains($companyB->id, $idsA);

        // Reset auth state between requests
        $this->app->make('auth')->forgetGuards();

        // UserB sees only their company
        $responseB = $this->withHeaders($this->authHeaders($userB))
            ->getJson('/api/companies');

        $responseB->assertOk();
        $idsB = collect($responseB->json('data'))->pluck('id')->toArray();
        $this->assertContains($companyB->id, $idsB);
        $this->assertNotContains($companyA->id, $idsB);
    }
}
