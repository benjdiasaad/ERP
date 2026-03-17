<?php

declare(strict_types=1);

namespace Tests\Feature\Personnel;

use App\Models\Auth\Permission;
use App\Models\Auth\Role;
use App\Models\Auth\User;
use App\Models\Company\Company;
use App\Models\Personnel\Attendance;
use App\Models\Personnel\Personnel;
use Tests\TestCase;

class AttendanceTest extends TestCase
{
    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function giveUserPermissions(User $user, Company $company, array $permissionSlugs): void
    {
        $role = Role::create([
            'company_id' => $company->id,
            'name'       => 'Test Role',
            'slug'       => 'test-role-' . uniqid(),
            'is_system'  => false,
        ]);

        foreach ($permissionSlugs as $slug) {
            $permission = Permission::firstOrCreate(
                ['slug' => $slug],
                ['module' => explode('.', $slug)[0], 'name' => $slug, 'description' => '']
            );
            $role->permissions()->syncWithoutDetaching([$permission->id]);
        }

        $user->roles()->syncWithoutDetaching([$role->id => ['company_id' => $company->id]]);
    }

    // ─── Index ────────────────────────────────────────────────────────────────

    public function test_user_with_permission_can_list_attendances(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['attendances.view_any']);

        $personnel = Personnel::factory()->create(['company_id' => $company->id]);
        Attendance::factory()->create(['company_id' => $company->id, 'personnel_id' => $personnel->id]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/attendances');

        $response->assertOk()
            ->assertJsonStructure(['data', 'total', 'per_page', 'current_page']);
    }

    public function test_index_filter_by_personnel_id(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['attendances.view_any']);

        $personnelA = Personnel::factory()->create(['company_id' => $company->id]);
        $personnelB = Personnel::factory()->create(['company_id' => $company->id]);

        $attendanceA = Attendance::factory()->create(['company_id' => $company->id, 'personnel_id' => $personnelA->id]);
        Attendance::factory()->create(['company_id' => $company->id, 'personnel_id' => $personnelB->id]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson("/api/attendances?personnel_id={$personnelA->id}");

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->toArray();
        $this->assertContains($attendanceA->id, $ids);
        foreach ($ids as $id) {
            $this->assertEquals($personnelA->id, Attendance::find($id)->personnel_id);
        }
    }

    public function test_index_filter_by_date_from(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['attendances.view_any']);

        $personnel = Personnel::factory()->create(['company_id' => $company->id]);
        $recent    = Attendance::factory()->create(['company_id' => $company->id, 'personnel_id' => $personnel->id, 'date' => '2024-06-15']);
        $old       = Attendance::factory()->create(['company_id' => $company->id, 'personnel_id' => $personnel->id, 'date' => '2024-01-01']);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/attendances?date_from=2024-06-01');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->toArray();
        $this->assertContains($recent->id, $ids);
        $this->assertNotContains($old->id, $ids);
    }

    public function test_index_filter_by_date_to(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['attendances.view_any']);

        $personnel = Personnel::factory()->create(['company_id' => $company->id]);
        $early     = Attendance::factory()->create(['company_id' => $company->id, 'personnel_id' => $personnel->id, 'date' => '2024-01-10']);
        $late      = Attendance::factory()->create(['company_id' => $company->id, 'personnel_id' => $personnel->id, 'date' => '2024-12-01']);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/attendances?date_to=2024-06-30');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->toArray();
        $this->assertContains($early->id, $ids);
        $this->assertNotContains($late->id, $ids);
    }

    public function test_index_filter_by_status(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['attendances.view_any']);

        $personnel = Personnel::factory()->create(['company_id' => $company->id]);
        Attendance::factory()->create(['company_id' => $company->id, 'personnel_id' => $personnel->id, 'status' => 'present']);
        Attendance::factory()->absent()->create(['company_id' => $company->id, 'personnel_id' => $personnel->id]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/attendances?status=absent');

        $response->assertOk();
        $statuses = collect($response->json('data'))->pluck('status')->toArray();
        foreach ($statuses as $status) {
            $this->assertEquals('absent', $status);
        }
    }

    public function test_index_requires_authentication(): void
    {
        $this->getJson('/api/attendances')->assertUnauthorized();
    }

    public function test_index_requires_view_any_permission(): void
    {
        ['user' => $user] = $this->setUpCompanyAndUser();

        $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/attendances')
            ->assertForbidden();
    }

    // ─── Store ────────────────────────────────────────────────────────────────

    public function test_user_with_permission_can_create_attendance(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['attendances.create']);

        $personnel = Personnel::factory()->create(['company_id' => $company->id]);

        $payload = [
            'personnel_id' => $personnel->id,
            'date'         => '2024-06-10',
            'status'       => 'present',
            'check_in'     => '08:00:00',
            'check_out'    => '17:00:00',
        ];

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/attendances', $payload);

        $response->assertCreated()
            ->assertJsonFragment(['status' => 'present']);

        $this->assertDatabaseHas('attendances', [
            'personnel_id' => $personnel->id,
            'company_id'   => $company->id,
            'status'       => 'present',
        ]);
    }

    public function test_store_requires_personnel_id(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['attendances.create']);

        $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/attendances', ['date' => '2024-06-10', 'status' => 'present'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['personnel_id']);
    }

    public function test_store_requires_date(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['attendances.create']);

        $personnel = Personnel::factory()->create(['company_id' => $company->id]);

        $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/attendances', ['personnel_id' => $personnel->id, 'status' => 'present'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['date']);
    }

    public function test_store_requires_create_permission(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();

        $personnel = Personnel::factory()->create(['company_id' => $company->id]);

        $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/attendances', ['personnel_id' => $personnel->id, 'date' => '2024-06-10', 'status' => 'present'])
            ->assertForbidden();
    }

    // ─── Show ─────────────────────────────────────────────────────────────────

    public function test_user_with_permission_can_view_attendance_with_personnel(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['attendances.view']);

        $personnel  = Personnel::factory()->create(['company_id' => $company->id]);
        $attendance = Attendance::factory()->create(['company_id' => $company->id, 'personnel_id' => $personnel->id]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson("/api/attendances/{$attendance->id}");

        $response->assertOk()
            ->assertJsonFragment(['id' => $attendance->id])
            ->assertJsonStructure(['personnel']);
    }

    public function test_show_returns_404_for_nonexistent_attendance(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['attendances.view']);

        $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/attendances/99999')
            ->assertNotFound();
    }

    public function test_show_requires_view_permission(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();

        $personnel  = Personnel::factory()->create(['company_id' => $company->id]);
        $attendance = Attendance::factory()->create(['company_id' => $company->id, 'personnel_id' => $personnel->id]);

        $this->withHeaders($this->authHeaders($user))
            ->getJson("/api/attendances/{$attendance->id}")
            ->assertForbidden();
    }

    // ─── Update ───────────────────────────────────────────────────────────────

    public function test_user_with_permission_can_update_attendance(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['attendances.update']);

        $personnel  = Personnel::factory()->create(['company_id' => $company->id]);
        $attendance = Attendance::factory()->create(['company_id' => $company->id, 'personnel_id' => $personnel->id, 'status' => 'present']);

        $response = $this->withHeaders($this->authHeaders($user))
            ->putJson("/api/attendances/{$attendance->id}", ['status' => 'remote']);

        $response->assertOk();
        $this->assertDatabaseHas('attendances', ['id' => $attendance->id, 'status' => 'remote']);
    }

    public function test_update_requires_update_permission(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();

        $personnel  = Personnel::factory()->create(['company_id' => $company->id]);
        $attendance = Attendance::factory()->create(['company_id' => $company->id, 'personnel_id' => $personnel->id]);

        $this->withHeaders($this->authHeaders($user))
            ->putJson("/api/attendances/{$attendance->id}", ['status' => 'absent'])
            ->assertForbidden();
    }

    // ─── Destroy ──────────────────────────────────────────────────────────────

    public function test_user_with_permission_can_soft_delete_attendance(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['attendances.delete']);

        $personnel  = Personnel::factory()->create(['company_id' => $company->id]);
        $attendance = Attendance::factory()->create(['company_id' => $company->id, 'personnel_id' => $personnel->id]);

        $this->withHeaders($this->authHeaders($user))
            ->deleteJson("/api/attendances/{$attendance->id}")
            ->assertNoContent();

        $this->assertSoftDeleted('attendances', ['id' => $attendance->id]);
    }

    public function test_destroy_requires_delete_permission(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();

        $personnel  = Personnel::factory()->create(['company_id' => $company->id]);
        $attendance = Attendance::factory()->create(['company_id' => $company->id, 'personnel_id' => $personnel->id]);

        $this->withHeaders($this->authHeaders($user))
            ->deleteJson("/api/attendances/{$attendance->id}")
            ->assertForbidden();
    }

    // ─── Tenant Isolation ─────────────────────────────────────────────────────

    public function test_user_from_company_b_cannot_view_attendance_from_company_a(): void
    {
        ['user' => $userA, 'company' => $companyA] = $this->setUpCompanyAndUser();
        ['user' => $userB, 'company' => $companyB] = $this->setUpCompanyAndUser();

        $this->giveUserPermissions($userA, $companyA, ['attendances.view']);
        $this->giveUserPermissions($userB, $companyB, ['attendances.view']);

        $personnel  = Personnel::factory()->create(['company_id' => $companyA->id]);
        $attendance = Attendance::factory()->create(['company_id' => $companyA->id, 'personnel_id' => $personnel->id]);

        $this->withHeaders($this->authHeaders($userB))
            ->getJson("/api/attendances/{$attendance->id}")
            ->assertStatus(404);
    }

    public function test_index_does_not_leak_attendances_across_companies(): void
    {
        ['user' => $userA, 'company' => $companyA] = $this->setUpCompanyAndUser();
        ['user' => $userB, 'company' => $companyB] = $this->setUpCompanyAndUser();

        $this->giveUserPermissions($userA, $companyA, ['attendances.view_any']);
        $this->giveUserPermissions($userB, $companyB, ['attendances.view_any']);

        $personnelA   = Personnel::factory()->create(['company_id' => $companyA->id]);
        $personnelB   = Personnel::factory()->create(['company_id' => $companyB->id]);
        $attendanceA  = Attendance::factory()->create(['company_id' => $companyA->id, 'personnel_id' => $personnelA->id]);
        $attendanceB  = Attendance::factory()->create(['company_id' => $companyB->id, 'personnel_id' => $personnelB->id]);

        $response = $this->withHeaders($this->authHeaders($userA))
            ->getJson('/api/attendances');

        $ids = collect($response->json('data'))->pluck('id')->toArray();
        $this->assertContains($attendanceA->id, $ids);
        $this->assertNotContains($attendanceB->id, $ids);
    }

}
