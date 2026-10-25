<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Models\Auth\User;
use App\Models\CRM\Lead;
use App\Models\CRM\Opportunity;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\CRM\InteractsWithCrm;
use Tests\TestCase;

class LeadPagesTest extends TestCase
{
    use InteractsWithCrm;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_index_filters_by_status_and_shows_pipeline(): void
    {
        ['user' => $user, 'company' => $company] = $this->actingWithPermissions(['leads.view_any']);
        Lead::factory()->create(['company_id' => $company->id, 'status' => 'new']);
        Lead::factory()->qualified()->create(['company_id' => $company->id]);
        Lead::factory()->create(); // other company

        $this->actingAs($user)->get('/crm/leads?status=qualified')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('CRM/Leads/Index')
                ->has('leads.data', 1)
                ->where('leads.data.0.status', 'qualified')
                ->where('pipeline.total_count', 2));
    }

    public function test_index_requires_permission(): void
    {
        ['user' => $user] = $this->actingWithPermissions([]);

        $this->actingAs($user)->get('/crm/leads')->assertForbidden();
    }

    public function test_can_create_lead(): void
    {
        ['user' => $user] = $this->actingWithPermissions(['leads.create', 'leads.view']);

        $this->actingAs($user)->post('/crm/leads', ['title' => 'Nouveau parc PC', 'estimated_value' => 50000])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('leads', ['title' => 'Nouveau parc PC', 'status' => 'new']);
    }

    public function test_qualify_then_convert_redirects_to_opportunity(): void
    {
        ['user' => $user, 'company' => $company] = $this->actingWithPermissions(['leads.qualify', 'leads.convert', 'leads.view']);
        $lead = Lead::factory()->contacted()->create(['company_id' => $company->id, 'estimated_value' => 20000]);

        $this->actingAs($user)->from("/crm/leads/{$lead->id}")->post("/crm/leads/{$lead->id}/qualify")
            ->assertRedirect("/crm/leads/{$lead->id}");
        $this->assertEquals('qualified', $lead->fresh()->status);

        $response = $this->actingAs($user)->post("/crm/leads/{$lead->id}/convert", ['stage' => 'proposal']);

        $opportunity = Opportunity::where('lead_id', $lead->id)->firstOrFail();
        $response->assertRedirect(route('crm.opportunities.show', $opportunity));
        $this->assertEquals('proposal', $opportunity->stage);
        $this->assertEquals('converted', $lead->fresh()->status);
    }

    public function test_refused_conversion_returns_error_to_page(): void
    {
        ['user' => $user, 'company' => $company] = $this->actingWithPermissions(['leads.convert']);
        $lead = Lead::factory()->create(['company_id' => $company->id, 'status' => 'new']);

        $this->actingAs($user)->from("/crm/leads/{$lead->id}")->post("/crm/leads/{$lead->id}/convert")
            ->assertRedirect("/crm/leads/{$lead->id}")
            ->assertSessionHasErrors('status');

        $this->assertDatabaseCount('opportunities', 0);
    }

    public function test_assign_only_to_company_users(): void
    {
        ['user' => $user, 'company' => $company] = $this->actingWithPermissions(['leads.assign']);
        $lead = Lead::factory()->create(['company_id' => $company->id]);
        $outsider = User::factory()->create();

        $this->actingAs($user)->post("/crm/leads/{$lead->id}/assign", ['assigned_to' => $user->id])->assertSessionHasNoErrors();
        $this->assertEquals($user->id, $lead->fresh()->assigned_to);

        $this->actingAs($user)->post("/crm/leads/{$lead->id}/assign", ['assigned_to' => $outsider->id])
            ->assertSessionHasErrors('assigned_to');
    }

    public function test_cannot_open_another_companys_lead(): void
    {
        ['user' => $user] = $this->actingWithPermissions(['leads.view']);
        $foreign = Lead::factory()->create();

        $this->actingAs($user)->get("/crm/leads/{$foreign->id}")->assertNotFound();
    }
}
