<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Models\CRM\Opportunity;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\CRM\InteractsWithCrm;
use Tests\TestCase;

class OpportunityPagesTest extends TestCase
{
    use InteractsWithCrm;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_index_defaults_to_open_opportunities_with_forecast(): void
    {
        ['user' => $user, 'company' => $company] = $this->actingWithPermissions(['opportunities.view_any']);
        Opportunity::factory()->create(['company_id' => $company->id]);
        Opportunity::factory()->won()->create(['company_id' => $company->id]);

        $this->actingAs($user)->get('/crm/opportunities')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('CRM/Opportunities/Index')
                ->where('filters.stage', 'open')
                ->has('opportunities.data', 1)
                ->has('forecast.periods', 3)
                ->where('pipeline.open_count', 1));

        $this->actingAs($user)->get('/crm/opportunities?stage=closed_won')
            ->assertInertia(fn (Assert $page) => $page->has('opportunities.data', 1)->where('opportunities.data.0.stage', 'closed_won'));
    }

    public function test_show_and_stage_actions(): void
    {
        ['user' => $user, 'company' => $company] = $this->actingWithPermissions([
            'opportunities.view', 'opportunities.advance', 'opportunities.win',
        ]);
        $opportunity = Opportunity::factory()->create(['company_id' => $company->id, 'value' => 10000]);

        $this->actingAs($user)->get("/crm/opportunities/{$opportunity->id}")
            ->assertInertia(fn (Assert $page) => $page
                ->component('CRM/Opportunities/Show')
                ->where('opportunity.data.stage', 'prospecting')
                ->has('stages', 4));

        $this->actingAs($user)->post("/crm/opportunities/{$opportunity->id}/advance")->assertSessionHasNoErrors();
        $this->assertEquals('qualification', $opportunity->fresh()->stage);

        $this->actingAs($user)->post("/crm/opportunities/{$opportunity->id}/win", ['value' => 12000])->assertSessionHasNoErrors();
        $this->assertEquals('closed_won', $opportunity->fresh()->stage);
        $this->assertEquals('12000.00', $opportunity->fresh()->value);

        $this->actingAs($user)->post("/crm/opportunities/{$opportunity->id}/advance")->assertSessionHasErrors('stage');
    }

    public function test_lose_requires_reason(): void
    {
        ['user' => $user, 'company' => $company] = $this->actingWithPermissions(['opportunities.lose']);
        $opportunity = Opportunity::factory()->create(['company_id' => $company->id]);

        $this->actingAs($user)->post("/crm/opportunities/{$opportunity->id}/lose")->assertSessionHasErrors('lost_reason');

        $this->actingAs($user)->post("/crm/opportunities/{$opportunity->id}/lose", ['lost_reason' => 'Prix'])->assertSessionHasNoErrors();
        $this->assertEquals('closed_lost', $opportunity->fresh()->stage);
    }

    public function test_win_requires_permission(): void
    {
        ['user' => $user, 'company' => $company] = $this->actingWithPermissions($this->modulePermissions('opportunities'));
        $opportunity = Opportunity::factory()->create(['company_id' => $company->id]);

        $this->actingAs($user)->post("/crm/opportunities/{$opportunity->id}/win")->assertForbidden();
    }
}
