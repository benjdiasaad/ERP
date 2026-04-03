<?php

declare(strict_types=1);

namespace Tests\Feature\Event;

use App\Models\Auth\Permission;
use App\Models\Auth\Role;
use App\Models\Auth\User;
use App\Models\Company\Company;
use App\Models\Event\Event;
use App\Models\Event\EventCategory;
use App\Models\Event\EventParticipant;
use App\Models\Personnel\Personnel;
use Tests\TestCase;

class ParticipantTest extends TestCase
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

    private function allParticipantPermissions(): array
    {
        return [
            'events.view',
            'events.update',
            'event_participants.view_any',
            'event_participants.view',
            'event_participants.create',
            'event_participants.update',
            'event_participants.delete',
        ];
    }

    private function createEvent(Company $company, array $overrides = []): Event
    {
        $category = EventCategory::factory()->create(['company_id' => $company->id]);

        return Event::factory()->create(array_merge([
            'company_id'        => $company->id,
            'event_category_id' => $category->id,
            'status'            => 'planned',
        ], $overrides));
    }

    // ─── Index ────────────────────────────────────────────────────────────────

    public function test_can_list_participants_for_event(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['events.view', 'event_participants.view_any']);

        $event = $this->createEvent($company);

        EventParticipant::factory()->count(3)->create([
            'company_id' => $company->id,
            'event_id'   => $event->id,
        ]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson("/api/events/{$event->id}/participants");

        $response->assertOk();
        $this->assertCount(3, $response->json('data'));
    }

    public function test_index_requires_authentication(): void
    {
        ['company' => $company] = $this->setUpCompanyAndUser();
        $event = $this->createEvent($company);

        $this->getJson("/api/events/{$event->id}/participants")
            ->assertUnauthorized();
    }

    public function test_index_requires_permission(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $event = $this->createEvent($company);

        $this->withHeaders($this->authHeaders($user))
            ->getJson("/api/events/{$event->id}/participants")
            ->assertForbidden();
    }

    // ─── Store (Invite) ───────────────────────────────────────────────────────

    public function test_can_invite_external_participant(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['events.update', 'event_participants.create']);

        $event = $this->createEvent($company);

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/events/{$event->id}/participants", [
                'name'  => 'John Doe',
                'email' => 'john@external.com',
                'role'  => 'guest',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.email', 'john@external.com')
            ->assertJsonPath('data.role', 'guest')
            ->assertJsonPath('data.rsvp_status', 'pending');

        $this->assertDatabaseHas('event_participants', [
            'event_id'   => $event->id,
            'company_id' => $company->id,
            'email'      => 'john@external.com',
            'role'       => 'guest',
            'rsvp_status' => 'pending',
        ]);
    }

    public function test_can_invite_internal_user(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['events.update', 'event_participants.create']);

        $event = $this->createEvent($company);
        $invitedUser = User::factory()->create(['current_company_id' => $company->id]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/events/{$event->id}/participants", [
                'user_id' => $invitedUser->id,
                'role'    => 'attendee',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.rsvp_status', 'pending');

        $this->assertDatabaseHas('event_participants', [
            'event_id' => $event->id,
            'user_id'  => $invitedUser->id,
            'role'     => 'attendee',
        ]);
    }

    public function test_can_invite_personnel(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['events.update', 'event_participants.create']);

        $event = $this->createEvent($company);
        $personnel = Personnel::factory()->create(['company_id' => $company->id]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/events/{$event->id}/participants", [
                'personnel_id' => $personnel->id,
                'role'         => 'speaker',
            ]);

        $response->assertCreated();

        $this->assertDatabaseHas('event_participants', [
            'event_id'     => $event->id,
            'personnel_id' => $personnel->id,
            'role'         => 'speaker',
        ]);
    }

    public function test_store_requires_permission(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $event = $this->createEvent($company);

        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/events/{$event->id}/participants", [
                'email' => 'test@test.com',
                'role'  => 'guest',
            ])
            ->assertForbidden();
    }

    public function test_store_validates_role_field(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['events.update', 'event_participants.create']);

        $event = $this->createEvent($company);

        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/events/{$event->id}/participants", [
                'email' => 'test@test.com',
                'role'  => 'invalid_role',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['role']);
    }

    public function test_cannot_invite_duplicate_user(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['events.update', 'event_participants.create']);

        $event = $this->createEvent($company);
        $invitedUser = User::factory()->create(['current_company_id' => $company->id]);

        // First invite
        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/events/{$event->id}/participants", [
                'user_id' => $invitedUser->id,
                'role'    => 'attendee',
            ])
            ->assertCreated();

        // Duplicate invite
        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/events/{$event->id}/participants", [
                'user_id' => $invitedUser->id,
                'role'    => 'speaker',
            ])
            ->assertUnprocessable();
    }

    // ─── Show ─────────────────────────────────────────────────────────────────

    public function test_can_show_participant(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['events.view', 'event_participants.view']);

        $event = $this->createEvent($company);
        $participant = EventParticipant::factory()->external()->create([
            'company_id' => $company->id,
            'event_id'   => $event->id,
        ]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson("/api/events/{$event->id}/participants/{$participant->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $participant->id);
    }

    // ─── Destroy ─────────────────────────────────────────────────────────────

    public function test_can_remove_participant(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['events.update', 'event_participants.delete']);

        $event = $this->createEvent($company);
        $participant = EventParticipant::factory()->external()->create([
            'company_id' => $company->id,
            'event_id'   => $event->id,
        ]);

        $this->withHeaders($this->authHeaders($user))
            ->deleteJson("/api/events/{$event->id}/participants/{$participant->id}")
            ->assertNoContent();

        $this->assertSoftDeleted('event_participants', ['id' => $participant->id]);
    }

    public function test_destroy_requires_permission(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $event = $this->createEvent($company);
        $participant = EventParticipant::factory()->external()->create([
            'company_id' => $company->id,
            'event_id'   => $event->id,
        ]);

        $this->withHeaders($this->authHeaders($user))
            ->deleteJson("/api/events/{$event->id}/participants/{$participant->id}")
            ->assertForbidden();
    }

    // ─── Confirm RSVP ────────────────────────────────────────────────────────

    public function test_can_confirm_rsvp(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['events.update', 'event_participants.update']);

        $event = $this->createEvent($company);
        $participant = EventParticipant::factory()->pending()->create([
            'company_id' => $company->id,
            'event_id'   => $event->id,
        ]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/events/{$event->id}/participants/{$participant->id}/confirm");

        $response->assertOk()
            ->assertJsonPath('data.rsvp_status', 'confirmed');

        $this->assertDatabaseHas('event_participants', [
            'id'          => $participant->id,
            'rsvp_status' => 'confirmed',
        ]);
    }

    public function test_cannot_confirm_already_confirmed_participant(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['events.update', 'event_participants.update']);

        $event = $this->createEvent($company);
        $participant = EventParticipant::factory()->confirmed()->create([
            'company_id' => $company->id,
            'event_id'   => $event->id,
        ]);

        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/events/{$event->id}/participants/{$participant->id}/confirm")
            ->assertUnprocessable();
    }

    public function test_confirm_requires_permission(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $event = $this->createEvent($company);
        $participant = EventParticipant::factory()->pending()->create([
            'company_id' => $company->id,
            'event_id'   => $event->id,
        ]);

        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/events/{$event->id}/participants/{$participant->id}/confirm")
            ->assertForbidden();
    }

    // ─── Decline RSVP ────────────────────────────────────────────────────────

    public function test_can_decline_rsvp(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['events.update', 'event_participants.update']);

        $event = $this->createEvent($company);
        $participant = EventParticipant::factory()->pending()->create([
            'company_id' => $company->id,
            'event_id'   => $event->id,
        ]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/events/{$event->id}/participants/{$participant->id}/decline");

        $response->assertOk()
            ->assertJsonPath('data.rsvp_status', 'declined');

        $this->assertDatabaseHas('event_participants', [
            'id'          => $participant->id,
            'rsvp_status' => 'declined',
        ]);
    }

    public function test_cannot_decline_already_declined_participant(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['events.update', 'event_participants.update']);

        $event = $this->createEvent($company);
        $participant = EventParticipant::factory()->declined()->create([
            'company_id' => $company->id,
            'event_id'   => $event->id,
        ]);

        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/events/{$event->id}/participants/{$participant->id}/decline")
            ->assertUnprocessable();
    }

    public function test_decline_requires_permission(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $event = $this->createEvent($company);
        $participant = EventParticipant::factory()->pending()->create([
            'company_id' => $company->id,
            'event_id'   => $event->id,
        ]);

        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/events/{$event->id}/participants/{$participant->id}/decline")
            ->assertForbidden();
    }

    // ─── Bulk Invite ──────────────────────────────────────────────────────────

    public function test_can_bulk_invite_participants(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['events.update', 'event_participants.create']);

        $event = $this->createEvent($company);
        $user1 = User::factory()->create(['current_company_id' => $company->id]);
        $user2 = User::factory()->create(['current_company_id' => $company->id]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/events/{$event->id}/participants/bulk-invite", [
                'participants' => [
                    ['user_id' => $user1->id, 'role' => 'attendee'],
                    ['user_id' => $user2->id, 'role' => 'speaker'],
                    ['name' => 'External Guest', 'email' => 'guest@external.com', 'role' => 'guest'],
                ],
            ]);

        $response->assertCreated();
        $this->assertCount(3, $response->json('created'));
        $this->assertCount(0, $response->json('failed'));

        $this->assertDatabaseHas('event_participants', [
            'event_id' => $event->id,
            'user_id'  => $user1->id,
        ]);
        $this->assertDatabaseHas('event_participants', [
            'event_id' => $event->id,
            'email'    => 'guest@external.com',
        ]);
    }

    public function test_bulk_invite_handles_partial_failures(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['events.update', 'event_participants.create']);

        $event = $this->createEvent($company);
        $existingUser = User::factory()->create(['current_company_id' => $company->id]);

        // Pre-invite to create duplicate scenario
        EventParticipant::factory()->create([
            'company_id' => $company->id,
            'event_id'   => $event->id,
            'user_id'    => $existingUser->id,
        ]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/events/{$event->id}/participants/bulk-invite", [
                'participants' => [
                    ['user_id' => $existingUser->id, 'role' => 'speaker'], // Duplicate - fails
                    ['name' => 'Valid Guest', 'email' => 'valid@external.com', 'role' => 'guest'], // Succeeds
                    ['role' => 'attendee'], // Missing identifier - fails
                ],
            ]);

        $response->assertCreated();
        $this->assertCount(1, $response->json('created'));
        $this->assertCount(2, $response->json('failed'));
    }

    public function test_bulk_invite_requires_permission(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $event = $this->createEvent($company);

        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/events/{$event->id}/participants/bulk-invite", [
                'participants' => [
                    ['email' => 'test@test.com', 'role' => 'guest'],
                ],
            ])
            ->assertForbidden();
    }

    // ─── Tenant Isolation ─────────────────────────────────────────────────────

    public function test_cannot_view_participants_from_another_company_event(): void
    {
        ['user' => $userA, 'company' => $companyA] = $this->setUpCompanyAndUser();
        ['user' => $userB, 'company' => $companyB] = $this->setUpCompanyAndUser();

        $this->giveUserPermissions($userB, $companyB, ['events.view', 'event_participants.view_any']);

        $eventA = $this->createEvent($companyA);

        // User B cannot list participants of User A's event (event not found)
        $this->withHeaders($this->authHeaders($userB))
            ->getJson("/api/events/{$eventA->id}/participants")
            ->assertNotFound();
    }

    public function test_cannot_invite_to_another_company_event(): void
    {
        ['user' => $userA, 'company' => $companyA] = $this->setUpCompanyAndUser();
        ['user' => $userB, 'company' => $companyB] = $this->setUpCompanyAndUser();

        $this->giveUserPermissions($userB, $companyB, ['events.update', 'event_participants.create']);

        $eventA = $this->createEvent($companyA);

        $this->withHeaders($this->authHeaders($userB))
            ->postJson("/api/events/{$eventA->id}/participants", [
                'email' => 'test@test.com',
                'role'  => 'guest',
            ])
            ->assertNotFound();
    }

    public function test_participants_are_scoped_to_company(): void
    {
        ['user' => $userA, 'company' => $companyA] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($userA, $companyA, ['events.view', 'event_participants.view_any']);

        $eventA = $this->createEvent($companyA);

        EventParticipant::factory()->count(2)->create([
            'company_id' => $companyA->id,
            'event_id'   => $eventA->id,
        ]);

        $responseA = $this->withHeaders($this->authHeaders($userA))
            ->getJson("/api/events/{$eventA->id}/participants");

        $responseA->assertOk();
        $this->assertCount(2, $responseA->json('data'));
    }

    public function test_participants_are_scoped_to_company_for_second_tenant(): void
    {
        ['user' => $userB, 'company' => $companyB] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($userB, $companyB, ['events.view', 'event_participants.view_any']);

        $eventB = $this->createEvent($companyB);

        EventParticipant::factory()->count(3)->create([
            'company_id' => $companyB->id,
            'event_id'   => $eventB->id,
        ]);

        $responseB = $this->withHeaders($this->authHeaders($userB))
            ->getJson("/api/events/{$eventB->id}/participants");

        $responseB->assertOk();
        $this->assertCount(3, $responseB->json('data'));
    }

    public function test_cannot_confirm_participant_from_another_company(): void
    {
        ['user' => $userA, 'company' => $companyA] = $this->setUpCompanyAndUser();
        ['user' => $userB, 'company' => $companyB] = $this->setUpCompanyAndUser();

        $this->giveUserPermissions($userB, $companyB, ['events.update', 'event_participants.update']);

        $eventA = $this->createEvent($companyA);
        $participantA = EventParticipant::factory()->pending()->create([
            'company_id' => $companyA->id,
            'event_id'   => $eventA->id,
        ]);

        // User B cannot confirm participant from company A's event
        $this->withHeaders($this->authHeaders($userB))
            ->postJson("/api/events/{$eventA->id}/participants/{$participantA->id}/confirm")
            ->assertNotFound();
    }
}
