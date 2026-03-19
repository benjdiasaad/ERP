<?php

declare(strict_types=1);

namespace Tests\Feature\Caution;

use App\Models\Auth\Permission;
use App\Models\Auth\Role;
use App\Models\Auth\User;
use App\Models\Caution\Caution;
use App\Models\Caution\CautionHistory;
use App\Models\Caution\CautionType;
use App\Models\Company\Company;
use App\Models\Sales\Customer;
use Carbon\Carbon;
use Tests\TestCase;

class CautionLifecycleTest extends TestCase
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

    private function allCautionPermissions(): array
    {
        return [
            'cautions.view_any',
            'cautions.view',
            'cautions.create',
            'cautions.update',
            'cautions.delete',
        ];
    }

    private function createCaution(Company $company): Caution
    {
        $cautionType = CautionType::factory()->create(['company_id' => $company->id]);
        $customer = Customer::factory()->company()->create(['company_id' => $company->id]);

        return Caution::factory()->draft()->create([
            'company_id'      => $company->id,
            'caution_type_id' => $cautionType->id,
            'partner_type' => 'App\Models\Sales\Customer',
            'partner_id'      => $customer->id,
            'direction'       => 'given',
            'amount'          => 5000.00,
        ]);
    }

    // ─── Lifecycle: Creation ──────────────────────────────────────────────────

    public function test_caution_creation_starts_in_draft_status(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['cautions.create']);

        $cautionType = CautionType::factory()->create(['company_id' => $company->id]);
        $customer = Customer::factory()->company()->create(['company_id' => $company->id]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/cautions', [
                'caution_type_id' => $cautionType->id,
                'direction'       => 'given',
                'partner_type' => 'App\Models\Sales\Customer',
                'partner_id'      => $customer->id,
                'amount'          => 5000.00,
                'issue_date'       => now()->toDateString(),
                'expiry_date'       => now()->addMonths(6)->toDateString(),
            ]);

        $response->assertCreated();
        $this->assertEquals('draft', $response->json('data.status'));
    }

    public function test_caution_creation_logs_history(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['cautions.create']);

        $cautionType = CautionType::factory()->create(['company_id' => $company->id]);
        $customer = Customer::factory()->company()->create(['company_id' => $company->id]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/cautions', [
                'caution_type_id' => $cautionType->id,
                'direction'       => 'given',
                'partner_type' => 'App\Models\Sales\Customer',
                'partner_id'      => $customer->id,
                'amount'          => 5000.00,
                'issue_date'       => now()->toDateString(),
                'expiry_date'       => now()->addMonths(6)->toDateString(),
            ]);

        $cautionId = $response->json('data.id');

        $this->assertDatabaseHas('caution_histories', [
            'caution_id' => $cautionId,
            'action'     => 'created',
            'new_status' => 'draft',
        ]);
    }

    // ─── Lifecycle: Activate ──────────────────────────────────────────────────

    public function test_draft_caution_can_be_activated(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['cautions.update']);

        $caution = $this->createCaution($company);

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/cautions/{$caution->id}/activate");

        $response->assertOk();
        $this->assertEquals('active', $response->json('data.status'));
        $this->assertDatabaseHas('cautions', ['id' => $caution->id, 'status' => 'active']);
    }

    public function test_activate_logs_history(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['cautions.update']);

        $caution = $this->createCaution($company);

        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/cautions/{$caution->id}/activate");

        $this->assertDatabaseHas('caution_histories', [
            'caution_id'      => $caution->id,
            'action'          => 'activated',
            'previous_status' => 'draft',
            'new_status'      => 'active',
        ]);
    }

    public function test_cannot_activate_non_draft_caution(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['cautions.update']);

        $caution = $this->createCaution($company);
        $caution->update(['status' => 'active']);

        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/cautions/{$caution->id}/activate")
            ->assertUnprocessable();
    }

    // ─── Lifecycle: Partial Return ────────────────────────────────────────────

    public function test_active_caution_can_have_partial_return(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['cautions.update']);

        $caution = $this->createCaution($company);
        $caution->update(['status' => 'active']);

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/cautions/{$caution->id}/partial-return", [
                'amount' => 2000.00,
                'notes'  => 'Partial return of 2000',
            ]);

        $response->assertOk();
        $this->assertEquals('partially_returned', $response->json('data.status'));
    }

    public function test_partial_return_logs_history(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['cautions.update']);

        $caution = $this->createCaution($company);
        $caution->update(['status' => 'active']);

        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/cautions/{$caution->id}/partial-return", [
                'amount' => 2000.00,
                'notes'  => 'Partial return',
            ]);

        $this->assertDatabaseHas('caution_histories', [
            'caution_id'      => $caution->id,
            'action'          => 'partial_return',
            'previous_status' => 'active',
            'new_status'      => 'partially_returned',
            'amount'          => 2000.00,
        ]);
    }

    public function test_partially_returned_caution_can_have_another_partial_return(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['cautions.update']);

        $caution = $this->createCaution($company);
        $caution->update(['status' => 'partially_returned']);

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/cautions/{$caution->id}/partial-return", [
                'amount' => 1500.00,
            ]);

        $response->assertOk();
        $this->assertEquals('partially_returned', $response->json('data.status'));
    }

    // ─── Lifecycle: Full Return ───────────────────────────────────────────────

    public function test_active_caution_can_be_fully_returned(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['cautions.update']);

        $caution = $this->createCaution($company);
        $caution->update(['status' => 'active']);

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/cautions/{$caution->id}/full-return", [
                'notes' => 'Full return completed',
            ]);

        $response->assertOk();
        $this->assertEquals('returned', $response->json('data.status'));
        $this->assertNotNull($response->json('data.return_date'));
    }

    public function test_partially_returned_caution_can_be_fully_returned(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['cautions.update']);

        $caution = $this->createCaution($company);
        $caution->update(['status' => 'partially_returned']);

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/cautions/{$caution->id}/full-return");

        $response->assertOk();
        $this->assertEquals('returned', $response->json('data.status'));
    }

    public function test_full_return_logs_history(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['cautions.update']);

        $caution = $this->createCaution($company);
        $caution->update(['status' => 'active']);

        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/cautions/{$caution->id}/full-return", [
                'notes' => 'Full return',
            ]);

        $this->assertDatabaseHas('caution_histories', [
            'caution_id'      => $caution->id,
            'action'          => 'full_return',
            'previous_status' => 'active',
            'new_status'      => 'returned',
        ]);
    }

    // ─── Lifecycle: Extend ────────────────────────────────────────────────────

    public function test_caution_expiry_can_be_extended(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['cautions.update']);

        $caution = $this->createCaution($company);
        $originalExpiry = $caution->expiry_date;
        $newExpiry = $originalExpiry->addMonths(3);

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/cautions/{$caution->id}/extend", [
                'expiry_date' => $newExpiry->toDateString(),
            ]);

        $response->assertOk();
        $this->assertEquals($newExpiry->toDateString(), Carbon::parse($response->json('data.expiry_date'))->toDateString());
    }

    public function test_extend_logs_history(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['cautions.update']);

        $caution = $this->createCaution($company);
        $newExpiry = $caution->expiry_date->addMonths(3);

        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/cautions/{$caution->id}/extend", [
                'expiry_date' => $newExpiry->toDateString(),
            ]);

        $this->assertDatabaseHas('caution_histories', [
            'caution_id' => $caution->id,
            'action'     => 'extended',
        ]);
    }

    public function test_cannot_extend_to_earlier_date(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['cautions.update']);

        $caution = $this->createCaution($company);
        $earlierDate = $caution->expiry_date->subMonths(1);

        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/cautions/{$caution->id}/extend", [
                'expiry_date' => $earlierDate->toDateString(),
            ])
            ->assertUnprocessable();
    }

    // ─── Lifecycle: Forfeit ───────────────────────────────────────────────────

    public function test_active_caution_can_be_forfeited(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['cautions.update']);

        $caution = $this->createCaution($company);
        $caution->update(['status' => 'active']);

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/cautions/{$caution->id}/forfeit", [
                'notes' => 'Forfeited due to breach',
            ]);

        $response->assertOk();
        $this->assertEquals('forfeited', $response->json('data.status'));
    }

    public function test_forfeit_logs_history(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['cautions.update']);

        $caution = $this->createCaution($company);
        $caution->update(['status' => 'active']);

        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/cautions/{$caution->id}/forfeit", [
                'notes' => 'Forfeited',
            ]);

        $this->assertDatabaseHas('caution_histories', [
            'caution_id'      => $caution->id,
            'action'          => 'forfeited',
            'previous_status' => 'active',
            'new_status'      => 'forfeited',
        ]);
    }

    // ─── Lifecycle: Cancel ────────────────────────────────────────────────────

    public function test_draft_caution_can_be_cancelled(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['cautions.update']);

        $caution = $this->createCaution($company);

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/cautions/{$caution->id}/cancel", [
                'notes' => 'Cancelled',
            ]);

        $response->assertOk();
        $this->assertEquals('cancelled', $response->json('data.status'));
    }

    public function test_cancel_logs_history(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['cautions.update']);

        $caution = $this->createCaution($company);

        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/cautions/{$caution->id}/cancel", [
                'notes' => 'Cancelled',
            ]);

        $this->assertDatabaseHas('caution_histories', [
            'caution_id'      => $caution->id,
            'action'          => 'cancelled',
            'previous_status' => 'draft',
            'new_status'      => 'cancelled',
        ]);
    }

    public function test_cannot_cancel_non_draft_caution(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['cautions.update']);

        $caution = $this->createCaution($company);
        $caution->update(['status' => 'active']);

        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/cautions/{$caution->id}/cancel")
            ->assertUnprocessable();
    }

    // ─── Expiring Cautions ────────────────────────────────────────────────────

    public function test_get_expiring_cautions_within_days(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['cautions.view_any']);

        $cautionType = CautionType::factory()->create(['company_id' => $company->id]);
        $customer = Customer::factory()->company()->create(['company_id' => $company->id]);

        // Expiring in 10 days
        Caution::factory()->active()->create([
            'company_id'      => $company->id,
            'caution_type_id' => $cautionType->id,
            'partner_type' => 'App\Models\Sales\Customer',
            'partner_id'      => $customer->id,
            'expiry_date'       => now()->addDays(10),
        ]);

        // Expiring in 60 days (outside default 30-day window)
        Caution::factory()->active()->create([
            'company_id'      => $company->id,
            'caution_type_id' => $cautionType->id,
            'partner_type' => 'App\Models\Sales\Customer',
            'partner_id'      => $customer->id,
            'expiry_date'       => now()->addDays(60),
        ]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/cautions/expiring');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }

    public function test_get_expiring_cautions_with_custom_days(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['cautions.view_any']);

        $cautionType = CautionType::factory()->create(['company_id' => $company->id]);
        $customer = Customer::factory()->company()->create(['company_id' => $company->id]);

        Caution::factory()->active()->create([
            'company_id'      => $company->id,
            'caution_type_id' => $cautionType->id,
            'partner_type' => 'App\Models\Sales\Customer',
            'partner_id'      => $customer->id,
            'expiry_date'       => now()->addDays(45),
        ]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/cautions/expiring?days=60');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }

    public function test_expiring_only_includes_active_cautions(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['cautions.view_any']);

        $cautionType = CautionType::factory()->create(['company_id' => $company->id]);
        $customer = Customer::factory()->company()->create(['company_id' => $company->id]);

        Caution::factory()->draft()->create([
            'company_id'      => $company->id,
            'caution_type_id' => $cautionType->id,
            'partner_type' => 'App\Models\Sales\Customer',
            'partner_id'      => $customer->id,
            'expiry_date'       => now()->addDays(10),
        ]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/cautions/expiring');

        $response->assertOk();
        $this->assertCount(0, $response->json('data'));
    }

    // ─── Dashboard Stats ──────────────────────────────────────────────────────

    public function test_dashboard_stats_returns_totals(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['cautions.view_any']);

        $cautionType = CautionType::factory()->create(['company_id' => $company->id]);
        $customer = Customer::factory()->company()->create(['company_id' => $company->id]);

        Caution::factory()->active()->create([
            'company_id'      => $company->id,
            'caution_type_id' => $cautionType->id,
            'partner_type' => 'App\Models\Sales\Customer',
            'partner_id'      => $customer->id,
            'direction'       => 'given',
            'amount'          => 5000.00,
        ]);

        Caution::factory()->active()->create([
            'company_id'      => $company->id,
            'caution_type_id' => $cautionType->id,
            'partner_type' => 'App\Models\Sales\Customer',
            'partner_id'      => $customer->id,
            'direction'       => 'received',
            'amount'          => 3000.00,
        ]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/cautions/stats');

        $response->assertOk();
        $this->assertEquals(5000.00, $response->json('total_given'));
        $this->assertEquals(3000.00, $response->json('total_received'));
        $this->assertEquals(2, $response->json('active_count'));
    }

    public function test_dashboard_stats_counts_expiring(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['cautions.view_any']);

        $cautionType = CautionType::factory()->create(['company_id' => $company->id]);
        $customer = Customer::factory()->company()->create(['company_id' => $company->id]);

        Caution::factory()->active()->create([
            'company_id'      => $company->id,
            'caution_type_id' => $cautionType->id,
            'partner_type' => 'App\Models\Sales\Customer',
            'partner_id'      => $customer->id,
            'expiry_date'       => now()->addDays(15),
        ]);

        Caution::factory()->active()->create([
            'company_id'      => $company->id,
            'caution_type_id' => $cautionType->id,
            'partner_type' => 'App\Models\Sales\Customer',
            'partner_id'      => $customer->id,
            'expiry_date'       => now()->addDays(60),
        ]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/cautions/stats');

        $response->assertOk();
        $this->assertEquals(1, $response->json('expiring_count'));
    }

    public function test_dashboard_stats_counts_forfeited(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['cautions.view_any']);

        $cautionType = CautionType::factory()->create(['company_id' => $company->id]);
        $customer = Customer::factory()->company()->create(['company_id' => $company->id]);

        Caution::factory()->forfeited()->create([
            'company_id'      => $company->id,
            'caution_type_id' => $cautionType->id,
            'partner_type' => 'App\Models\Sales\Customer',
            'partner_id'      => $customer->id,
            'amount'          => 2000.00,
        ]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/cautions/stats');

        $response->assertOk();
        $this->assertEquals(2000.00, $response->json('forfeited_total'));
    }

    // ─── History Tracking ─────────────────────────────────────────────────────

    public function test_each_action_creates_history_entry(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, $this->allCautionPermissions());

        $caution = $this->createCaution($company);

        // Activate
        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/cautions/{$caution->id}/activate");

        // Partial return
        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/cautions/{$caution->id}/partial-return", ['amount' => 1000.00]);

        $histories = CautionHistory::where('caution_id', $caution->id)->get();

        $this->assertGreaterThanOrEqual(3, $histories->count()); // created, activated, partial_return
        $this->assertTrue($histories->pluck('action')->contains('created'));
        $this->assertTrue($histories->pluck('action')->contains('activated'));
        $this->assertTrue($histories->pluck('action')->contains('partial_return'));
    }

    public function test_history_tracks_status_transitions(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, $this->allCautionPermissions());

        $caution = $this->createCaution($company);

        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/cautions/{$caution->id}/activate");

        $history = CautionHistory::where('caution_id', $caution->id)
            ->where('action', 'activated')
            ->first();

        $this->assertNotNull($history);
        $this->assertEquals('draft', $history->previous_status);
        $this->assertEquals('active', $history->new_status);
    }

    public function test_history_tracks_amounts(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, $this->allCautionPermissions());

        $caution = $this->createCaution($company);
        $caution->update(['status' => 'active']);

        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/cautions/{$caution->id}/partial-return", [
                'amount' => 1500.00,
            ]);

        $history = CautionHistory::where('caution_id', $caution->id)
            ->where('action', 'partial_return')
            ->first();

        $this->assertNotNull($history);
        $this->assertEquals(1500.00, $history->amount);
    }

    public function test_history_tracks_user_who_performed_action(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, $this->allCautionPermissions());

        $caution = $this->createCaution($company);

        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/cautions/{$caution->id}/activate");

        $history = CautionHistory::where('caution_id', $caution->id)
            ->where('action', 'activated')
            ->first();

        $this->assertNotNull($history);
        $this->assertEquals($user->id, $history->created_by);
    }
}
