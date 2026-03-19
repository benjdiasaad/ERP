<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\Auth\Permission;
use App\Models\Auth\Role;
use App\Models\Auth\User;
use App\Models\Company\Company;
use App\Models\Finance\ChartOfAccount;
use App\Models\Finance\JournalEntry;
use App\Models\Finance\JournalEntryLine;
use App\Services\Finance\JournalEntryService;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class JournalEntryTest extends TestCase
{
    private JournalEntryService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(JournalEntryService::class);
    }

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

    private function journalEntryPayload(array $lines = [], array $overrides = []): array
    {
        return array_merge([
            'reference'   => 'JE-2024-001',
            'date'        => now()->toDateString(),
            'description' => 'Test journal entry',
            'status'      => 'draft',
            'lines'       => $lines,
        ], $overrides);
    }

    private function journalLinePayload(
        ChartOfAccount $account,
        float $debit = 0,
        float $credit = 0,
        array $overrides = []
    ): array {
        return array_merge([
            'chart_of_account_id' => $account->id,
            'debit'               => $debit,
            'credit'              => $credit,
            'description'         => 'Test line',
            'sort_order'          => 0,
        ], $overrides);
    }

    private function allJournalPermissions(): array
    {
        return [
            'journal_entries.view_any',
            'journal_entries.view',
            'journal_entries.create',
            'journal_entries.update',
            'journal_entries.delete',
            'journal_entries.restore',
            'journal_entries.force_delete',
            'journal_entries.export',
        ];
    }

    // ─── Index ────────────────────────────────────────────────────────────────

    public function test_user_with_permission_can_list_journal_entries(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['journal_entries.view_any']);

        JournalEntry::factory()->count(3)->create(['company_id' => $company->id]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/journal-entries');

        $response->assertOk();
        $response->assertJsonStructure(['data' => [['id', 'reference', 'date', 'status']]]);
        $this->assertCount(3, $response->json('data'));
    }

    public function test_index_requires_authentication(): void
    {
        $response = $this->getJson('/api/journal-entries');

        $response->assertUnauthorized();
    }

    public function test_index_requires_view_any_permission(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/journal-entries');

        $response->assertForbidden();
    }

    public function test_index_only_returns_entries_from_current_company(): void
    {
        ['user' => $userA, 'company' => $companyA] = $this->setUpCompanyAndUser();
        ['user' => $userB, 'company' => $companyB] = $this->setUpCompanyAndUser();

        $this->giveUserPermissions($userA, $companyA, ['journal_entries.view_any']);
        $this->giveUserPermissions($userB, $companyB, ['journal_entries.view_any']);

        JournalEntry::factory()->create(['company_id' => $companyA->id, 'reference' => 'JE-A-001']);
        JournalEntry::factory()->create(['company_id' => $companyB->id, 'reference' => 'JE-B-001']);

        $response = $this->withHeaders($this->authHeaders($userA))
            ->getJson('/api/journal-entries');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('JE-A-001', $response->json('data.0.reference'));
    }

    public function test_index_can_filter_by_status(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['journal_entries.view_any']);

        JournalEntry::factory()->create(['company_id' => $company->id, 'status' => 'draft']);
        JournalEntry::factory()->create(['company_id' => $company->id, 'status' => 'posted']);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/journal-entries?status=draft');

        $response->assertOk();
        $statuses = collect($response->json('data'))->pluck('status')->unique()->toArray();
        $this->assertEquals(['draft'], $statuses);
    }

    // ─── Store ────────────────────────────────────────────────────────────────

    public function test_user_with_permission_can_create_journal_entry(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['journal_entries.create']);

        $account1 = ChartOfAccount::factory()->create(['company_id' => $company->id]);
        $account2 = ChartOfAccount::factory()->create(['company_id' => $company->id]);

        $lines = [
            $this->journalLinePayload($account1, 100, 0),
            $this->journalLinePayload($account2, 0, 100),
        ];

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/journal-entries', $this->journalEntryPayload($lines));

        $response->assertCreated();
        $response->assertJsonPath('data.reference', 'JE-2024-001');
        $response->assertJsonPath('data.status', 'draft');
        $response->assertJsonPath('data.total_debit', 100);
        $response->assertJsonPath('data.total_credit', 100);

        $this->assertDatabaseHas('journal_entries', [
            'company_id' => $company->id,
            'reference'  => 'JE-2024-001',
        ]);
    }

    public function test_store_requires_create_permission(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/journal-entries', $this->journalEntryPayload());

        $response->assertForbidden();
    }

    public function test_store_requires_reference(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['journal_entries.create']);

        $payload = $this->journalEntryPayload();
        unset($payload['reference']);

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/journal-entries', $payload);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('reference');
    }

    public function test_store_requires_date(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['journal_entries.create']);

        $payload = $this->journalEntryPayload();
        unset($payload['date']);

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/journal-entries', $payload);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('date');
    }

    public function test_store_can_create_without_lines(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['journal_entries.create']);

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/journal-entries', $this->journalEntryPayload([]));

        $response->assertCreated();
        $response->assertJsonPath('data.total_debit', 0);
        $response->assertJsonPath('data.total_credit', 0);
    }

    // ─── Show ─────────────────────────────────────────────────────────────────

    public function test_user_with_permission_can_view_journal_entry(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['journal_entries.view']);

        $entry = JournalEntry::factory()->create(['company_id' => $company->id]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson("/api/journal-entries/{$entry->id}");

        $response->assertOk();
        $response->assertJsonPath('data.id', $entry->id);
        $response->assertJsonPath('data.reference', $entry->reference);
    }

    public function test_show_returns_404_for_nonexistent_entry(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['journal_entries.view']);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/journal-entries/99999');

        $response->assertNotFound();
    }

    public function test_show_requires_view_permission(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();

        $entry = JournalEntry::factory()->create(['company_id' => $company->id]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson("/api/journal-entries/{$entry->id}");

        $response->assertForbidden();
    }

    // ─── Update ───────────────────────────────────────────────────────────────

    public function test_user_with_permission_can_update_draft_entry(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['journal_entries.update']);

        $entry = JournalEntry::factory()->create(['company_id' => $company->id, 'status' => 'draft']);

        $response = $this->withHeaders($this->authHeaders($user))
            ->putJson("/api/journal-entries/{$entry->id}", [
                'description' => 'Updated description',
            ]);

        $response->assertOk();
        $response->assertJsonPath('data.description', 'Updated description');

        $this->assertDatabaseHas('journal_entries', [
            'id'          => $entry->id,
            'description' => 'Updated description',
        ]);
    }

    public function test_update_cannot_modify_posted_entry(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['journal_entries.update']);

        $entry = JournalEntry::factory()->create(['company_id' => $company->id, 'status' => 'posted']);

        $response = $this->withHeaders($this->authHeaders($user))
            ->putJson("/api/journal-entries/{$entry->id}", [
                'description' => 'Hacked',
            ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('status');
    }

    public function test_update_can_sync_lines(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['journal_entries.update']);

        $account1 = ChartOfAccount::factory()->create(['company_id' => $company->id]);
        $account2 = ChartOfAccount::factory()->create(['company_id' => $company->id]);

        $entry = JournalEntry::factory()->create(['company_id' => $company->id, 'status' => 'draft']);

        $newLines = [
            $this->journalLinePayload($account1, 50, 0),
            $this->journalLinePayload($account2, 0, 50),
        ];

        $response = $this->withHeaders($this->authHeaders($user))
            ->putJson("/api/journal-entries/{$entry->id}", [
                'lines' => $newLines,
            ]);

        $response->assertOk();
        $response->assertJsonPath('data.total_debit', 50);
        $response->assertJsonPath('data.total_credit', 50);
        $this->assertCount(2, $response->json('data.lines'));
    }

    public function test_update_requires_update_permission(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();

        $entry = JournalEntry::factory()->create(['company_id' => $company->id, 'status' => 'draft']);

        $response = $this->withHeaders($this->authHeaders($user))
            ->putJson("/api/journal-entries/{$entry->id}", ['description' => 'Hacked']);

        $response->assertForbidden();
    }

    // ─── Destroy ──────────────────────────────────────────────────────────────

    public function test_user_with_permission_can_soft_delete_draft_entry(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['journal_entries.delete']);

        $entry = JournalEntry::factory()->create(['company_id' => $company->id, 'status' => 'draft']);

        $response = $this->withHeaders($this->authHeaders($user))
            ->deleteJson("/api/journal-entries/{$entry->id}");

        $response->assertNoContent();

        $this->assertSoftDeleted('journal_entries', ['id' => $entry->id]);
    }

    public function test_destroy_cannot_delete_posted_entry(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['journal_entries.delete']);

        $entry = JournalEntry::factory()->create(['company_id' => $company->id, 'status' => 'posted']);

        $response = $this->withHeaders($this->authHeaders($user))
            ->deleteJson("/api/journal-entries/{$entry->id}");

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('status');
    }

    public function test_destroy_requires_delete_permission(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();

        $entry = JournalEntry::factory()->create(['company_id' => $company->id, 'status' => 'draft']);

        $response = $this->withHeaders($this->authHeaders($user))
            ->deleteJson("/api/journal-entries/{$entry->id}");

        $response->assertForbidden();
    }

    // ─── Post (Debit = Credit Validation) ─────────────────────────────────────

    public function test_post_transitions_draft_to_posted(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['journal_entries.create']);

        $account1 = ChartOfAccount::factory()->create(['company_id' => $company->id]);
        $account2 = ChartOfAccount::factory()->create(['company_id' => $company->id]);

        $lines = [
            $this->journalLinePayload($account1, 100, 0),
            $this->journalLinePayload($account2, 0, 100),
        ];

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/journal-entries', $this->journalEntryPayload($lines));

        $entry = JournalEntry::find($response->json('data.id'));

        $postResponse = $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/journal-entries/{$entry->id}/post");

        $postResponse->assertOk();
        $postResponse->assertJsonPath('data.status', 'posted');
        $postResponse->assertJsonPath('data.posted_by', $user->id);
        $this->assertNotNull($postResponse->json('data.posted_at'));

        $this->assertDatabaseHas('journal_entries', [
            'id'     => $entry->id,
            'status' => 'posted',
        ]);
    }

    public function test_post_validates_debit_equals_credit(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['journal_entries.create']);

        $account1 = ChartOfAccount::factory()->create(['company_id' => $company->id]);
        $account2 = ChartOfAccount::factory()->create(['company_id' => $company->id]);

        // Unbalanced: 100 debit vs 50 credit
        $lines = [
            $this->journalLinePayload($account1, 100, 0),
            $this->journalLinePayload($account2, 0, 50),
        ];

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/journal-entries', $this->journalEntryPayload($lines));

        $entry = JournalEntry::find($response->json('data.id'));

        $postResponse = $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/journal-entries/{$entry->id}/post");

        $postResponse->assertUnprocessable();
        $postResponse->assertJsonValidationErrors('lines');
        $this->assertStringContainsString('must equal', $postResponse->json('errors.lines.0'));
    }

    public function test_post_requires_at_least_one_line(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['journal_entries.create']);

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/journal-entries', $this->journalEntryPayload([]));

        $entry = JournalEntry::find($response->json('data.id'));

        $postResponse = $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/journal-entries/{$entry->id}/post");

        $postResponse->assertUnprocessable();
        $postResponse->assertJsonValidationErrors('lines');
    }

    public function test_post_cannot_post_already_posted_entry(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['journal_entries.create']);

        $account1 = ChartOfAccount::factory()->create(['company_id' => $company->id]);
        $account2 = ChartOfAccount::factory()->create(['company_id' => $company->id]);

        $lines = [
            $this->journalLinePayload($account1, 100, 0),
            $this->journalLinePayload($account2, 0, 100),
        ];

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/journal-entries', $this->journalEntryPayload($lines));

        $entry = JournalEntry::find($response->json('data.id'));

        // Post once
        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/journal-entries/{$entry->id}/post")
            ->assertOk();

        // Try to post again
        $postResponse = $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/journal-entries/{$entry->id}/post");

        $postResponse->assertUnprocessable();
        $postResponse->assertJsonValidationErrors('status');
    }

    public function test_post_with_multiple_lines_validates_total_debit_credit(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['journal_entries.create']);

        $account1 = ChartOfAccount::factory()->create(['company_id' => $company->id]);
        $account2 = ChartOfAccount::factory()->create(['company_id' => $company->id]);
        $account3 = ChartOfAccount::factory()->create(['company_id' => $company->id]);

        // Multiple lines: 50 + 50 debit = 100 credit
        $lines = [
            $this->journalLinePayload($account1, 50, 0),
            $this->journalLinePayload($account2, 50, 0),
            $this->journalLinePayload($account3, 0, 100),
        ];

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/journal-entries', $this->journalEntryPayload($lines));

        $entry = JournalEntry::find($response->json('data.id'));

        $postResponse = $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/journal-entries/{$entry->id}/post");

        $postResponse->assertOk();
        $postResponse->assertJsonPath('data.status', 'posted');
    }

    public function test_post_with_decimal_amounts_validates_correctly(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['journal_entries.create']);

        $account1 = ChartOfAccount::factory()->create(['company_id' => $company->id]);
        $account2 = ChartOfAccount::factory()->create(['company_id' => $company->id]);

        // Decimal amounts: 123.45 debit = 123.45 credit
        $lines = [
            $this->journalLinePayload($account1, 123.45, 0),
            $this->journalLinePayload($account2, 0, 123.45),
        ];

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/journal-entries', $this->journalEntryPayload($lines));

        $entry = JournalEntry::find($response->json('data.id'));

        $postResponse = $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/journal-entries/{$entry->id}/post");

        $postResponse->assertOk();
        $postResponse->assertJsonPath('data.status', 'posted');
    }

    // ─── Cancel ───────────────────────────────────────────────────────────────

    public function test_user_can_cancel_draft_entry(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['journal_entries.create']);

        $entry = JournalEntry::factory()->create(['company_id' => $company->id, 'status' => 'draft']);

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/journal-entries/{$entry->id}/cancel", [
                'reason' => 'Duplicate entry',
            ]);

        $response->assertOk();
        $response->assertJsonPath('data.status', 'cancelled');
        $response->assertJsonPath('data.cancelled_by', $user->id);
        $this->assertNotNull($response->json('data.cancelled_at'));
    }

    public function test_cancel_cannot_cancel_posted_entry(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['journal_entries.create']);

        $entry = JournalEntry::factory()->create(['company_id' => $company->id, 'status' => 'posted']);

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/journal-entries/{$entry->id}/cancel");

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('status');
    }

    // ─── Tenant Isolation ─────────────────────────────────────────────────────

    public function test_user_from_company_b_cannot_view_entry_from_company_a(): void
    {
        ['user' => $userA, 'company' => $companyA] = $this->setUpCompanyAndUser();
        ['user' => $userB, 'company' => $companyB] = $this->setUpCompanyAndUser();

        $this->giveUserPermissions($userB, $companyB, ['journal_entries.view']);

        $entry = JournalEntry::factory()->create(['company_id' => $companyA->id]);

        $response = $this->withHeaders($this->authHeaders($userB))
            ->getJson("/api/journal-entries/{$entry->id}");

        $this->assertContains($response->status(), [403, 404]);
    }

    public function test_user_from_company_b_cannot_update_entry_from_company_a(): void
    {
        ['user' => $userA, 'company' => $companyA] = $this->setUpCompanyAndUser();
        ['user' => $userB, 'company' => $companyB] = $this->setUpCompanyAndUser();

        $this->giveUserPermissions($userB, $companyB, ['journal_entries.update']);

        $entry = JournalEntry::factory()->create(['company_id' => $companyA->id, 'status' => 'draft']);

        $response = $this->withHeaders($this->authHeaders($userB))
            ->putJson("/api/journal-entries/{$entry->id}", ['description' => 'Hacked']);

        $this->assertContains($response->status(), [403, 404]);
    }

    public function test_index_does_not_leak_entries_across_companies(): void
    {
        ['user' => $userA, 'company' => $companyA] = $this->setUpCompanyAndUser();
        ['user' => $userB, 'company' => $companyB] = $this->setUpCompanyAndUser();

        $this->giveUserPermissions($userA, $companyA, ['journal_entries.view_any']);
        $this->giveUserPermissions($userB, $companyB, ['journal_entries.view_any']);

        JournalEntry::factory()->count(2)->create(['company_id' => $companyA->id]);
        JournalEntry::factory()->count(3)->create(['company_id' => $companyB->id]);

        $responseA = $this->withHeaders($this->authHeaders($userA))
            ->getJson('/api/journal-entries');

        $responseB = $this->withHeaders($this->authHeaders($userB))
            ->getJson('/api/journal-entries');

        $responseA->assertOk();
        $responseB->assertOk();
        $this->assertCount(2, $responseA->json('data'));
        $this->assertCount(3, $responseB->json('data'));
    }
}
