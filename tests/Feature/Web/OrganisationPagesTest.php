<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Models\Event\Event;
use App\Models\Event\EventCategory;
use App\Models\Personnel\Department;
use App\Models\Personnel\Leave;
use App\Models\Personnel\Personnel;
use App\Models\Personnel\Position;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\CRM\InteractsWithCrm;
use Tests\TestCase;

class OrganisationPagesTest extends TestCase
{
    use InteractsWithCrm;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function allPermissions(): array
    {
        return [
            ...$this->modulePermissions('personnels'),
            ...$this->modulePermissions('departments'),
            ...$this->modulePermissions('positions'),
            ...$this->modulePermissions('contracts'),
            ...$this->modulePermissions('leaves', ['approve', 'reject']),
            ...$this->modulePermissions('events', ['confirm', 'cancel', 'complete', 'postpone']),
            ...$this->modulePermissions('event_participants', ['invite']),
            ...$this->modulePermissions('event_categories'),
        ];
    }

    public function test_index_pages_render_and_are_isolated(): void
    {
        ['user' => $user, 'company' => $company] = $this->actingWithPermissions($this->allPermissions());
        Personnel::factory()->create(['company_id' => $company->id]);
        $foreign = Personnel::factory()->create();
        $foreignEvent = Event::factory()->create();

        $pages = [
            '/organisation/personnels'       => 'Personnel/Personnels/Index',
            '/organisation/departments'      => 'Personnel/Departments/Index',
            '/organisation/leaves'           => 'Personnel/Leaves/Index',
            '/organisation/events'           => 'Event/Events/Index',
            '/organisation/event-categories' => 'Event/Categories/Index',
        ];
        foreach ($pages as $url => $component) {
            $this->actingAs($user)->get($url)->assertInertia(fn (Assert $page) => $page->component($component));
        }

        $this->actingAs($user)->get('/organisation/personnels')->assertInertia(fn (Assert $page) => $page->has('personnels.data', 1));
        $this->actingAs($user)->get("/organisation/personnels/{$foreign->id}")->assertNotFound();
        $this->actingAs($user)->get("/organisation/events/{$foreignEvent->id}")->assertNotFound();
    }

    public function test_pages_require_permissions(): void
    {
        ['user' => $user] = $this->actingWithPermissions(['personnels.view_any']);

        $this->actingAs($user)->get('/organisation/personnels')->assertOk();
        $this->actingAs($user)->get('/organisation/leaves')->assertForbidden();
        $this->actingAs($user)->get('/organisation/events')->assertForbidden();
    }

    public function test_personnel_with_contract_and_company_scoped_department(): void
    {
        ['user' => $user, 'company' => $company] = $this->actingWithPermissions($this->allPermissions());
        $department = Department::factory()->create(['company_id' => $company->id]);
        $position = Position::factory()->create(['company_id' => $company->id, 'department_id' => $department->id]);
        $foreignDepartment = Department::factory()->create();

        $this->actingAs($user)->post('/organisation/personnels', ['first_name' => 'Salma', 'last_name' => 'Bennani', 'department_id' => $foreignDepartment->id])
            ->assertSessionHasErrors('department_id');
        $this->actingAs($user)->post('/organisation/personnels', [
            'first_name' => 'Salma', 'last_name' => 'Bennani', 'department_id' => $department->id, 'position_id' => $position->id,
            'employment_type' => 'full_time', 'status' => 'active', 'hire_date' => now()->toDateString(),
        ])->assertRedirect()->assertSessionHasNoErrors();
        $personnel = Personnel::where('last_name', 'Bennani')->firstOrFail();
        $this->assertNotEmpty($personnel->matricule);

        $this->actingAs($user)->post('/organisation/contracts', [
            'personnel_id' => $personnel->id, 'type' => 'CDI', 'start_date' => now()->toDateString(), 'salary' => 12000, 'status' => 'active',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $contract = $personnel->contracts()->firstOrFail();
        $this->actingAs($user)->post("/organisation/contracts/{$contract->id}/terminate", ['termination_date' => now()->addMonth()->toDateString(), 'reason' => 'Démission'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertEquals('terminated', $contract->fresh()->status);

        $this->actingAs($user)->get("/organisation/personnels/{$personnel->id}")->assertInertia(fn (Assert $page) => $page
            ->component('Personnel/Personnels/Show')->has('contracts.data', 1));
        $this->actingAs($user)->delete("/organisation/departments/{$department->id}")->assertSessionHasErrors('department');
    }

    public function test_leave_request_is_approved_once(): void
    {
        ['user' => $user, 'company' => $company] = $this->actingWithPermissions($this->allPermissions());
        $personnel = Personnel::factory()->create(['company_id' => $company->id]);

        $this->actingAs($user)->post('/organisation/leaves', [
            'personnel_id' => $personnel->id, 'type' => 'annual', 'start_date' => '2026-11-02', 'end_date' => '2026-11-06',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $leave = Leave::firstOrFail();
        $this->assertEquals('annual', $leave->leave_type);
        $this->assertEquals(5.0, (float) $leave->total_days);

        $this->actingAs($user)->post("/organisation/leaves/{$leave->id}/approve")->assertRedirect()->assertSessionHasNoErrors();
        $this->assertEquals('approved', $leave->fresh()->status);
        $this->actingAs($user)->post("/organisation/leaves/{$leave->id}/approve")->assertSessionHasErrors('status');
        $this->actingAs($user)->post("/organisation/leaves/{$leave->id}/reject")->assertSessionHasErrors('reason');

        // An approved leave can still be revoked.
        $this->actingAs($user)->post("/organisation/leaves/{$leave->id}/reject", ['reason' => 'Besoin de service'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertEquals('rejected', $leave->fresh()->status);
    }

    public function test_event_workflow_with_participants(): void
    {
        ['user' => $user, 'company' => $company] = $this->actingWithPermissions($this->allPermissions());
        $category = EventCategory::factory()->create(['company_id' => $company->id]);
        $personnel = Personnel::factory()->create(['company_id' => $company->id]);

        $this->actingAs($user)->post('/organisation/events', [
            'title' => 'Formation sécurité', 'event_category_id' => $category->id, 'type' => 'training',
            'start_date' => now()->addWeek()->toIso8601String(), 'end_date' => now()->addWeek()->addHours(3)->toIso8601String(),
        ])->assertRedirect()->assertSessionHasNoErrors();
        $event = Event::firstOrFail();
        $this->assertEquals('planned', $event->status);

        $this->actingAs($user)->post("/organisation/events/{$event->id}/participants", ['personnel_id' => $personnel->id, 'role' => 'attendee'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($user)->post("/organisation/events/{$event->id}/participants", ['personnel_id' => $personnel->id, 'role' => 'attendee'])
            ->assertSessionHasErrors('participant');
        $participant = $event->participants()->firstOrFail();
        $this->actingAs($user)->post("/organisation/events/{$event->id}/participants/{$participant->id}/rsvp", ['rsvp_status' => 'confirmed'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertEquals('confirmed', $participant->fresh()->rsvp_status);

        $this->actingAs($user)->post("/organisation/events/{$event->id}/confirm")->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($user)->post("/organisation/events/{$event->id}/complete")->assertRedirect()->assertSessionHasNoErrors();
        $this->assertEquals('completed', $event->fresh()->status);
        $this->actingAs($user)->post("/organisation/events/{$event->id}/cancel")->assertSessionHasErrors('status');

        $this->actingAs($user)->get("/organisation/events/{$event->id}")->assertInertia(fn (Assert $page) => $page
            ->component('Event/Events/Show')->has('event.data.participants', 1));
        $this->actingAs($user)->delete("/organisation/event-categories/{$category->id}")->assertSessionHasErrors('category');
    }
}
