<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Company\Company;
use App\Models\User;
use App\Services\Company\CompanyService;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CompanyServiceTest extends TestCase
{
    private CompanyService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CompanyService();
    }

    // ─── create() ─────────────────────────────────────────────────────────────

    public function test_create_persists_company_to_database(): void
    {
        ['user' => $user] = $this->setUpCompanyAndUser();
        $this->actingAs($user);

        $company = $this->service->create([
            'name'     => 'Acme Corp',
            'currency' => 'MAD',
        ]);

        $this->assertDatabaseHas('companies', [
            'id'   => $company->id,
            'name' => 'Acme Corp',
        ]);
    }

    public function test_create_attaches_authenticated_user_as_default_member(): void
    {
        ['user' => $user] = $this->setUpCompanyAndUser();
        $this->actingAs($user);

        $company = $this->service->create(['name' => 'Acme Corp']);

        $this->assertDatabaseHas('company_user', [
            'company_id' => $company->id,
            'user_id'    => $user->id,
            'is_default' => true,
        ]);
    }

    public function test_create_sets_current_company_when_user_has_none(): void
    {
        $user = User::factory()->create(['current_company_id' => null]);
        $this->actingAs($user);

        $company = $this->service->create(['name' => 'First Corp']);

        $this->assertDatabaseHas('users', [
            'id'                 => $user->id,
            'current_company_id' => $company->id,
        ]);
    }

    public function test_create_does_not_overwrite_existing_current_company(): void
    {
        ['user' => $user, 'company' => $existing] = $this->setUpCompanyAndUser();
        $this->actingAs($user);

        $this->service->create(['name' => 'Second Corp']);

        // current_company_id should still point to the original company
        $this->assertDatabaseHas('users', [
            'id'                 => $user->id,
            'current_company_id' => $existing->id,
        ]);
    }

    public function test_create_returns_company_with_users_relation_loaded(): void
    {
        ['user' => $user] = $this->setUpCompanyAndUser();
        $this->actingAs($user);

        $company = $this->service->create(['name' => 'Loaded Corp']);

        $this->assertTrue($company->relationLoaded('users'));
    }

    // ─── update() ─────────────────────────────────────────────────────────────

    public function test_update_changes_company_attributes(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->actingAs($user);

        $updated = $this->service->update($company, ['name' => 'Renamed Corp']);

        $this->assertSame('Renamed Corp', $updated->name);
        $this->assertDatabaseHas('companies', [
            'id'   => $company->id,
            'name' => 'Renamed Corp',
        ]);
    }

    public function test_update_returns_fresh_company_with_users_loaded(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->actingAs($user);

        $result = $this->service->update($company, ['name' => 'Fresh Corp']);

        $this->assertTrue($result->relationLoaded('users'));
    }

    // ─── delete() ─────────────────────────────────────────────────────────────

    public function test_delete_soft_deletes_company_when_sole_member(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->actingAs($user);

        $this->service->delete($company);

        $this->assertSoftDeleted('companies', ['id' => $company->id]);
    }

    public function test_delete_detaches_sole_member_before_deleting(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->actingAs($user);

        $this->service->delete($company);

        $this->assertDatabaseMissing('company_user', [
            'company_id' => $company->id,
            'user_id'    => $user->id,
        ]);
    }

    public function test_delete_throws_when_other_users_are_still_members(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->actingAs($user);

        $otherUser = User::factory()->create();
        $company->users()->attach($otherUser->id, ['is_default' => false, 'joined_at' => now()]);

        $this->expectException(ValidationException::class);

        $this->service->delete($company);
    }

    public function test_delete_does_not_hard_delete_company(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->actingAs($user);

        $this->service->delete($company);

        $this->assertDatabaseHas('companies', ['id' => $company->id]);
    }

    // ─── addUser() ────────────────────────────────────────────────────────────

    public function test_add_user_attaches_user_to_company(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->actingAs($user);

        $newUser = User::factory()->create();
        $this->service->addUser($company, $newUser);

        $this->assertDatabaseHas('company_user', [
            'company_id' => $company->id,
            'user_id'    => $newUser->id,
        ]);
    }

    public function test_add_user_sets_is_default_flag(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->actingAs($user);

        $newUser = User::factory()->create();
        $this->service->addUser($company, $newUser, isDefault: true);

        $this->assertDatabaseHas('company_user', [
            'company_id' => $company->id,
            'user_id'    => $newUser->id,
            'is_default' => true,
        ]);
    }

    public function test_add_user_sets_current_company_when_user_has_none(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->actingAs($user);

        $newUser = User::factory()->create(['current_company_id' => null]);
        $this->service->addUser($company, $newUser);

        $this->assertDatabaseHas('users', [
            'id'                 => $newUser->id,
            'current_company_id' => $company->id,
        ]);
    }

    public function test_add_user_does_not_overwrite_existing_current_company(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        ['company' => $otherCompany] = $this->setUpCompanyAndUser();
        $this->actingAs($user);

        $newUser = User::factory()->create(['current_company_id' => $otherCompany->id]);
        $this->service->addUser($company, $newUser);

        $this->assertDatabaseHas('users', [
            'id'                 => $newUser->id,
            'current_company_id' => $otherCompany->id,
        ]);
    }

    public function test_add_user_throws_when_user_is_already_a_member(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->actingAs($user);

        $this->expectException(ValidationException::class);

        $this->service->addUser($company, $user);
    }

    // ─── removeUser() ─────────────────────────────────────────────────────────

    public function test_remove_user_detaches_user_from_company(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->actingAs($user);

        $member = User::factory()->create(['current_company_id' => $company->id]);
        $company->users()->attach($member->id, ['is_default' => false, 'joined_at' => now()]);

        $this->service->removeUser($company, $member);

        $this->assertDatabaseMissing('company_user', [
            'company_id' => $company->id,
            'user_id'    => $member->id,
        ]);
    }

    public function test_remove_user_clears_current_company_when_it_was_the_active_one(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->actingAs($user);

        $member = User::factory()->create(['current_company_id' => $company->id]);
        $company->users()->attach($member->id, ['is_default' => false, 'joined_at' => now()]);

        $this->service->removeUser($company, $member);

        $this->assertDatabaseHas('users', [
            'id'                 => $member->id,
            'current_company_id' => null,
        ]);
    }

    public function test_remove_user_switches_to_next_company_when_available(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->actingAs($user);

        // Member belongs to two companies; current is $company
        $secondCompany = Company::factory()->create();
        $member = User::factory()->create(['current_company_id' => $company->id]);
        $company->users()->attach($member->id, ['is_default' => false, 'joined_at' => now()]);
        $secondCompany->users()->attach($member->id, ['is_default' => false, 'joined_at' => now()]);

        $this->service->removeUser($company, $member);

        $member->refresh();
        $this->assertSame($secondCompany->id, $member->current_company_id);
    }

    public function test_remove_user_throws_when_user_is_not_a_member(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->actingAs($user);

        $nonMember = User::factory()->create();

        $this->expectException(ValidationException::class);

        $this->service->removeUser($company, $nonMember);
    }

    // ─── switchCompany() ──────────────────────────────────────────────────────

    public function test_switch_company_updates_current_company_id(): void
    {
        ['user' => $user, 'company' => $first] = $this->setUpCompanyAndUser();

        $second = Company::factory()->create();
        $second->users()->attach($user->id, ['is_default' => false, 'joined_at' => now()]);

        $this->service->switchCompany($user, $second->id);

        $this->assertDatabaseHas('users', [
            'id'                 => $user->id,
            'current_company_id' => $second->id,
        ]);
    }

    public function test_switch_company_returns_the_target_company(): void
    {
        ['user' => $user, 'company' => $first] = $this->setUpCompanyAndUser();

        $second = Company::factory()->create();
        $second->users()->attach($user->id, ['is_default' => false, 'joined_at' => now()]);

        $result = $this->service->switchCompany($user, $second->id);

        $this->assertSame($second->id, $result->id);
    }

    public function test_switch_company_throws_when_user_does_not_belong_to_target(): void
    {
        ['user' => $user] = $this->setUpCompanyAndUser();

        $unrelated = Company::factory()->create();

        $this->expectException(ValidationException::class);

        $this->service->switchCompany($user, $unrelated->id);
    }

    // ─── getUserCompanies() ───────────────────────────────────────────────────

    public function test_get_user_companies_returns_all_companies_for_user(): void
    {
        ['user' => $user, 'company' => $first] = $this->setUpCompanyAndUser();

        $second = Company::factory()->create();
        $second->users()->attach($user->id, ['is_default' => false, 'joined_at' => now()]);

        $companies = $this->service->getUserCompanies($user);

        $this->assertCount(2, $companies);
        $this->assertTrue($companies->contains('id', $first->id));
        $this->assertTrue($companies->contains('id', $second->id));
    }

    public function test_get_user_companies_includes_pivot_data(): void
    {
        ['user' => $user] = $this->setUpCompanyAndUser();

        $companies = $this->service->getUserCompanies($user);

        $pivot = $companies->first()->pivot;
        $this->assertNotNull($pivot->is_default);
        $this->assertNotNull($pivot->joined_at);
    }

    public function test_get_user_companies_returns_empty_collection_for_user_with_no_companies(): void
    {
        $user = User::factory()->create(['current_company_id' => null]);

        $companies = $this->service->getUserCompanies($user);

        $this->assertCount(0, $companies);
    }
}
