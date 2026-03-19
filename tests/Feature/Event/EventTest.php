<?php

namespace Tests\Feature\Event;

use App\Models\Auth\User;
use App\Models\Company\Company;
use App\Models\Event\Event;
use App\Models\Event\EventCategory;
use App\Services\Event\EventService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventTest extends TestCase
{
    use RefreshDatabase;

    private EventService $eventService;
    private User $user;
    private Company $company;
    private EventCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->eventService = app(EventService::class);

        ['user' => $this->user, 'company' => $this->company] = $this->setUpCompanyAndUser();
        $this->actingAs($this->user);

        $this->category = EventCategory::factory()->create([
            'company_id' => $this->company->id,
        ]);
    }

    // ─── CRUD Tests ───────────────────────────────────────────────────────────

    public function test_can_create_event(): void
    {
        $data = [
            'event_category_id' => $this->category->id,
            'title' => 'Annual Conference',
            'type' => 'conference',
            'location' => 'Convention Center',
            'description' => 'Annual company conference',
            'start_date' => now()->addDays(30),
            'end_date' => now()->addDays(32),
            'budget' => 50000.00,
            'is_mandatory' => true,
        ];

        $event = $this->eventService->create($data);

        $this->assertDatabaseHas('events', [
            'id' => $event->id,
            'company_id' => $this->company->id,
            'title' => 'Annual Conference',
            'type' => 'conference',
            'status' => 'planned',
            'created_by' => $this->user->id,
        ]);

        $this->assertEquals('planned', $event->status);
        $this->assertTrue($event->is_mandatory);
    }

    public function test_can_update_planned_event(): void
    {
        $event = Event::factory()->create([
            'company_id' => $this->company->id,
            'status' => 'planned',
        ]);

        $updated = $this->eventService->update($event, [
            'title' => 'Updated Conference Title',
            'location' => 'New Location',
        ]);

        $this->assertEquals('Updated Conference Title', $updated->title);
        $this->assertEquals('New Location', $updated->location);
    }

    public function test_cannot_update_confirmed_event(): void
    {
        $event = Event::factory()->create([
            'company_id' => $this->company->id,
            'status' => 'confirmed',
        ]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->expectExceptionMessage('Only planned or draft events can be updated');

        $this->eventService->update($event, [
            'title' => 'Should Fail',
        ]);
    }

    public function test_can_delete_planned_event(): void
    {
        $event = Event::factory()->create([
            'company_id' => $this->company->id,
            'status' => 'planned',
        ]);

        $result = $this->eventService->delete($event);

        $this->assertTrue($result);
        $this->assertSoftDeleted('events', ['id' => $event->id]);
    }

    public function test_cannot_delete_confirmed_event(): void
    {
        $event = Event::factory()->create([
            'company_id' => $this->company->id,
            'status' => 'confirmed',
        ]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->expectExceptionMessage('Only planned or draft events can be deleted');

        $this->eventService->delete($event);
    }

    // ─── Lifecycle Tests ──────────────────────────────────────────────────────

    public function test_full_lifecycle_planned_to_completed(): void
    {
        // Create event in planned status
        $event = Event::factory()->create([
            'company_id' => $this->company->id,
            'status' => 'planned',
        ]);

        $this->assertEquals('planned', $event->status);

        // Confirm event
        $event = $this->eventService->confirm($event);
        $this->assertEquals('confirmed', $event->status);
        $this->assertNotNull($event->confirmed_by);
        $this->assertNotNull($event->confirmed_at);

        // Complete event
        $event = $this->eventService->complete($event);
        $this->assertEquals('completed', $event->status);
        $this->assertNotNull($event->completed_by);
        $this->assertNotNull($event->completed_at);
    }

    public function test_can_confirm_planned_event(): void
    {
        $event = Event::factory()->create([
            'company_id' => $this->company->id,
            'status' => 'planned',
        ]);

        $confirmed = $this->eventService->confirm($event);

        $this->assertEquals('confirmed', $confirmed->status);
        $this->assertEquals($this->user->id, $confirmed->confirmed_by);
        $this->assertNotNull($confirmed->confirmed_at);
    }

    public function test_cannot_confirm_non_planned_event(): void
    {
        $event = Event::factory()->create([
            'company_id' => $this->company->id,
            'status' => 'confirmed',
        ]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->expectExceptionMessage('Only planned events can be confirmed');

        $this->eventService->confirm($event);
    }

    public function test_can_cancel_event(): void
    {
        $event = Event::factory()->create([
            'company_id' => $this->company->id,
            'status' => 'planned',
        ]);

        $cancelled = $this->eventService->cancel($event, 'Venue unavailable');

        $this->assertEquals('cancelled', $cancelled->status);
        $this->assertEquals($this->user->id, $cancelled->cancelled_by);
        $this->assertNotNull($cancelled->cancelled_at);
        $this->assertEquals('Venue unavailable', $cancelled->cancellation_reason);
    }

    public function test_cannot_cancel_completed_event(): void
    {
        $event = Event::factory()->create([
            'company_id' => $this->company->id,
            'status' => 'completed',
        ]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->expectExceptionMessage('Completed events cannot be cancelled');

        $this->eventService->cancel($event);
    }

    public function test_cannot_cancel_already_cancelled_event(): void
    {
        $event = Event::factory()->create([
            'company_id' => $this->company->id,
            'status' => 'cancelled',
        ]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->expectExceptionMessage('Event is already cancelled');

        $this->eventService->cancel($event);
    }

    public function test_can_complete_event(): void
    {
        $event = Event::factory()->create([
            'company_id' => $this->company->id,
            'status' => 'confirmed',
        ]);

        $completed = $this->eventService->complete($event);

        $this->assertEquals('completed', $completed->status);
        $this->assertEquals($this->user->id, $completed->completed_by);
        $this->assertNotNull($completed->completed_at);
    }

    public function test_cannot_complete_already_completed_event(): void
    {
        $event = Event::factory()->create([
            'company_id' => $this->company->id,
            'status' => 'completed',
        ]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->expectExceptionMessage('Event is already completed');

        $this->eventService->complete($event);
    }

    public function test_cannot_complete_cancelled_event(): void
    {
        $event = Event::factory()->create([
            'company_id' => $this->company->id,
            'status' => 'cancelled',
        ]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->expectExceptionMessage('Cancelled events cannot be completed');

        $this->eventService->complete($event);
    }

    public function test_can_postpone_event(): void
    {
        $event = Event::factory()->create([
            'company_id' => $this->company->id,
            'status' => 'planned',
            'start_date' => now()->addDays(10),
            'end_date' => now()->addDays(12),
        ]);

        $newStartDate = now()->addDays(20);
        $newEndDate = now()->addDays(22);

        $postponed = $this->eventService->postpone($event, [
            'start_date' => $newStartDate,
            'end_date' => $newEndDate,
            'reason' => 'Speaker unavailable',
        ]);

        $this->assertEquals($newStartDate->toDateString(), $postponed->start_date->toDateString());
        $this->assertEquals($newEndDate->toDateString(), $postponed->end_date->toDateString());
        $this->assertEquals($this->user->id, $postponed->postponed_by);
        $this->assertNotNull($postponed->postponed_at);
        $this->assertEquals('Speaker unavailable', $postponed->postponement_reason);
    }

    public function test_cannot_postpone_completed_event(): void
    {
        $event = Event::factory()->create([
            'company_id' => $this->company->id,
            'status' => 'completed',
        ]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->expectExceptionMessage('Completed events cannot be postponed');

        $this->eventService->postpone($event, [
            'start_date' => now()->addDays(30),
            'end_date' => now()->addDays(32),
        ]);
    }

    public function test_cannot_postpone_cancelled_event(): void
    {
        $event = Event::factory()->create([
            'company_id' => $this->company->id,
            'status' => 'cancelled',
        ]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->expectExceptionMessage('Cancelled events cannot be postponed');

        $this->eventService->postpone($event, [
            'start_date' => now()->addDays(30),
            'end_date' => now()->addDays(32),
        ]);
    }

    public function test_postpone_validates_date_range(): void
    {
        $event = Event::factory()->create([
            'company_id' => $this->company->id,
            'status' => 'planned',
        ]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->expectExceptionMessage('End date must be after start date');

        $this->eventService->postpone($event, [
            'start_date' => now()->addDays(30),
            'end_date' => now()->addDays(28), // Before start date
        ]);
    }

    // ─── Query Tests ──────────────────────────────────────────────────────────

    public function test_get_upcoming_events(): void
    {
        // Past event
        Event::factory()->create([
            'company_id' => $this->company->id,
            'start_date' => now()->subDays(10),
            'status' => 'completed',
        ]);

        // Future events
        $upcoming1 = Event::factory()->create([
            'company_id' => $this->company->id,
            'start_date' => now()->addDays(5),
            'status' => 'planned',
        ]);

        $upcoming2 = Event::factory()->create([
            'company_id' => $this->company->id,
            'start_date' => now()->addDays(10),
            'status' => 'confirmed',
        ]);

        // Cancelled future event (should not appear)
        Event::factory()->create([
            'company_id' => $this->company->id,
            'start_date' => now()->addDays(15),
            'status' => 'cancelled',
        ]);

        $upcoming = $this->eventService->getUpcoming();

        $this->assertCount(2, $upcoming);
        $this->assertEquals($upcoming1->id, $upcoming->first()->id);
        $this->assertEquals($upcoming2->id, $upcoming->last()->id);
    }

    public function test_get_upcoming_events_with_limit(): void
    {
        Event::factory()->count(5)->create([
            'company_id' => $this->company->id,
            'start_date' => now()->addDays(5),
            'status' => 'planned',
        ]);

        $upcoming = $this->eventService->getUpcoming(3);

        $this->assertCount(3, $upcoming);
    }

    public function test_get_events_by_date_range(): void
    {
        $startDate = Carbon::parse('2026-04-01');
        $endDate = Carbon::parse('2026-04-30');

        // Event within range
        $inRange = Event::factory()->create([
            'company_id' => $this->company->id,
            'start_date' => Carbon::parse('2026-04-15'),
        ]);

        // Event outside range
        Event::factory()->create([
            'company_id' => $this->company->id,
            'start_date' => Carbon::parse('2026-05-15'),
        ]);

        $events = $this->eventService->getByDateRange($startDate, $endDate);

        $this->assertCount(1, $events);
        $this->assertEquals($inRange->id, $events->first()->id);
    }

    public function test_get_events_by_date_range_with_status_filter(): void
    {
        $startDate = Carbon::parse('2026-04-01');
        $endDate = Carbon::parse('2026-04-30');

        Event::factory()->create([
            'company_id' => $this->company->id,
            'start_date' => Carbon::parse('2026-04-10'),
            'status' => 'planned',
        ]);

        $confirmed = Event::factory()->create([
            'company_id' => $this->company->id,
            'start_date' => Carbon::parse('2026-04-15'),
            'status' => 'confirmed',
        ]);

        $events = $this->eventService->getByDateRange($startDate, $endDate, 'confirmed');

        $this->assertCount(1, $events);
        $this->assertEquals($confirmed->id, $events->first()->id);
    }

    public function test_get_events_by_category(): void
    {
        $category1 = EventCategory::factory()->create(['company_id' => $this->company->id]);
        $category2 = EventCategory::factory()->create(['company_id' => $this->company->id]);

        $event1 = Event::factory()->create([
            'company_id' => $this->company->id,
            'event_category_id' => $category1->id,
        ]);

        Event::factory()->create([
            'company_id' => $this->company->id,
            'event_category_id' => $category2->id,
        ]);

        $events = $this->eventService->getByCategory($category1->id);

        $this->assertCount(1, $events);
        $this->assertEquals($event1->id, $events->first()->id);
    }

    public function test_get_events_by_status(): void
    {
        Event::factory()->create([
            'company_id' => $this->company->id,
            'status' => 'planned',
        ]);

        $confirmed = Event::factory()->create([
            'company_id' => $this->company->id,
            'status' => 'confirmed',
        ]);

        $events = $this->eventService->getByStatus('confirmed');

        $this->assertCount(1, $events);
        $this->assertEquals($confirmed->id, $events->first()->id);
    }

    public function test_get_mandatory_events(): void
    {
        // Mandatory future event
        $mandatory = Event::factory()->create([
            'company_id' => $this->company->id,
            'is_mandatory' => true,
            'start_date' => now()->addDays(10),
        ]);

        // Non-mandatory future event
        Event::factory()->create([
            'company_id' => $this->company->id,
            'is_mandatory' => false,
            'start_date' => now()->addDays(10),
        ]);

        // Mandatory past event (should not appear)
        Event::factory()->create([
            'company_id' => $this->company->id,
            'is_mandatory' => true,
            'start_date' => now()->subDays(10),
        ]);

        $events = $this->eventService->getMandatory();

        $this->assertCount(1, $events);
        $this->assertEquals($mandatory->id, $events->first()->id);
    }

    public function test_get_expiring_soon(): void
    {
        // Event within 7 days
        $soon = Event::factory()->create([
            'company_id' => $this->company->id,
            'start_date' => now()->addDays(5),
            'status' => 'planned',
        ]);

        // Event beyond 7 days
        Event::factory()->create([
            'company_id' => $this->company->id,
            'start_date' => now()->addDays(10),
            'status' => 'planned',
        ]);

        // Cancelled event within 7 days (should not appear)
        Event::factory()->create([
            'company_id' => $this->company->id,
            'start_date' => now()->addDays(3),
            'status' => 'cancelled',
        ]);

        $events = $this->eventService->getExpiringSoon(7);

        $this->assertCount(1, $events);
        $this->assertEquals($soon->id, $events->first()->id);
    }

    // ─── Tenant Isolation Tests ───────────────────────────────────────────────

    public function test_events_are_company_scoped(): void
    {
        ['user' => $userB, 'company' => $companyB] = $this->setUpCompanyAndUser();

        $eventA = Event::factory()->create([
            'company_id' => $this->company->id,
        ]);

        $eventB = Event::factory()->create([
            'company_id' => $companyB->id,
        ]);

        // User A should only see their event
        $this->actingAs($this->user);
        $eventsA = Event::all();
        $this->assertCount(1, $eventsA);
        $this->assertEquals($eventA->id, $eventsA->first()->id);

        // User B should only see their event
        $this->actingAs($userB);
        $eventsB = Event::all();
        $this->assertCount(1, $eventsB);
        $this->assertEquals($eventB->id, $eventsB->first()->id);
    }
}
