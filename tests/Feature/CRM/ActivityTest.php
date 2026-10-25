<?php

declare(strict_types=1);

namespace Tests\Feature\CRM;

use App\Models\CRM\Activity;
use App\Models\CRM\Contact;
use App\Models\CRM\Lead;
use Tests\TestCase;

class ActivityTest extends TestCase
{
    use InteractsWithCrm;

    // ─── CRUD ─────────────────────────────────────────────────────────────────

    public function test_can_list_and_filter_activities(): void
    {
        ['company' => $company, 'headers' => $headers] = $this->actingWithPermissions(['activities.view_any']);
        Activity::factory()->create(['company_id' => $company->id, 'type' => 'call']);
        Activity::factory()->create(['company_id' => $company->id, 'type' => 'email']);

        $this->withHeaders($headers)->getJson('/api/activities')->assertOk()->assertJsonCount(2, 'data');
        $this->withHeaders($headers)->getJson('/api/activities?type=call')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', 'call');
    }

    public function test_index_requires_permission(): void
    {
        ['headers' => $headers] = $this->actingWithPermissions([]);

        $this->withHeaders($headers)->getJson('/api/activities')->assertForbidden();
    }

    public function test_can_create_activity_linked_to_lead(): void
    {
        ['user' => $user, 'company' => $company, 'headers' => $headers] = $this->actingWithPermissions(['activities.create']);
        $lead = Lead::factory()->create(['company_id' => $company->id]);

        $this->withHeaders($headers)->postJson('/api/activities', [
            'type'         => 'call',
            'subject'      => 'Discovery call',
            'related_type' => 'lead',
            'related_id'   => $lead->id,
            'due_date'     => now()->addDay()->toISOString(),
            'priority'     => 'high',
        ])->assertCreated()
            ->assertJsonPath('data.related_type', 'lead')
            ->assertJsonPath('data.related_id', $lead->id)
            ->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseHas('activities', [
            'related_type' => Lead::class,
            'related_id'   => $lead->id,
            'created_by'   => $user->id,
        ]);
    }

    public function test_create_rejects_related_record_from_another_company(): void
    {
        ['headers' => $headers] = $this->actingWithPermissions(['activities.create']);
        $foreignContact = Contact::factory()->create();

        $this->withHeaders($headers)->postJson('/api/activities', [
            'type'         => 'note',
            'subject'      => 'x',
            'related_type' => 'contact',
            'related_id'   => $foreignContact->id,
        ])->assertUnprocessable()->assertJsonValidationErrors(['related_id']);
    }

    public function test_create_validates_input(): void
    {
        ['headers' => $headers] = $this->actingWithPermissions(['activities.create']);

        $this->withHeaders($headers)->postJson('/api/activities', [
            'type'         => 'fax',
            'related_type' => 'invoice',
            'related_id'   => 1,
        ])->assertUnprocessable()->assertJsonValidationErrors(['type', 'subject', 'related_type']);
    }

    public function test_can_filter_by_related_record(): void
    {
        ['company' => $company, 'headers' => $headers] = $this->actingWithPermissions(['activities.view_any']);
        $contact = Contact::factory()->create(['company_id' => $company->id]);
        Activity::factory()->create(['company_id' => $company->id, 'related_type' => Contact::class, 'related_id' => $contact->id]);
        Activity::factory()->create(['company_id' => $company->id]);

        $this->withHeaders($headers)->getJson("/api/activities?related_type=contact&related_id={$contact->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.related_type', 'contact');
    }

    public function test_can_update_and_delete_activity(): void
    {
        ['company' => $company, 'headers' => $headers] = $this->actingWithPermissions($this->modulePermissions('activities'));
        $activity = Activity::factory()->create(['company_id' => $company->id]);

        $this->withHeaders($headers)->putJson("/api/activities/{$activity->id}", ['status' => 'in_progress'])
            ->assertOk()
            ->assertJsonPath('data.status', 'in_progress');

        $this->withHeaders($headers)->deleteJson("/api/activities/{$activity->id}")->assertNoContent();
        $this->assertDatabaseMissing('activities', ['id' => $activity->id]);
    }

    public function test_activities_are_isolated_between_companies(): void
    {
        ['user' => $userA, 'company' => $companyA] = $this->actingWithPermissions(['activities.view']);
        ['user' => $userB] = $this->actingWithPermissions(['activities.view']);
        $activity = Activity::factory()->create(['company_id' => $companyA->id]);

        $this->assertTenantIsolation('/api/activities', $userA, $userB, $activity->id);
    }

    // ─── Complete ─────────────────────────────────────────────────────────────

    public function test_can_complete_activity_with_outcome(): void
    {
        ['company' => $company, 'headers' => $headers] = $this->actingWithPermissions(['activities.complete']);
        $activity = Activity::factory()->create(['company_id' => $company->id]);

        $this->withHeaders($headers)->postJson("/api/activities/{$activity->id}/complete", ['outcome' => 'Sent proposal'])
            ->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.outcome', 'Sent proposal');

        $this->assertNotNull($activity->fresh()->completed_at);
    }

    public function test_completed_activity_cannot_be_completed_or_updated(): void
    {
        ['company' => $company, 'headers' => $headers] = $this->actingWithPermissions(['activities.complete', 'activities.update']);
        $activity = Activity::factory()->completed()->create(['company_id' => $company->id]);

        $this->withHeaders($headers)->postJson("/api/activities/{$activity->id}/complete")->assertUnprocessable();
        $this->withHeaders($headers)->putJson("/api/activities/{$activity->id}", ['subject' => 'x'])->assertUnprocessable();
    }

    // ─── Overdue / Upcoming ───────────────────────────────────────────────────

    public function test_overdue_lists_only_open_past_due_activities(): void
    {
        ['company' => $company, 'headers' => $headers] = $this->actingWithPermissions(['activities.view_any']);
        $overdue = Activity::factory()->overdue()->create(['company_id' => $company->id]);
        Activity::factory()->overdue()->completed()->create(['company_id' => $company->id]);
        Activity::factory()->create(['company_id' => $company->id, 'due_date' => now()->addDays(3)]);
        Activity::factory()->overdue()->create(); // other company

        $this->withHeaders($headers)->getJson('/api/activities/overdue')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $overdue->id)
            ->assertJsonPath('data.0.is_overdue', true);
    }

    public function test_overdue_can_be_limited_to_my_activities(): void
    {
        ['user' => $user, 'company' => $company, 'headers' => $headers] = $this->actingWithPermissions(['activities.view_any']);
        Activity::factory()->overdue()->create(['company_id' => $company->id, 'assigned_to' => $user->id]);
        Activity::factory()->overdue()->create(['company_id' => $company->id]);

        $this->withHeaders($headers)->getJson('/api/activities/overdue?mine=1')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_upcoming_respects_day_window(): void
    {
        ['company' => $company, 'headers' => $headers] = $this->actingWithPermissions(['activities.view_any']);
        Activity::factory()->create(['company_id' => $company->id, 'due_date' => now()->addDays(2)]);
        Activity::factory()->create(['company_id' => $company->id, 'due_date' => now()->addDays(10)]);
        Activity::factory()->overdue()->create(['company_id' => $company->id]);

        $this->withHeaders($headers)->getJson('/api/activities/upcoming')->assertOk()->assertJsonCount(1, 'data');
        $this->withHeaders($headers)->getJson('/api/activities/upcoming?days=14')->assertOk()->assertJsonCount(2, 'data');
    }
}
