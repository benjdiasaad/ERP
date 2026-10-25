<?php

declare(strict_types=1);

namespace Tests\Feature\CRM;

use App\Models\CRM\Lead;
use App\Models\CRM\Opportunity;
use Carbon\Carbon;
use Tests\TestCase;

class OpportunityTest extends TestCase
{
    use InteractsWithCrm;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ─── CRUD ─────────────────────────────────────────────────────────────────

    public function test_can_list_and_filter_opportunities(): void
    {
        ['company' => $company, 'headers' => $headers] = $this->actingWithPermissions(['opportunities.view_any']);
        Opportunity::factory()->create(['company_id' => $company->id]);
        Opportunity::factory()->won()->create(['company_id' => $company->id]);

        $this->withHeaders($headers)->getJson('/api/opportunities')->assertOk()->assertJsonCount(2, 'data');
        $this->withHeaders($headers)->getJson('/api/opportunities?open=1')->assertOk()->assertJsonCount(1, 'data');
        $this->withHeaders($headers)->getJson('/api/opportunities?stage=closed_won')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.stage', 'closed_won');
    }

    public function test_index_requires_permission(): void
    {
        ['headers' => $headers] = $this->actingWithPermissions([]);

        $this->withHeaders($headers)->getJson('/api/opportunities')->assertForbidden();
    }

    public function test_create_applies_stage_probability_and_weighted_value(): void
    {
        ['company' => $company, 'headers' => $headers] = $this->actingWithPermissions(['opportunities.create']);

        $this->withHeaders($headers)->postJson('/api/opportunities', [
            'title' => 'Website redesign',
            'value' => 20000,
            'stage' => 'proposal',
        ])->assertCreated()
            ->assertJsonPath('data.reference', 'OPP-' . date('Y') . '-00001')
            ->assertJsonPath('data.stage', 'proposal')
            ->assertJsonPath('data.probability', 50)
            ->assertJsonPath('data.weighted_value', '10000.00');
    }

    public function test_create_rejects_closed_stage(): void
    {
        ['headers' => $headers] = $this->actingWithPermissions(['opportunities.create']);

        $this->withHeaders($headers)->postJson('/api/opportunities', ['title' => 'X', 'stage' => 'closed_won'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['stage']);
    }

    public function test_update_recalculates_weighted_value_and_ignores_stage(): void
    {
        ['company' => $company, 'headers' => $headers] = $this->actingWithPermissions(['opportunities.update']);
        $opportunity = Opportunity::factory()->create(['company_id' => $company->id, 'value' => 1000]);

        $this->withHeaders($headers)->putJson("/api/opportunities/{$opportunity->id}", [
            'value'       => 4000,
            'probability' => 30,
            'stage'       => 'closed_won',
        ])->assertOk()
            ->assertJsonPath('data.weighted_value', '1200.00')
            ->assertJsonPath('data.stage', 'prospecting');
    }

    public function test_closed_opportunity_cannot_be_updated(): void
    {
        ['company' => $company, 'headers' => $headers] = $this->actingWithPermissions(['opportunities.update']);
        $opportunity = Opportunity::factory()->lost()->create(['company_id' => $company->id]);

        $this->withHeaders($headers)->putJson("/api/opportunities/{$opportunity->id}", ['value' => 1])
            ->assertUnprocessable();
    }

    public function test_won_opportunity_cannot_be_deleted(): void
    {
        ['company' => $company, 'headers' => $headers] = $this->actingWithPermissions(['opportunities.delete']);
        $won = Opportunity::factory()->won()->create(['company_id' => $company->id]);
        $open = Opportunity::factory()->create(['company_id' => $company->id]);

        $this->withHeaders($headers)->deleteJson("/api/opportunities/{$won->id}")->assertUnprocessable();
        $this->withHeaders($headers)->deleteJson("/api/opportunities/{$open->id}")->assertNoContent();
        $this->assertSoftDeleted('opportunities', ['id' => $open->id]);
    }

    public function test_opportunities_are_isolated_between_companies(): void
    {
        ['user' => $userA, 'company' => $companyA] = $this->actingWithPermissions(['opportunities.view']);
        ['user' => $userB] = $this->actingWithPermissions(['opportunities.view']);
        $opportunity = Opportunity::factory()->create(['company_id' => $companyA->id]);

        $this->assertTenantIsolation('/api/opportunities', $userA, $userB, $opportunity->id);
    }

    // ─── Stage transitions ────────────────────────────────────────────────────

    public function test_advance_moves_to_next_stage(): void
    {
        ['company' => $company, 'headers' => $headers] = $this->actingWithPermissions(['opportunities.advance']);
        $opportunity = Opportunity::factory()->create(['company_id' => $company->id, 'value' => 10000]);

        $this->withHeaders($headers)->postJson("/api/opportunities/{$opportunity->id}/advance")
            ->assertOk()
            ->assertJsonPath('data.stage', 'qualification')
            ->assertJsonPath('data.probability', 25)
            ->assertJsonPath('data.weighted_value', '2500.00');
    }

    public function test_advance_can_jump_to_later_stage_with_custom_probability(): void
    {
        ['company' => $company, 'headers' => $headers] = $this->actingWithPermissions(['opportunities.advance']);
        $opportunity = Opportunity::factory()->create(['company_id' => $company->id]);

        $this->withHeaders($headers)->postJson("/api/opportunities/{$opportunity->id}/advance", [
            'stage'       => 'negotiation',
            'probability' => 80,
        ])->assertOk()
            ->assertJsonPath('data.stage', 'negotiation')
            ->assertJsonPath('data.probability', 80);
    }

    public function test_advance_cannot_move_backwards(): void
    {
        ['company' => $company, 'headers' => $headers] = $this->actingWithPermissions(['opportunities.advance']);
        $opportunity = Opportunity::factory()->stage('proposal')->create(['company_id' => $company->id]);

        $this->withHeaders($headers)->postJson("/api/opportunities/{$opportunity->id}/advance", ['stage' => 'qualification'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['stage']);
    }

    public function test_advance_from_negotiation_requires_win_or_lose(): void
    {
        ['company' => $company, 'headers' => $headers] = $this->actingWithPermissions(['opportunities.advance']);
        $opportunity = Opportunity::factory()->stage('negotiation')->create(['company_id' => $company->id]);

        $this->withHeaders($headers)->postJson("/api/opportunities/{$opportunity->id}/advance")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['stage']);
    }

    public function test_can_win_opportunity(): void
    {
        ['company' => $company, 'headers' => $headers] = $this->actingWithPermissions(['opportunities.win']);
        $opportunity = Opportunity::factory()->stage('negotiation')->create(['company_id' => $company->id, 'value' => 7000]);

        $this->withHeaders($headers)->postJson("/api/opportunities/{$opportunity->id}/win", ['value' => 7500])
            ->assertOk()
            ->assertJsonPath('data.stage', 'closed_won')
            ->assertJsonPath('data.probability', 100)
            ->assertJsonPath('data.value', '7500.00')
            ->assertJsonPath('data.weighted_value', '7500.00')
            ->assertJsonPath('data.actual_close_date', now()->toDateString());
    }

    public function test_lose_requires_reason(): void
    {
        ['company' => $company, 'headers' => $headers] = $this->actingWithPermissions(['opportunities.lose']);
        $opportunity = Opportunity::factory()->create(['company_id' => $company->id]);

        $this->withHeaders($headers)->postJson("/api/opportunities/{$opportunity->id}/lose")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['lost_reason']);

        $this->withHeaders($headers)->postJson("/api/opportunities/{$opportunity->id}/lose", ['lost_reason' => 'Price'])
            ->assertOk()
            ->assertJsonPath('data.stage', 'closed_lost')
            ->assertJsonPath('data.probability', 0)
            ->assertJsonPath('data.weighted_value', '0.00')
            ->assertJsonPath('data.lost_reason', 'Price');
    }

    public function test_closed_opportunity_cannot_be_won_or_lost_again(): void
    {
        ['company' => $company, 'headers' => $headers] = $this->actingWithPermissions(['opportunities.win', 'opportunities.lose']);
        $opportunity = Opportunity::factory()->won()->create(['company_id' => $company->id]);

        $this->withHeaders($headers)->postJson("/api/opportunities/{$opportunity->id}/win")->assertUnprocessable();
        $this->withHeaders($headers)->postJson("/api/opportunities/{$opportunity->id}/lose", ['lost_reason' => 'x'])
            ->assertUnprocessable();
    }

    public function test_win_requires_win_permission(): void
    {
        ['company' => $company, 'headers' => $headers] = $this->actingWithPermissions($this->modulePermissions('opportunities'));
        $opportunity = Opportunity::factory()->create(['company_id' => $company->id]);

        $this->withHeaders($headers)->postJson("/api/opportunities/{$opportunity->id}/win")->assertForbidden();
    }

    // ─── Full flow ────────────────────────────────────────────────────────────

    public function test_full_flow_lead_to_won_opportunity(): void
    {
        ['company' => $company, 'headers' => $headers] = $this->actingWithPermissions([
            'leads.create', 'leads.qualify', 'leads.convert',
            'opportunities.advance', 'opportunities.win', 'opportunities.view_any',
        ]);

        $leadId = $this->withHeaders($headers)->postJson('/api/leads', [
            'title'           => 'Annual maintenance contract',
            'estimated_value' => 12000,
        ])->assertCreated()->json('data.id');

        $this->withHeaders($headers)->postJson("/api/leads/{$leadId}/qualify")->assertOk();

        $opportunityId = $this->withHeaders($headers)->postJson("/api/leads/{$leadId}/convert")
            ->assertCreated()
            ->json('data.opportunity.id');

        foreach (['qualification', 'proposal', 'negotiation'] as $stage) {
            $this->withHeaders($headers)->postJson("/api/opportunities/{$opportunityId}/advance")
                ->assertOk()
                ->assertJsonPath('data.stage', $stage);
        }

        $this->withHeaders($headers)->postJson("/api/opportunities/{$opportunityId}/win")
            ->assertOk()
            ->assertJsonPath('data.stage', 'closed_won');

        $this->assertEquals('converted', Lead::find($leadId)->status);

        $this->withHeaders($headers)->getJson('/api/opportunities/pipeline')
            ->assertOk()
            ->assertJsonPath('data.stages.closed_won.count', 1)
            ->assertJsonPath('data.stages.closed_won.value', 12000)
            ->assertJsonPath('data.win_rate', 100);
    }

    // ─── Reporting ────────────────────────────────────────────────────────────

    public function test_pipeline_aggregates_open_stages(): void
    {
        ['company' => $company, 'headers' => $headers] = $this->actingWithPermissions(['opportunities.view_any']);
        Opportunity::factory()->create(['company_id' => $company->id, 'value' => 1000]);            // 10% → 100
        Opportunity::factory()->stage('proposal')->create(['company_id' => $company->id, 'value' => 2000]); // 50% → 1000
        Opportunity::factory()->won()->create(['company_id' => $company->id, 'value' => 500]);
        Opportunity::factory()->lost()->create(['company_id' => $company->id, 'value' => 300]);
        Opportunity::factory()->create(['value' => 99999]); // other company

        $this->withHeaders($headers)->getJson('/api/opportunities/pipeline')
            ->assertOk()
            ->assertJsonPath('data.stages.prospecting.count', 1)
            ->assertJsonPath('data.stages.proposal.weighted_value', 1000)
            ->assertJsonPath('data.open_count', 2)
            ->assertJsonPath('data.open_value', 3000)
            ->assertJsonPath('data.open_weighted_value', 1100)
            ->assertJsonPath('data.win_rate', 50);
    }

    public function test_forecast_groups_by_month(): void
    {
        Carbon::setTestNow('2026-10-15 10:00:00');

        ['company' => $company, 'headers' => $headers] = $this->actingWithPermissions(['opportunities.view_any']);
        Opportunity::factory()->stage('proposal')->create([
            'company_id' => $company->id, 'value' => 10000, 'expected_close_date' => '2026-11-20',
        ]);
        Opportunity::factory()->create([ // overdue → counted in current month
            'company_id' => $company->id, 'value' => 1000, 'expected_close_date' => '2026-09-01',
        ]);
        Opportunity::factory()->create([ // beyond horizon
            'company_id' => $company->id, 'value' => 5000, 'expected_close_date' => '2027-06-01',
        ]);
        Opportunity::factory()->won()->create([
            'company_id' => $company->id, 'value' => 3000, 'actual_close_date' => '2026-10-02',
        ]);

        $this->withHeaders($headers)->getJson('/api/opportunities/forecast?months=3')
            ->assertOk()
            ->assertJsonCount(3, 'data.periods')
            ->assertJsonPath('data.periods.0.period', '2026-10')
            ->assertJsonPath('data.periods.0.open_count', 1)
            ->assertJsonPath('data.periods.0.weighted_value', 100)
            ->assertJsonPath('data.periods.0.won_value', 3000)
            ->assertJsonPath('data.periods.1.period', '2026-11')
            ->assertJsonPath('data.periods.1.weighted_value', 5000)
            ->assertJsonPath('data.periods.2.open_count', 0)
            ->assertJsonPath('data.total_weighted_value', 5100)
            ->assertJsonPath('data.total_won_value', 3000);
    }

    public function test_forecast_validates_months(): void
    {
        ['headers' => $headers] = $this->actingWithPermissions(['opportunities.view_any']);

        $this->withHeaders($headers)->getJson('/api/opportunities/forecast?months=99')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['months']);
    }
}
