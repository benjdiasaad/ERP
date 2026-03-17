<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Auth\User;
use App\Models\Personnel\Personnel;
use App\Services\Personnel\PersonnelService;
use Tests\TestCase;

class PersonnelServiceTest extends TestCase
{
    private PersonnelService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PersonnelService();
    }

    // ─── Matricule generation ─────────────────────────────────────────────────

    public function test_generated_matricule_matches_expected_format(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->actingAs($user);

        $personnel = $this->service->create([
            'company_id' => $company->id,
            'first_name' => 'Alice',
            'last_name'  => 'Dupont',
            'hire_date'  => now()->toDateString(),
        ]);

        $year = now()->year;
        $this->assertMatchesRegularExpression(
            '/^EMP-' . $year . '-\d{5}$/',
            $personnel->matricule
        );
    }

    public function test_generated_matricule_starts_at_00001_for_first_employee_of_year(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->actingAs($user);

        $personnel = $this->service->create([
            'company_id' => $company->id,
            'first_name' => 'Bob',
            'last_name'  => 'Martin',
            'hire_date'  => now()->toDateString(),
        ]);

        $year = now()->year;
        $this->assertSame("EMP-{$year}-00001", $personnel->matricule);
    }

    public function test_sequential_matricules_are_incremented(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->actingAs($user);

        $first = $this->service->create([
            'company_id' => $company->id,
            'first_name' => 'Alice',
            'last_name'  => 'A',
            'hire_date'  => now()->toDateString(),
        ]);

        $second = $this->service->create([
            'company_id' => $company->id,
            'first_name' => 'Bob',
            'last_name'  => 'B',
            'hire_date'  => now()->toDateString(),
        ]);

        $year = now()->year;
        $this->assertSame("EMP-{$year}-00001", $first->matricule);
        $this->assertSame("EMP-{$year}-00002", $second->matricule);
    }

    public function test_matricule_uniqueness_across_multiple_personnel(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->actingAs($user);

        $matricules = [];
        for ($i = 0; $i < 5; $i++) {
            $p = $this->service->create([
                'company_id' => $company->id,
                'first_name' => "Employee{$i}",
                'last_name'  => 'Test',
                'hire_date'  => now()->toDateString(),
            ]);
            $matricules[] = $p->matricule;
        }

        $this->assertCount(5, array_unique($matricules), 'All matricules must be unique');
    }

    public function test_provided_matricule_is_not_overwritten(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->actingAs($user);

        $personnel = $this->service->create([
            'company_id' => $company->id,
            'first_name' => 'Charlie',
            'last_name'  => 'C',
            'hire_date'  => now()->toDateString(),
            'matricule'  => 'CUSTOM-001',
        ]);

        $this->assertSame('CUSTOM-001', $personnel->matricule);
    }

    // ─── User linking ─────────────────────────────────────────────────────────

    public function test_personnel_can_exist_without_linked_user(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->actingAs($user);

        $personnel = $this->service->create([
            'company_id' => $company->id,
            'first_name' => 'Unlinked',
            'last_name'  => 'Person',
            'hire_date'  => now()->toDateString(),
        ]);

        $this->assertNull($personnel->user_id);
        $this->assertDatabaseHas('personnels', [
            'id'      => $personnel->id,
            'user_id' => null,
        ]);
    }

    public function test_link_user_assigns_user_id_to_personnel(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->actingAs($user);

        $personnel = Personnel::factory()->create([
            'company_id' => $company->id,
            'user_id'    => null,
        ]);

        $targetUser = User::factory()->create();
        $result = $this->service->linkUser($personnel, $targetUser->id);

        $this->assertSame($targetUser->id, $result->user_id);
        $this->assertDatabaseHas('personnels', [
            'id'      => $personnel->id,
            'user_id' => $targetUser->id,
        ]);
    }

    public function test_link_user_returns_personnel_with_user_relation_loaded(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();

        $this->actingAs($user);

        $personnel = Personnel::factory()->create(['company_id' => $company->id, 'user_id' => null]);
        
        $targetUser = User::factory()->create();

        $result = $this->service->linkUser($personnel, $targetUser->id);

        $this->assertTrue($result->relationLoaded('user'));
        
        $this->assertSame($targetUser->id, $result->user->id);
    }

    public function test_unlink_user_sets_user_id_to_null(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        
        $this->actingAs($user);

        $linkedUser = User::factory()->create();
        
        $personnel  = Personnel::factory()->create([
            'company_id' => $company->id,
            'user_id' => $linkedUser->id,
        ]);

        $result = $this->service->unlinkUser($personnel);

        $this->assertNull($result->user_id);
        
        $this->assertDatabaseHas('personnels', [
            'id' => $personnel->id,
            'user_id' => null,
        ]);

    }

    public function test_unlink_user_on_already_unlinked_personnel_stays_null(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        
        $this->actingAs($user);

        $personnel = Personnel::factory()->create([
            'company_id' => $company->id,
            'user_id' => null,
        ]);

        $result = $this->service->unlinkUser($personnel);

        $this->assertNull($result->user_id);
    }
}
