<?php

namespace Tests\Feature\Event;

use App\Models\Auth\User;
use App\Models\Company\Company;
use App\Models\Event\Event;
use App\Models\Event\EventParticipant;
use App\Models\Personnel\Personnel;
use App\Services\Event\EventParticipantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ParticipantTest extends TestCase
{
    use RefreshDatabase;

    private EventParticipantService $participantService;
    private User $user;
    private Company $company;
    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();

        $this->participantService = app(EventParticipantService::class);

        ['user' => $this->user, 'company' => $this->company] = $this->setUpCompanyAndUser();
        $this->actingAs($this->user);

        $this->event = Event::factory()->create([
            'company_id' => $this->company->id,
        ]);
    }

    // ─── Invite Tests ─────────────────────────────────────────────────────────

    public function test_can_invite_internal_user_by_user_id(): void
    {
        $invitedUser = User::factory()->create([
            'current_company_id' => $this->company->id,
        ]);

        $participant = $this->participantService->invite($this->event, [
            'user_id' => $invitedUser->id,
            'role' => 'attendee',
        ]);

        $this->assertDatabaseHas('event_participants', [
            'id' => $participant->id,
            'event_id' => $this->event->id,
            'user_id' => $invitedUser->id,
            'company_id' => $this->company->id,
            'role' => 'attendee',
            'rsvp_status' => 'pending',
        ]);
    }

    public function test_can_invite_internal_personnel_by_personnel_id(): void
    {
        $personnel = Personnel::factory()->create([
            'company_id' => $this->company->id,
        ]);

        $participant = $this->participantService->invite($this->event, [
            'personnel_id' => $personnel->id,
            'role' => 'speaker',
        ]);

        $this->assertDatabaseHas('event_participants', [
            'id' => $participant->id,
            'event_id' => $this->event->id,
            'personnel_id' => $personnel->id,
            'role' => 'speaker',
            'rsvp_status' => 'pending',
        ]);
    }

    public function test_can_invite_external_participant_by_email(): void
    {
        $participant = $this->participantService->invite($this->event, [
            'name' => 'John Doe',
            'email' => 'john.doe@external.com',
            'role' => 'guest',
        ]);

        $this->assertDatabaseHas('event_participants', [
            'id' => $participant->id,
            'event_id' => $this->event->id,
            'name' => 'John Doe',
            'email' => 'john.doe@external.com',
            'role' => 'guest',
            'rsvp_status' => 'pending',
        ]);
    }

    public function test_invite_requires_at_least_one_identifier(): void
    {
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->expectExceptionMessage('Either user_id, personnel_id, or email must be provided');

        $this->participantService->invite($this->event, [
            'role' => 'attendee',
        ]);
    }

    public function test_cannot_invite_duplicate_user(): void
    {
        $invitedUser = User::factory()->create([
            'current_company_id' => $this->company->id,
        ]);

        // First invitation
        $this->participantService->invite($this->event, [
            'user_id' => $invitedUser->id,
            'role' => 'attendee',
        ]);

        // Attempt duplicate invitation
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->expectExceptionMessage('Participant is already invited to this event');

        $this->participantService->invite($this->event, [
            'user_id' => $invitedUser->id,
            'role' => 'speaker',
        ]);
    }

    public function test_cannot_invite_duplicate_personnel(): void
    {
        $personnel = Personnel::factory()->create([
            'company_id' => $this->company->id,
        ]);

        // First invitation
        $this->participantService->invite($this->event, [
            'personnel_id' => $personnel->id,
            'role' => 'attendee',
        ]);

        // Attempt duplicate invitation
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->expectExceptionMessage('Participant is already invited to this event');

        $this->participantService->invite($this->event, [
            'personnel_id' => $personnel->id,
            'role' => 'organizer',
        ]);
    }

    public function test_cannot_invite_duplicate_external_email(): void
    {
        // First invitation
        $this->participantService->invite($this->event, [
            'name' => 'John Doe',
            'email' => 'john@external.com',
            'role' => 'guest',
        ]);

        // Attempt duplicate invitation
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->expectExceptionMessage('Participant is already invited to this event');

        $this->participantService->invite($this->event, [
            'name' => 'John Doe',
            'email' => 'john@external.com',
            'role' => 'speaker',
        ]);
    }

    public function test_invite_sets_default_role_and_status(): void
    {
        $invitedUser = User::factory()->create([
            'current_company_id' => $this->company->id,
        ]);

        $participant = $this->participantService->invite($this->event, [
            'user_id' => $invitedUser->id,
        ]);

        $this->assertEquals('attendee', $participant->role);
        $this->assertEquals('pending', $participant->rsvp_status);
    }

    // ─── RSVP Tests ───────────────────────────────────────────────────────────

    public function test_can_confirm_participation(): void
    {
        $participant = EventParticipant::factory()->create([
            'company_id' => $this->company->id,
            'event_id' => $this->event->id,
            'rsvp_status' => 'pending',
        ]);

        $confirmed = $this->participantService->confirm($participant);

        $this->assertEquals('confirmed', $confirmed->rsvp_status);
        $this->assertDatabaseHas('event_participants', [
            'id' => $participant->id,
            'rsvp_status' => 'confirmed',
        ]);
    }

    public function test_cannot_confirm_already_confirmed_participant(): void
    {
        $participant = EventParticipant::factory()->create([
            'company_id' => $this->company->id,
            'event_id' => $this->event->id,
            'rsvp_status' => 'confirmed',
        ]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->expectExceptionMessage('Participant has already confirmed');

        $this->participantService->confirm($participant);
    }

    public function test_can_decline_participation(): void
    {
        $participant = EventParticipant::factory()->create([
            'company_id' => $this->company->id,
            'event_id' => $this->event->id,
            'rsvp_status' => 'pending',
        ]);

        $declined = $this->participantService->decline($participant);

        $this->assertEquals('declined', $declined->rsvp_status);
        $this->assertDatabaseHas('event_participants', [
            'id' => $participant->id,
            'rsvp_status' => 'declined',
        ]);
    }

    public function test_cannot_decline_already_declined_participant(): void
    {
        $participant = EventParticipant::factory()->create([
            'company_id' => $this->company->id,
            'event_id' => $this->event->id,
            'rsvp_status' => 'declined',
        ]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->expectExceptionMessage('Participant has already declined');

        $this->participantService->decline($participant);
    }

    public function test_can_change_rsvp_from_declined_to_confirmed(): void
    {
        $participant = EventParticipant::factory()->create([
            'company_id' => $this->company->id,
            'event_id' => $this->event->id,
            'rsvp_status' => 'declined',
        ]);

        // Should be able to confirm after declining
        $confirmed = $this->participantService->confirm($participant);

        $this->assertEquals('confirmed', $confirmed->rsvp_status);
    }

    public function test_can_change_rsvp_from_confirmed_to_declined(): void
    {
        $participant = EventParticipant::factory()->create([
            'company_id' => $this->company->id,
            'event_id' => $this->event->id,
            'rsvp_status' => 'confirmed',
        ]);

        // Should be able to decline after confirming
        $declined = $this->participantService->decline($participant);

        $this->assertEquals('declined', $declined->rsvp_status);
    }

    // ─── Bulk Invite Tests ────────────────────────────────────────────────────

    public function test_can_bulk_invite_participants(): void
    {
        $user1 = User::factory()->create(['current_company_id' => $this->company->id]);
        $user2 = User::factory()->create(['current_company_id' => $this->company->id]);

        $participants = [
            ['user_id' => $user1->id, 'role' => 'attendee'],
            ['user_id' => $user2->id, 'role' => 'speaker'],
            ['name' => 'External Guest', 'email' => 'guest@external.com', 'role' => 'guest'],
        ];

        $result = $this->participantService->bulkInvite($this->event, $participants);

        $this->assertCount(3, $result['created']);
        $this->assertCount(0, $result['failed']);

        $this->assertDatabaseHas('event_participants', [
            'event_id' => $this->event->id,
            'user_id' => $user1->id,
        ]);

        $this->assertDatabaseHas('event_participants', [
            'event_id' => $this->event->id,
            'user_id' => $user2->id,
        ]);

        $this->assertDatabaseHas('event_participants', [
            'event_id' => $this->event->id,
            'email' => 'guest@external.com',
        ]);
    }

    public function test_bulk_invite_handles_partial_failures(): void
    {
        $user1 = User::factory()->create(['current_company_id' => $this->company->id]);

        // Pre-invite user1 to create a duplicate scenario
        $this->participantService->invite($this->event, [
            'user_id' => $user1->id,
            'role' => 'attendee',
        ]);

        $participants = [
            ['user_id' => $user1->id, 'role' => 'speaker'], // Duplicate - should fail
            ['name' => 'Valid Guest', 'email' => 'valid@external.com', 'role' => 'guest'], // Should succeed
            ['role' => 'attendee'], // Missing identifier - should fail
        ];

        $result = $this->participantService->bulkInvite($this->event, $participants);

        $this->assertCount(1, $result['created']);
        $this->assertCount(2, $result['failed']);

        // Verify the successful one was created
        $this->assertDatabaseHas('event_participants', [
            'event_id' => $this->event->id,
            'email' => 'valid@external.com',
        ]);

        // Verify failed entries contain error information
        $this->assertArrayHasKey('index', $result['failed'][0]);
        $this->assertArrayHasKey('error', $result['failed'][0]);
    }

    public function test_bulk_invite_with_all_failures(): void
    {
        $participants = [
            ['role' => 'attendee'], // Missing identifier
            ['role' => 'speaker'], // Missing identifier
        ];

        $result = $this->participantService->bulkInvite($this->event, $participants);

        $this->assertCount(0, $result['created']);
        $this->assertCount(2, $result['failed']);
    }

    // ─── Remove Participant Tests ─────────────────────────────────────────────

    public function test_can_remove_participant(): void
    {
        $participant = EventParticipant::factory()->create([
            'company_id' => $this->company->id,
            'event_id' => $this->event->id,
        ]);

        $result = $this->participantService->remove($participant);

        $this->assertTrue($result);
        $this->assertSoftDeleted('event_participants', ['id' => $participant->id]);
    }

    // ─── Relationship Tests ───────────────────────────────────────────────────

    public function test_participant_belongs_to_event(): void
    {
        $participant = EventParticipant::factory()->create([
            'company_id' => $this->company->id,
            'event_id' => $this->event->id,
        ]);

        $this->assertInstanceOf(Event::class, $participant->event);
        $this->assertEquals($this->event->id, $participant->event->id);
    }

    public function test_participant_belongs_to_user(): void
    {
        $invitedUser = User::factory()->create([
            'current_company_id' => $this->company->id,
        ]);

        $participant = EventParticipant::factory()->create([
            'company_id' => $this->company->id,
            'event_id' => $this->event->id,
            'user_id' => $invitedUser->id,
        ]);

        $this->assertInstanceOf(User::class, $participant->user);
        $this->assertEquals($invitedUser->id, $participant->user->id);
    }

    public function test_participant_belongs_to_personnel(): void
    {
        $personnel = Personnel::factory()->create([
            'company_id' => $this->company->id,
        ]);

        $participant = EventParticipant::factory()->create([
            'company_id' => $this->company->id,
            'event_id' => $this->event->id,
            'personnel_id' => $personnel->id,
        ]);

        $this->assertInstanceOf(Personnel::class, $participant->personnel);
        $this->assertEquals($personnel->id, $participant->personnel->id);
    }

    public function test_event_has_many_participants(): void
    {
        EventParticipant::factory()->count(3)->create([
            'company_id' => $this->company->id,
            'event_id' => $this->event->id,
        ]);

        $this->assertCount(3, $this->event->participants);
    }

    // ─── Tenant Isolation Tests ───────────────────────────────────────────────

    public function test_participants_are_company_scoped(): void
    {
        ['user' => $userB, 'company' => $companyB] = $this->setUpCompanyAndUser();

        $eventB = Event::factory()->create([
            'company_id' => $companyB->id,
        ]);

        $participantA = EventParticipant::factory()->create([
            'company_id' => $this->company->id,
            'event_id' => $this->event->id,
        ]);

        $participantB = EventParticipant::factory()->create([
            'company_id' => $companyB->id,
            'event_id' => $eventB->id,
        ]);

        // User A should only see their participant
        $this->actingAs($this->user);
        $participantsA = EventParticipant::all();
        $this->assertCount(1, $participantsA);
        $this->assertEquals($participantA->id, $participantsA->first()->id);

        // User B should only see their participant
        $this->actingAs($userB);
        $participantsB = EventParticipant::all();
        $this->assertCount(1, $participantsB);
        $this->assertEquals($participantB->id, $participantsB->first()->id);
    }

    // ─── Role Tests ───────────────────────────────────────────────────────────

    public function test_can_invite_with_different_roles(): void
    {
        $roles = ['organizer', 'speaker', 'attendee', 'guest'];

        foreach ($roles as $role) {
            $user = User::factory()->create(['current_company_id' => $this->company->id]);

            $participant = $this->participantService->invite($this->event, [
                'user_id' => $user->id,
                'role' => $role,
            ]);

            $this->assertEquals($role, $participant->role);
        }
    }

    public function test_multiple_participants_with_different_rsvp_statuses(): void
    {
        $pending = EventParticipant::factory()->create([
            'company_id' => $this->company->id,
            'event_id' => $this->event->id,
            'rsvp_status' => 'pending',
        ]);

        $confirmed = EventParticipant::factory()->create([
            'company_id' => $this->company->id,
            'event_id' => $this->event->id,
            'rsvp_status' => 'confirmed',
        ]);

        $declined = EventParticipant::factory()->create([
            'company_id' => $this->company->id,
            'event_id' => $this->event->id,
            'rsvp_status' => 'declined',
        ]);

        $participants = $this->event->participants;

        $this->assertCount(3, $participants);
        $this->assertEquals(1, $participants->where('rsvp_status', 'pending')->count());
        $this->assertEquals(1, $participants->where('rsvp_status', 'confirmed')->count());
        $this->assertEquals(1, $participants->where('rsvp_status', 'declined')->count());
    }
}
