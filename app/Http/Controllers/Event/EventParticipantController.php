<?php

declare(strict_types=1);

namespace App\Http\Controllers\Event;

use App\Http\Controllers\Controller;
use App\Http\Requests\Event\InviteParticipantRequest;
use App\Http\Resources\Event\EventParticipantResource;
use App\Models\Event\Event;
use App\Models\Event\EventParticipant;
use App\Services\Event\EventParticipantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class EventParticipantController extends Controller
{
    public function __construct(
        private readonly EventParticipantService $eventParticipantService,
    ) {}

    public function index(Event $event): AnonymousResourceCollection
    {
        $this->authorize('viewAny', EventParticipant::class);

        $participants = $event->participants()
            ->with(['user', 'personnel'])
            ->get();

        return EventParticipantResource::collection($participants);
    }

    public function store(InviteParticipantRequest $request, Event $event): JsonResponse
    {
        $this->authorize('create', EventParticipant::class);

        $participant = $this->eventParticipantService->invite($event, $request->validated());

        return (new EventParticipantResource($participant))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Event $event, EventParticipant $participant): EventParticipantResource
    {
        $this->authorize('view', $participant);

        $participant->load(['user', 'personnel', 'event']);

        return new EventParticipantResource($participant);
    }

    public function destroy(Event $event, EventParticipant $participant): JsonResponse
    {
        $this->authorize('delete', $participant);

        $this->eventParticipantService->remove($participant);

        return response()->json(null, 204);
    }

    public function confirm(Event $event, EventParticipant $participant): EventParticipantResource
    {
        $this->authorize('update', $participant);

        $participant = $this->eventParticipantService->confirm($participant);

        return new EventParticipantResource($participant);
    }

    public function decline(Event $event, EventParticipant $participant): EventParticipantResource
    {
        $this->authorize('update', $participant);

        $participant = $this->eventParticipantService->decline($participant);

        return new EventParticipantResource($participant);
    }

    public function bulkInvite(Request $request, Event $event): JsonResponse
    {
        $this->authorize('create', EventParticipant::class);

        $participants = $request->input('participants', []);
        $result = $this->eventParticipantService->bulkInvite($event, $participants);

        return response()->json([
            'created' => EventParticipantResource::collection($result['created']),
            'failed' => $result['failed'],
        ], 201);
    }
}
