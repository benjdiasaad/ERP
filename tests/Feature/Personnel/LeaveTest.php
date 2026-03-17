<?php

declare(strict_types=1);

namespace Tests\Feature\Personnel;

use App\Models\Auth\Permission;
use App\Models\Auth\Role;
use App\Models\Auth\User;
use App\Models\Company\Company;
use App\Models\Personnel\Leave;
use App\Models\Personnel\Personnel;
use Tests\TestCase;

class LeaveTest extends TestCase
{
    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function giveUserPermissions(User $user, Company $company, array $permissionSlugs): void
    {
        $role = Role::create([
            'company_id' => $company->id,
            'name' => 'Test Role',
            'slug' => 'test-role-' . uniqid(),
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

    public function test_user_with_permission_can_list_leaves(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['leaves.view_any']);

        $personnel = Personnel::factory()->create(['company_id' => $company->id]);
        Leave::factory()->create(['company_id' => $company->id, 'personnel_id' => $personnel->id]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/leaves');

        $response->assertOk()
            ->assertJsonStructure(['data', 'total', 'per_page', 'current_page']);
    }

    public function test_index_filter_by_personnel_id(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['leaves.view_any']);

        $personnelA = Personnel::factory()->create(['company_id' => $company->id]);
        $personnelB = Personnel::factory()->create(['company_id' => $company->id]);

        $leaveA = Leave::factory()->create(['company_id' => $company->id, 'personnel_id' => $personnelA->id]);
        Leave::factory()->create(['company_id' => $company->id, 'personnel_id' => $personnelB->id]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson("/api/leaves?personnel_id={$personnelA->id}");

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->toArray();
        $this->assertContains($leaveA->id, $ids);
        foreach ($ids as $id) {
            $this->assertEquals($personnelA->id, Leave::find($id)->personnel_id);
        }
    }

    public function test_index_filter_by_status(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['leaves.view_any']);

        $personnel = Personnel::factory()->create(['company_id' => $company->id]);
        Leave::factory()->create(['company_id' => $company->id, 'personnel_id' => $personnel->id, 'status' => 'pending']);
        Leave::factory()->approved()->create(['company_id' => $company->id, 'personnel_id' => $personnel->id]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/leaves?status=pending');

        $response->assertOk();
        $statuses = collect($response->json('data'))->pluck('status')->toArray();
        foreach ($statuses as $status) {
            $this->assertEquals('pending', $status);
        }
    }

    public function test_index_requires_authentication(): void
    {
        $this->getJson('/api/leaves')->assertUnauthorized();
    }

    public function test_index_requires_view_any_permission(): void
    {
        ['user' => $user] = $this->setUpCompanyAndUser();

        $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/leaves')
            ->assertForbidden();
    }

    // ─── Store ────────────────────────────────────────────────────────────────

    public function test_user_with_permission_can_create_leave_request(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['leaves.create']);

        $personnel = Personnel::factory()->create(['company_id' => $company->id]);

        $payload = [
            'personnel_id' => $personnel->id,
            'type' => 'annual',
            'start_date' => '2024-06-01',
            'end_date' => '2024-06-05',
            'total_days' => 5,
        ];

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/leaves', $payload);

        $response->assertCreated()
            ->assertJsonFragment(['status' => 'pending']);

        $this->assertDatabaseHas('leaves', [
            'personnel_id' => $personnel->id,
            'status' => 'pending',
            'company_id' => $company->id,
        ]);
    }

    public function test_store_status_defaults_to_pending(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['leaves.create']);

        $personnel = Personnel::factory()->create(['company_id' => $company->id]);

        $payload = [
            'personnel_id' => $personnel->id,
            'type' => 'sick',
            'start_date' => '2024-07-01',
            'end_date' => '2024-07-03',
        ];

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/leaves', $payload);

        $response->assertCreated();
        $this->assertEquals('pending', $response->json('status'));
    }

    public function test_store_requires_personnel_id(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['leaves.create']);

        $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/leaves', ['type' => 'annual', 'start_date' => '2024-06-01', 'end_date' => '2024-06-05'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['personnel_id']);
    }

    public function test_store_requires_type(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['leaves.create']);

        $personnel = Personnel::factory()->create(['company_id' => $company->id]);

        $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/leaves', ['personnel_id' => $personnel->id, 'start_date' => '2024-06-01', 'end_date' => '2024-06-05'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['type']);
    }

    public function test_store_requires_create_permission(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();

        $personnel = Personnel::factory()->create(['company_id' => $company->id]);

        $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/leaves', ['personnel_id' => $personnel->id, 'type' => 'annual', 'start_date' => '2024-06-01', 'end_date' => '2024-06-05'])
            ->assertForbidden();
    }

    // ─── Show ─────────────────────────────────────────────────────────────────

    public function test_user_with_permission_can_view_leave_with_relations(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['leaves.view']);

        $personnel = Personnel::factory()->create(['company_id' => $company->id]);
        $leave = Leave::factory()->create(['company_id' => $company->id, 'personnel_id' => $personnel->id]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson("/api/leaves/{$leave->id}");

        $response->assertOk()
            ->assertJsonFragment(['id' => $leave->id])
            ->assertJsonStructure(['personnel', 'approved_by']);
    }

    public function test_show_returns_404_for_nonexistent_leave(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['leaves.view']);

        $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/leaves/99999')
            ->assertNotFound();
    }

    public function test_show_requires_view_permission(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();

        $personnel = Personnel::factory()->create(['company_id' => $company->id]);
        $leave = Leave::factory()->create(['company_id' => $company->id, 'personnel_id' => $personnel->id]);

        $this->withHeaders($this->authHeaders($user))
            ->getJson("/api/leaves/{$leave->id}")
            ->assertForbidden();
    }

    // ─── Update ───────────────────────────────────────────────────────────────

    public function test_user_with_permission_can_update_leave(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['leaves.update']);

        $personnel = Personnel::factory()->create(['company_id' => $company->id]);
        $leave = Leave::factory()->create(['company_id' => $company->id, 'personnel_id' => $personnel->id, 'total_days' => 3]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->putJson("/api/leaves/{$leave->id}", ['total_days' => 5]);

        $response->assertOk();
        $this->assertDatabaseHas('leaves', ['id' => $leave->id, 'total_days' => 5]);
    }

    public function test_update_requires_update_permission(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();

        $personnel = Personnel::factory()->create(['company_id' => $company->id]);
        $leave = Leave::factory()->create(['company_id' => $company->id, 'personnel_id' => $personnel->id]);

        $this->withHeaders($this->authHeaders($user))
            ->putJson("/api/leaves/{$leave->id}", ['total_days' => 10])
            ->assertForbidden();
    }

    // ─── Destroy ──────────────────────────────────────────────────────────────

    public function test_user_with_permission_can_soft_delete_leave(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['leaves.delete']);

        $personnel = Personnel::factory()->create(['company_id' => $company->id]);
        $leave = Leave::factory()->create(['company_id' => $company->id, 'personnel_id' => $personnel->id]);

        $this->withHeaders($this->authHeaders($user))
            ->deleteJson("/api/leaves/{$leave->id}")
            ->assertNoContent();

        $this->assertSoftDeleted('leaves', ['id' => $leave->id]);
    }

    public function test_destroy_requires_delete_permission(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();

        $personnel = Personnel::factory()->create(['company_id' => $company->id]);
        $leave = Leave::factory()->create(['company_id' => $company->id, 'personnel_id' => $personnel->id]);

        $this->withHeaders($this->authHeaders($user))
            ->deleteJson("/api/leaves/{$leave->id}")
            ->assertForbidden();
    }

    // ─── Approve ──────────────────────────────────────────────────────────────

    public function test_user_with_permission_can_approve_leave(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['leaves.update']);

        $personnel = Personnel::factory()->create(['company_id' => $company->id]);
        $leave = Leave::factory()->create(['company_id' => $company->id, 'personnel_id' => $personnel->id, 'status' => 'pending']);

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/leaves/{$leave->id}/approve");

        $response->assertOk();

        $this->assertDatabaseHas('leaves', [
            'id' => $leave->id,
            'status'  => 'approved',
            'approved_by' => $user->id,
        ]);

        $this->assertNotNull($response->json('approved_at'));
    }

    public function test_approve_requires_update_permission(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();

        $personnel = Personnel::factory()->create(['company_id' => $company->id]);
        $leave = Leave::factory()->create(['company_id' => $company->id, 'personnel_id' => $personnel->id]);

        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/leaves/{$leave->id}/approve")
            ->assertForbidden();
    }

    // ─── Reject ───────────────────────────────────────────────────────────────

    public function test_user_with_permission_can_reject_leave_with_reason(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['leaves.update']);

        $personnel = Personnel::factory()->create(['company_id' => $company->id]);
        $leave = Leave::factory()->create(['company_id' => $company->id, 'personnel_id' => $personnel->id, 'status' => 'pending']);

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/leaves/{$leave->id}/reject", ['reason' => 'Insufficient leave balance']);

        $response->assertOk();

        $this->assertDatabaseHas('leaves', [
            'id'=> $leave->id,
            'status'  => 'rejected',
            'approved_by'  => $user->id,
            'rejection_reason' => 'Insufficient leave balance',
        ]);
    }

    public function test_reject_sets_rejection_reason_from_request(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['leaves.update']);

        $personnel = Personnel::factory()->create(['company_id' => $company->id]);
        $leave = Leave::factory()->create(['company_id' => $company->id, 'personnel_id' => $personnel->id]);

        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/leaves/{$leave->id}/reject", ['reason' => 'Team is at full capacity']);

        $this->assertDatabaseHas('leaves', [
            'id' => $leave->id,
            'rejection_reason' => 'Team is at full capacity',
        ]);
    }

    public function test_reject_requires_update_permission(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();

        $personnel = Personnel::factory()->create(['company_id' => $company->id]);
        $leave = Leave::factory()->create(['company_id' => $company->id, 'personnel_id' => $personnel->id]);

        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/leaves/{$leave->id}/reject", ['reason' => 'No reason'])
            ->assertForbidden();
    }

    // ─── Tenant Isolation ─────────────────────────────────────────────────────

    public function test_user_from_company_b_cannot_view_leave_from_company_a(): void
    {
        ['user' => $userA, 'company' => $companyA] = $this->setUpCompanyAndUser();
        ['user' => $userB, 'company' => $companyB] = $this->setUpCompanyAndUser();

        $this->giveUserPermissions($userA, $companyA, ['leaves.view']);
        $this->giveUserPermissions($userB, $companyB, ['leaves.view']);

        $personnel = Personnel::factory()->create(['company_id' => $companyA->id]);
        $leave = Leave::factory()->create(['company_id' => $companyA->id, 'personnel_id' => $personnel->id]);

        $this->withHeaders($this->authHeaders($userB))
            ->getJson("/api/leaves/{$leave->id}")
            ->assertStatus(404);
    }

    public function test_index_does_not_leak_leaves_across_companies(): void
    {
        ['user' => $userA, 'company' => $companyA] = $this->setUpCompanyAndUser();
        ['user' => $userB, 'company' => $companyB] = $this->setUpCompanyAndUser();

        $this->giveUserPermissions($userA, $companyA, ['leaves.view_any']);
        $this->giveUserPermissions($userB, $companyB, ['leaves.view_any']);

        $personnelA = Personnel::factory()->create(['company_id' => $companyA->id]);
        $personnelB = Personnel::factory()->create(['company_id' => $companyB->id]);

        $leaveA = Leave::factory()->create(['company_id' => $companyA->id, 'personnel_id' => $personnelA->id]);
        $leaveB = Leave::factory()->create(['company_id' => $companyB->id, 'personnel_id' => $personnelB->id]);

        $response = $this->withHeaders($this->authHeaders($userA))
            ->getJson('/api/leaves');

        $ids = collect($response->json('data'))->pluck('id')->toArray();
        $this->assertContains($leaveA->id, $ids);
        $this->assertNotContains($leaveB->id, $ids);
    }
}
