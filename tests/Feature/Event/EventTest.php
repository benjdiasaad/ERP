<?php

declare(strict_types=1);

namespace Tests\Feature\Event;

use App\Models\Auth\Permission;
use App\Models\Auth\Role;
use App\Models\Auth\User;
use App\Models\Company\Company;
use App\Models\Event\Event;
use App\Models\Event\EventCategory;
use Carbon\Carbon;
use Tests\TestCase;

class EventTest extends TestCase
{
    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function giveUserPermissions(User $user, Company $company, array $slugs): void
    {
        $role = Role::create([
            'company_id' => $company->id,
            'name'       => 'Test Role',
            'slug'       => 'test-role-' . uniqid(),
            'is_system'  => false,
        ]);

        foreach ($slugs as $slug) {
            $permission = Permission::firstOrCreate(
                ['slug' => $slug],
                ['module' => explode('.', $slug)[0], 'name' => $slug, 'description' => '']
            );
            $role->permissions()->syncWithoutDetaching([$permission->id]);
        }

        \Illuminate\Support\Facades\DB::table('role_user')->insert([
            'role_id'    => $role->id,
            'user_id'    => $user->id,
            'company_id' => $company->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function allEventPermissions(): array
    {
        return [
            'events.view_any',
            'events.view',
            'events.create',
            'events.update',
            'events.delete',
        ];
    }

    private function createCategory(Company $company): EventCategory
    {
        return EventCategory::factory()->create(['company_id' => $company->id]);
    }

    private function createEvent(Company $company, array $overrides = []): Event
    {
        $category = $this->createCategory($company);

        return Event::factory()->create(array_merge([
            'company_id'        => $company->id,
            'event_category_id' => $category->id,
            'status'            => 'planned',
        ], $overrides));
    }

    // ─── CRUD: Index ──────────────────────────────────────────────────────────

    public function test_can_list_events(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['events.view_any']);

        $this->createEvent($company);
        $this->createEvent($company);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/events');

        $response->assertOk()
            ->assertJsonStructure(['data', 'meta']);

        $this->assertCount(2, $response->json('data'));
    }

    public function test_index_requires_authentication(): void
    {
        $this->getJson('/api/events')->assertUnauthorized();
    }

    public function test_index_requires_permission(): void
    {
        ['user' => $user] = $this->setUpCompanyAndUser();

        $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/events')
            ->assertForbidden();
    }

    public function test_index_filters_by_status(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['events.view_any']);

        $this->createEvent($company, ['status' => 'planned']);
        $this->createEvent($company, ['status' => 'confirmed']);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/events?status=planned');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('planned', $response->json('data.0.status'));
    }

    public function test_index_filters_by_type(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['events.view_any']);

        $this->createEvent($company, ['type' => 'meeting']);
        $this->createEvent($company, ['type' => 'conference']);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/events?type=meeting');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('meeting', $response->json('data.0.type'));
    }

    // ─── CRUD: Store ──────────────────────────────────────────────────────────

    public function test_can_create_event(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['events.create']);

        $category = $this->createCategory($company);

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/events', [
                'event_category_id' => $category->id,
                'title'             => 'Annual Conference',
                'type'              => 'conference',
                'location'          => 'Convention Center',
                'description'       => 'Annual company conference',
                'start_date'        => now()->addDays(30)->toDateTimeString(),
                'end_date'          => now()->addDays(32)->toDateTimeString(),
                'budget'            => 50000.00,
                'is_mandatory'      => true,
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.title', 'Annual Conference')
            ->assertJsonPath('data.status', 'planned')
            ->assertJsonPath('data.type', 'conference');

        $this->assertDatabaseHas('events', [
            'company_id' => $company->id,
            'title'      => 'Annual Conference',
            'status'     => 'planned',
            'created_by' => $user->id,
        ]);
    }

    public function test_store_requires_permission(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $category = $this->createCategory($company);

        $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/events', [
                'event_category_id' => $category->id,
                'title'             => 'Test',
                'type'              => 'meeting',
                'start_date'        => now()->addDays(5)->toDateTimeString(),
                'end_date'          => now()->addDays(6)->toDateTimeString(),
            ])
            ->assertForbidden();
    }

    public function test_store_validates_required_fields(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['events.create']);

        $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/events', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['event_category_id', 'title', 'type', 'start_date', 'end_date']);
    }

    // ─── CRUD: Show ───────────────────────────────────────────────────────────

    public function test_can_show_event(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['events.view']);

        $event = $this->createEvent($company, ['title' => 'My Event']);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson("/api/events/{$event->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $event->id)
            ->assertJsonPath('data.title', 'My Event');
    }

    public function test_show_requires_permission(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $event = $this->createEvent($company);

        $this->withHeaders($this->authHeaders($user))
            ->getJson("/api/events/{$event->id}")
            ->assertForbidden();
    }

    // ─── CRUD: Update ─────────────────────────────────────────────────────────

    public function test_can_update_planned_event(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['events.update']);

        $event = $this->createEvent($company, ['status' => 'planned']);

        $response = $this->withHeaders($this->authHeaders($user))
            ->putJson("/api/events/{$event->id}", [
                'title'      => 'Updated Title',
                'type'       => $event->type,
                'start_date' => $event->start_date->toDateTimeString(),
                'end_date'   => $event->end_date->toDateTimeString(),
                'event_category_id' => $event->event_category_id,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.title', 'Updated Title');
    }

    public function test_cannot_update_confirmed_event(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['events.update']);

        $event = $this->createEvent($company, ['status' => 'confirmed']);

        $this->withHeaders($this->authHeaders($user))
            ->putJson("/api/events/{$event->id}", [
                'title'      => 'Should Fail',
                'type'       => $event->type,
                'start_date' => $event->start_date->toDateTimeString(),
                'end_date'   => $event->end_date->toDateTimeString(),
                'event_category_id' => $event->event_category_id,
            ])
            ->assertUnprocessable();
    }

    public function test_update_requires_permission(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $event = $this->createEvent($company);

        $this->withHeaders($this->authHeaders($user))
            ->putJson("/api/events/{$event->id}", ['title' => 'Fail'])
            ->assertForbidden();
    }

    // ─── CRUD: Destroy ────────────────────────────────────────────────────────

    public function test_can_delete_planned_event(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['events.delete']);

        $event = $this->createEvent($company, ['status' => 'planned']);

        $this->withHeaders($this->authHeaders($user))
            ->deleteJson("/api/events/{$event->id}")
            ->assertNoContent();

        $this->assertSoftDeleted('events', ['id' => $event->id]);
    }

    public function test_cannot_delete_confirmed_event(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['events.delete']);

        $event = $this->createEvent($company, ['status' => 'confirmed']);

        $this->withHeaders($this->authHeaders($user))
            ->deleteJson("/api/events/{$event->id}")
            ->assertUnprocessable();
    }

    public function test_delete_requires_permission(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $event = $this->createEvent($company);

        $this->withHeaders($this->authHeaders($user))
            ->deleteJson("/api/events/{$event->id}")
            ->assertForbidden();
    }

    // ─── Lifecycle: Confirm ───────────────────────────────────────────────────

    public function test_can_confirm_planned_event(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['events.update']);

        $event = $this->createEvent($company, ['status' => 'planned']);

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/events/{$event->id}/confirm");

        $response->assertOk()
            ->assertJsonPath('data.status', 'confirmed');

        $this->assertDatabaseHas('events', [
            'id'           => $event->id,
            'status'       => 'confirmed',
            'confirmed_by' => $user->id,
        ]);
    }

    public function test_cannot_confirm_already_confirmed_event(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['events.update']);

        $event = $this->createEvent($company, ['status' => 'confirmed']);

        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/events/{$event->id}/confirm")
            ->assertUnprocessable();
    }

    // ─── Lifecycle: Cancel ────────────────────────────────────────────────────

    public function test_can_cancel_event_with_reason(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['events.update']);

        $event = $this->createEvent($company, ['status' => 'planned']);

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/events/{$event->id}/cancel", [
                'reason' => 'Venue unavailable',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $this->assertDatabaseHas('events', [
            'id'                  => $event->id,
            'status'              => 'cancelled',
            'cancellation_reason' => 'Venue unavailable',
            'cancelled_by'        => $user->id,
        ]);
    }

    public function test_cannot_cancel_completed_event(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['events.update']);

        $event = $this->createEvent($company, ['status' => 'completed']);

        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/events/{$event->id}/cancel")
            ->assertUnprocessable();
    }

    public function test_cannot_cancel_already_cancelled_event(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['events.update']);

        $event = $this->createEvent($company, ['status' => 'cancelled']);

        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/events/{$event->id}/cancel")
            ->assertUnprocessable();
    }

    // ─── Lifecycle: Complete ──────────────────────────────────────────────────

    public function test_can_complete_event(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['events.update']);

        $event = $this->createEvent($company, ['status' => 'confirmed']);

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/events/{$event->id}/complete");

        $response->assertOk()
            ->assertJsonPath('data.status', 'completed');

        $this->assertDatabaseHas('events', [
            'id'           => $event->id,
            'status'       => 'completed',
            'completed_by' => $user->id,
        ]);
    }

    public function test_cannot_complete_already_completed_event(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['events.update']);

        $event = $this->createEvent($company, ['status' => 'completed']);

        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/events/{$event->id}/complete")
            ->assertUnprocessable();
    }

    public function test_cannot_complete_cancelled_event(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['events.update']);

        $event = $this->createEvent($company, ['status' => 'cancelled']);

        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/events/{$event->id}/complete")
            ->assertUnprocessable();
    }

    // ─── Full Lifecycle ───────────────────────────────────────────────────────

    public function test_full_lifecycle_planned_to_completed(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, $this->allEventPermissions());

        $category = $this->createCategory($company);

        // Create
        $createResponse = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/events', [
                'event_category_id' => $category->id,
                'title'             => 'Full Lifecycle Event',
                'type'              => 'conference',
                'start_date'        => now()->addDays(10)->toDateTimeString(),
                'end_date'          => now()->addDays(12)->toDateTimeString(),
            ]);

        $createResponse->assertCreated();
        $eventId = $createResponse->json('data.id');
        $this->assertEquals('planned', $createResponse->json('data.status'));

        // Confirm
        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/events/{$eventId}/confirm")
            ->assertOk()
            ->assertJsonPath('data.status', 'confirmed');

        // Complete
        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/events/{$eventId}/complete")
            ->assertOk()
            ->assertJsonPath('data.status', 'completed');
    }

    public function test_lifecycle_planned_to_cancelled(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, $this->allEventPermissions());

        $event = $this->createEvent($company, ['status' => 'planned']);

        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/events/{$event->id}/cancel", ['reason' => 'Budget cut'])
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');
    }

    // ─── Upcoming Events ──────────────────────────────────────────────────────

    public function test_can_get_upcoming_events(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['events.view_any']);

        // Future planned event
        $this->createEvent($company, [
            'start_date' => now()->addDays(5),
            'end_date'   => now()->addDays(6),
            'status'     => 'planned',
        ]);

        // Future confirmed event
        $this->createEvent($company, [
            'start_date' => now()->addDays(10),
            'end_date'   => now()->addDays(11),
            'status'     => 'confirmed',
        ]);

        // Past event (should not appear)
        $this->createEvent($company, [
            'start_date' => now()->subDays(5),
            'end_date'   => now()->subDays(4),
            'status'     => 'completed',
        ]);

        // Cancelled future event (should not appear)
        $this->createEvent($company, [
            'start_date' => now()->addDays(15),
            'end_date'   => now()->addDays(16),
            'status'     => 'cancelled',
        ]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/events/upcoming');

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
    }

    public function test_upcoming_respects_limit_parameter(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['events.view_any']);

        for ($i = 1; $i <= 5; $i++) {
            $this->createEvent($company, [
                'start_date' => now()->addDays($i),
                'end_date'   => now()->addDays($i + 1),
                'status'     => 'planned',
            ]);
        }

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/events/upcoming?limit=3');

        $response->assertOk();
        $this->assertCount(3, $response->json('data'));
    }

    public function test_upcoming_requires_permission(): void
    {
        ['user' => $user] = $this->setUpCompanyAndUser();

        $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/events/upcoming')
            ->assertForbidden();
    }

    // ─── Tenant Isolation ─────────────────────────────────────────────────────

    public function test_cannot_view_event_from_another_company(): void
    {
        ['user' => $userA, 'company' => $companyA] = $this->setUpCompanyAndUser();
        ['user' => $userB, 'company' => $companyB] = $this->setUpCompanyAndUser();

        $this->giveUserPermissions($userA, $companyA, ['events.view']);
        $this->giveUserPermissions($userB, $companyB, ['events.view']);

        $eventA = $this->createEvent($companyA);

        // User B cannot access User A's event
        $this->withHeaders($this->authHeaders($userB))
            ->getJson("/api/events/{$eventA->id}")
            ->assertNotFound();
    }

    public function test_index_only_returns_own_company_events(): void
    {
        ['user' => $userA, 'company' => $companyA] = $this->setUpCompanyAndUser();
        ['user' => $userB, 'company' => $companyB] = $this->setUpCompanyAndUser();

        $this->giveUserPermissions($userA, $companyA, ['events.view_any']);
        $this->giveUserPermissions($userB, $companyB, ['events.view_any']);

        $this->createEvent($companyA);
        $this->createEvent($companyA);
        $this->createEvent($companyB);

        // Test userA sees only their 2 events
        $responseA = $this->withHeaders($this->authHeaders($userA))
            ->getJson('/api/events');
        $responseA->assertOk();
        $this->assertCount(2, $responseA->json('data'));
    }

    public function test_index_only_returns_own_company_events_for_second_tenant(): void
    {
        ['user' => $userB, 'company' => $companyB] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($userB, $companyB, ['events.view_any']);
        $this->createEvent($companyB);

        $responseB = $this->withHeaders($this->authHeaders($userB))
            ->getJson('/api/events');
        $responseB->assertOk();
        $this->assertCount(1, $responseB->json('data'));
    }

    public function test_cannot_update_event_from_another_company(): void
    {
        ['user' => $userA, 'company' => $companyA] = $this->setUpCompanyAndUser();
        ['user' => $userB, 'company' => $companyB] = $this->setUpCompanyAndUser();

        $this->giveUserPermissions($userB, $companyB, ['events.update']);

        $eventA = $this->createEvent($companyA);

        $this->withHeaders($this->authHeaders($userB))
            ->putJson("/api/events/{$eventA->id}", ['title' => 'Hacked'])
            ->assertNotFound();
    }

    public function test_cannot_delete_event_from_another_company(): void
    {
        ['user' => $userA, 'company' => $companyA] = $this->setUpCompanyAndUser();
        ['user' => $userB, 'company' => $companyB] = $this->setUpCompanyAndUser();

        $this->giveUserPermissions($userB, $companyB, ['events.delete']);

        $eventA = $this->createEvent($companyA);

        $this->withHeaders($this->authHeaders($userB))
            ->deleteJson("/api/events/{$eventA->id}")
            ->assertNotFound();
    }

    public function test_upcoming_only_returns_own_company_events(): void
    {
        ['user' => $userA, 'company' => $companyA] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($userA, $companyA, ['events.view_any']);

        $this->createEvent($companyA, [
            'start_date' => now()->addDays(5),
            'end_date'   => now()->addDays(6),
            'status'     => 'planned',
        ]);

        $responseA = $this->withHeaders($this->authHeaders($userA))
            ->getJson('/api/events/upcoming');

        $responseA->assertOk();
        $this->assertCount(1, $responseA->json('data'));
    }

    public function test_upcoming_only_returns_own_company_events_for_second_tenant(): void
    {
        ['user' => $userB, 'company' => $companyB] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($userB, $companyB, ['events.view_any']);

        $this->createEvent($companyB, [
            'start_date' => now()->addDays(5),
            'end_date'   => now()->addDays(6),
            'status'     => 'planned',
        ]);

        $responseB = $this->withHeaders($this->authHeaders($userB))
            ->getJson('/api/events/upcoming');

        $responseB->assertOk();
        $this->assertCount(1, $responseB->json('data'));
    }
}
