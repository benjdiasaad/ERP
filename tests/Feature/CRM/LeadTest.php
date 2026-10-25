<?php

declare(strict_types=1);

namespace Tests\Feature\CRM;

use App\Models\Auth\User;
use App\Models\CRM\Contact;
use App\Models\CRM\Lead;
use App\Models\CRM\Opportunity;
use App\Models\Sales\Customer;
use Tests\TestCase;

class LeadTest extends TestCase
{
    use InteractsWithCrm;

    // ─── CRUD ─────────────────────────────────────────────────────────────────

    public function test_can_list_and_filter_leads(): void
    {
        ['company' => $company, 'headers' => $headers] = $this->actingWithPermissions(['leads.view_any']);
        Lead::factory()->create(['company_id' => $company->id, 'status' => 'new']);
        Lead::factory()->qualified()->create(['company_id' => $company->id]);

        $this->withHeaders($headers)->getJson('/api/leads')->assertOk()->assertJsonCount(2, 'data');

        $this->withHeaders($headers)->getJson('/api/leads?status=qualified')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'qualified');
    }

    public function test_index_requires_permission(): void
    {
        ['headers' => $headers] = $this->actingWithPermissions([]);

        $this->withHeaders($headers)->getJson('/api/leads')->assertForbidden();
    }

    public function test_create_generates_sequential_reference(): void
    {
        ['user' => $user, 'company' => $company, 'headers' => $headers] = $this->actingWithPermissions(['leads.create']);
        $contact = Contact::factory()->create(['company_id' => $company->id]);
        $year = date('Y');

        $this->withHeaders($headers)->postJson('/api/leads', [
            'title'           => 'ERP rollout',
            'contact_id'      => $contact->id,
            'estimated_value' => 50000,
            'source'          => 'website',
        ])->assertCreated()
            ->assertJsonPath('data.reference', "LD-{$year}-00001")
            ->assertJsonPath('data.status', 'new')
            ->assertJsonPath('data.contact.id', $contact->id);

        $this->withHeaders($headers)->postJson('/api/leads', ['title' => 'Second'])
            ->assertCreated()
            ->assertJsonPath('data.reference', "LD-{$year}-00002");

        $this->assertDatabaseHas('leads', ['title' => 'ERP rollout', 'created_by' => $user->id, 'company_id' => $company->id]);
    }

    public function test_create_validates_input(): void
    {
        ['headers' => $headers] = $this->actingWithPermissions(['leads.create']);

        $this->withHeaders($headers)->postJson('/api/leads', [
            'probability' => 150,
            'status'      => 'converted',
        ])->assertUnprocessable()->assertJsonValidationErrors(['title', 'probability', 'status']);
    }

    public function test_create_rejects_assignee_outside_company(): void
    {
        ['headers' => $headers] = $this->actingWithPermissions(['leads.create']);
        $outsider = User::factory()->create();

        $this->withHeaders($headers)->postJson('/api/leads', ['title' => 'X', 'assigned_to' => $outsider->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['assigned_to']);
    }

    public function test_can_update_and_delete_open_lead(): void
    {
        ['company' => $company, 'headers' => $headers] = $this->actingWithPermissions($this->modulePermissions('leads'));
        $lead = Lead::factory()->create(['company_id' => $company->id]);

        $this->withHeaders($headers)->putJson("/api/leads/{$lead->id}", ['status' => 'contacted', 'probability' => 30])
            ->assertOk()
            ->assertJsonPath('data.status', 'contacted')
            ->assertJsonPath('data.probability', 30);

        $this->withHeaders($headers)->deleteJson("/api/leads/{$lead->id}")->assertNoContent();
        $this->assertSoftDeleted('leads', ['id' => $lead->id]);
    }

    public function test_marking_lead_lost_requires_reason(): void
    {
        ['company' => $company, 'headers' => $headers] = $this->actingWithPermissions(['leads.update']);
        $lead = Lead::factory()->create(['company_id' => $company->id]);

        $this->withHeaders($headers)->putJson("/api/leads/{$lead->id}", ['status' => 'lost'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['lost_reason']);

        $this->withHeaders($headers)->putJson("/api/leads/{$lead->id}", ['status' => 'lost', 'lost_reason' => 'Budget cut'])
            ->assertOk()
            ->assertJsonPath('data.status', 'lost');
    }

    public function test_converted_lead_cannot_be_updated_or_deleted(): void
    {
        ['company' => $company, 'headers' => $headers] = $this->actingWithPermissions($this->modulePermissions('leads'));
        $lead = Lead::factory()->converted()->create(['company_id' => $company->id]);

        $this->withHeaders($headers)->putJson("/api/leads/{$lead->id}", ['title' => 'New'])->assertUnprocessable();
        $this->withHeaders($headers)->deleteJson("/api/leads/{$lead->id}")->assertUnprocessable();
    }

    public function test_leads_are_isolated_between_companies(): void
    {
        ['user' => $userA, 'company' => $companyA] = $this->actingWithPermissions(['leads.view']);
        ['user' => $userB] = $this->actingWithPermissions(['leads.view']);
        $lead = Lead::factory()->create(['company_id' => $companyA->id]);

        $this->assertTenantIsolation('/api/leads', $userA, $userB, $lead->id);
    }

    // ─── Qualify / Assign ─────────────────────────────────────────────────────

    public function test_can_qualify_lead(): void
    {
        ['company' => $company, 'headers' => $headers] = $this->actingWithPermissions(['leads.qualify']);
        $lead = Lead::factory()->contacted()->create(['company_id' => $company->id]);

        $this->withHeaders($headers)->postJson("/api/leads/{$lead->id}/qualify", ['probability' => 40])
            ->assertOk()
            ->assertJsonPath('data.status', 'qualified')
            ->assertJsonPath('data.probability', 40);

        $this->assertNotNull($lead->fresh()->qualified_at);
    }

    public function test_cannot_qualify_lost_lead(): void
    {
        ['company' => $company, 'headers' => $headers] = $this->actingWithPermissions(['leads.qualify']);
        $lead = Lead::factory()->lost()->create(['company_id' => $company->id]);

        $this->withHeaders($headers)->postJson("/api/leads/{$lead->id}/qualify")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    }

    public function test_can_assign_and_unassign_lead(): void
    {
        ['company' => $company, 'headers' => $headers] = $this->actingWithPermissions(['leads.assign']);
        $colleague = User::factory()->create(['current_company_id' => $company->id]);
        $company->users()->attach($colleague->id, ['is_default' => true, 'joined_at' => now()]);
        $lead = Lead::factory()->create(['company_id' => $company->id]);

        $this->withHeaders($headers)->postJson("/api/leads/{$lead->id}/assign", ['assigned_to' => $colleague->id])
            ->assertOk()
            ->assertJsonPath('data.assigned_to', $colleague->id)
            ->assertJsonPath('data.assignee.id', $colleague->id);

        $this->withHeaders($headers)->postJson("/api/leads/{$lead->id}/assign", ['assigned_to' => null])
            ->assertOk()
            ->assertJsonPath('data.assigned_to', null);
    }

    public function test_assign_requires_assign_permission(): void
    {
        ['user' => $user, 'company' => $company, 'headers' => $headers] = $this->actingWithPermissions(['leads.update']);
        $lead = Lead::factory()->create(['company_id' => $company->id]);

        $this->withHeaders($headers)->postJson("/api/leads/{$lead->id}/assign", ['assigned_to' => $user->id])
            ->assertForbidden();
    }

    // ─── Convert ──────────────────────────────────────────────────────────────

    public function test_convert_qualified_lead_creates_opportunity(): void
    {
        ['company' => $company, 'headers' => $headers] = $this->actingWithPermissions(['leads.convert']);
        $customer = Customer::factory()->create(['company_id' => $company->id, 'name' => 'Acme SARL']);
        $contact = Contact::factory()->create(['company_id' => $company->id, 'customer_id' => $customer->id]);
        $lead = Lead::factory()->qualified()->create([
            'company_id'      => $company->id,
            'contact_id'      => $contact->id,
            'title'           => 'Fleet renewal',
            'estimated_value' => 80000,
            'probability'     => 20,
        ]);

        $response = $this->withHeaders($headers)->postJson("/api/leads/{$lead->id}/convert");

        $response->assertCreated()
            ->assertJsonPath('data.lead.status', 'converted')
            ->assertJsonPath('data.opportunity.title', 'Fleet renewal')
            ->assertJsonPath('data.opportunity.stage', 'prospecting')
            ->assertJsonPath('data.opportunity.lead_id', $lead->id)
            ->assertJsonPath('data.opportunity.customer_id', $customer->id)
            ->assertJsonPath('data.opportunity.weighted_value', '16000.00');

        $opportunityId = $response->json('data.opportunity.id');
        $this->assertEquals($opportunityId, $lead->fresh()->converted_to_opportunity_id);
        $this->assertStringStartsWith('OPP-' . date('Y') . '-', Opportunity::find($opportunityId)->reference);
    }

    public function test_convert_accepts_overrides(): void
    {
        ['company' => $company, 'headers' => $headers] = $this->actingWithPermissions(['leads.convert']);
        $lead = Lead::factory()->qualified()->create(['company_id' => $company->id, 'estimated_value' => 1000]);

        $this->withHeaders($headers)->postJson("/api/leads/{$lead->id}/convert", [
            'title' => 'Override',
            'value' => 5000,
            'stage' => 'proposal',
        ])->assertCreated()
            ->assertJsonPath('data.opportunity.title', 'Override')
            ->assertJsonPath('data.opportunity.value', '5000.00')
            ->assertJsonPath('data.opportunity.stage', 'proposal');
    }

    public function test_cannot_convert_unqualified_lead(): void
    {
        ['company' => $company, 'headers' => $headers] = $this->actingWithPermissions(['leads.convert']);
        $lead = Lead::factory()->create(['company_id' => $company->id, 'status' => 'new']);

        $this->withHeaders($headers)->postJson("/api/leads/{$lead->id}/convert")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);

        $this->assertDatabaseCount('opportunities', 0);
    }

    public function test_cannot_convert_lead_twice(): void
    {
        ['company' => $company, 'headers' => $headers] = $this->actingWithPermissions(['leads.convert']);
        $lead = Lead::factory()->qualified()->create(['company_id' => $company->id]);

        $this->withHeaders($headers)->postJson("/api/leads/{$lead->id}/convert")->assertCreated();
        $this->withHeaders($headers)->postJson("/api/leads/{$lead->id}/convert")->assertUnprocessable();

        $this->assertDatabaseCount('opportunities', 1);
    }

    // ─── Pipeline ─────────────────────────────────────────────────────────────

    public function test_pipeline_returns_stats_per_status(): void
    {
        ['company' => $company, 'headers' => $headers] = $this->actingWithPermissions(['leads.view_any']);
        Lead::factory()->count(2)->create(['company_id' => $company->id, 'status' => 'new', 'estimated_value' => 1000]);
        Lead::factory()->converted()->create(['company_id' => $company->id, 'estimated_value' => 3000]);
        Lead::factory()->lost()->create(['company_id' => $company->id, 'estimated_value' => 500]);
        Lead::factory()->create(['estimated_value' => 99999]); // other company

        $this->withHeaders($headers)->getJson('/api/leads/pipeline')
            ->assertOk()
            ->assertJsonPath('data.stages.new.count', 2)
            ->assertJsonPath('data.stages.new.estimated_value', 2000)
            ->assertJsonPath('data.stages.converted.count', 1)
            ->assertJsonPath('data.stages.qualified.count', 0)
            ->assertJsonPath('data.total_count', 4)
            ->assertJsonPath('data.total_estimated_value', 5500)
            ->assertJsonPath('data.conversion_rate', 50);
    }
}
