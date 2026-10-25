<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Models\CRM\Activity;
use App\Models\CRM\Contact;
use App\Models\CRM\Lead;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\CRM\InteractsWithCrm;
use Tests\TestCase;

class ActivityPagesTest extends TestCase
{
    use InteractsWithCrm;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_index_views(): void
    {
        ['user' => $user, 'company' => $company] = $this->actingWithPermissions(['activities.view_any']);
        $lead = Lead::factory()->create(['company_id' => $company->id]);
        Activity::factory()->overdue()->create(['company_id' => $company->id, 'related_type' => Lead::class, 'related_id' => $lead->id]);
        Activity::factory()->create(['company_id' => $company->id, 'due_date' => now()->addDays(2)]);
        Activity::factory()->completed()->create(['company_id' => $company->id]);

        $this->actingAs($user)->get('/crm/activities')
            ->assertInertia(fn (Assert $page) => $page
                ->component('CRM/Activities/Index')
                ->where('filters.view', 'todo')
                ->has('activities.data', 2)
                ->where('counts.overdue', 1));

        $this->actingAs($user)->get('/crm/activities?view=overdue')
            ->assertInertia(fn (Assert $page) => $page
                ->has('activities.data', 1)
                ->where('activities.data.0.related_type', 'lead')
                ->where('activities.data.0.related_label', "{$lead->reference} · {$lead->title}"));

        $this->actingAs($user)->get('/crm/activities?view=completed')
            ->assertInertia(fn (Assert $page) => $page->has('activities.data', 1));
    }

    public function test_create_prefills_and_redirects_to_related_record(): void
    {
        ['user' => $user, 'company' => $company] = $this->actingWithPermissions(['activities.create']);
        $contact = Contact::factory()->create(['company_id' => $company->id]);

        $this->actingAs($user)->get("/crm/activities/create?related_type=contact&related_id={$contact->id}")
            ->assertInertia(fn (Assert $page) => $page
                ->component('CRM/Activities/Create')
                ->where('defaults.related_type', 'contact')
                ->where('defaults.related_id', $contact->id)
                ->has('related.contact', 1));

        $this->actingAs($user)->post('/crm/activities', [
            'type' => 'call', 'subject' => 'Appel de suivi', 'related_type' => 'contact', 'related_id' => $contact->id,
        ])->assertRedirect(route('crm.contacts.show', $contact));

        $this->assertDatabaseHas('activities', ['subject' => 'Appel de suivi', 'related_type' => Contact::class]);
    }

    public function test_complete_activity(): void
    {
        ['user' => $user, 'company' => $company] = $this->actingWithPermissions(['activities.complete']);
        $activity = Activity::factory()->create(['company_id' => $company->id]);

        $this->actingAs($user)->post("/crm/activities/{$activity->id}/complete", ['outcome' => 'Devis envoyé'])
            ->assertSessionHasNoErrors();

        $this->assertEquals('completed', $activity->fresh()->status);
        $this->assertEquals('Devis envoyé', $activity->fresh()->outcome);

        $this->actingAs($user)->post("/crm/activities/{$activity->id}/complete")->assertSessionHasErrors('status');
    }

    public function test_cannot_touch_another_companys_activity(): void
    {
        ['user' => $user] = $this->actingWithPermissions($this->modulePermissions('activities', ['complete']));
        $foreign = Activity::factory()->create();

        $this->actingAs($user)->get("/crm/activities/{$foreign->id}/edit")->assertNotFound();
        $this->actingAs($user)->post("/crm/activities/{$foreign->id}/complete")->assertNotFound();
    }
}
