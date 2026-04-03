<?php

declare(strict_types=1);

namespace App\Http\Controllers\Event;

use App\Http\Controllers\Controller;
use App\Http\Requests\Event\StoreEventRequest;
use App\Http\Requests\Event\UpdateEventRequest;
use App\Http\Resources\Event\EventResource;
use App\Models\Event\Event;
use App\Services\Event\EventService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class EventController extends Controller
{
    public function __construct(
        private readonly EventService $eventService,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Event::class);

        $events = Event::query()
            ->with(['category', 'participants'])
            ->when($request->input('search'), function ($query, $search) {
                $query->where('title', 'ilike', "%{$search}%");
            })
            ->when($request->input('status'), function ($query, $status) {
                $query->where('status', $status);
            })
            ->when($request->input('type'), function ($query, $type) {
                $query->where('type', $type);
            })
            ->when($request->input('category_id'), function ($query, $categoryId) {
                $query->where('event_category_id', $categoryId);
            })
            ->when($request->input('is_mandatory'), function ($query, $isMandatory) {
                $query->where('is_mandatory', filter_var($isMandatory, FILTER_VALIDATE_BOOLEAN));
            })
            ->orderBy('start_date', 'desc')
            ->paginate($request->input('per_page', 15));

        return EventResource::collection($events);
    }

    public function store(StoreEventRequest $request): JsonResponse
    {
        $this->authorize('create', Event::class);

        $event = $this->eventService->create($request->validated());

        return (new EventResource($event))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Event $event): EventResource
    {
        $this->authorize('view', $event);

        $event->load(['category', 'participants']);

        return new EventResource($event);
    }

    public function update(UpdateEventRequest $request, Event $event): EventResource
    {
        $this->authorize('update', $event);

        $event = $this->eventService->update($event, $request->validated());

        return new EventResource($event);
    }

    public function destroy(Event $event): JsonResponse
    {
        $this->authorize('delete', $event);

        $this->eventService->delete($event);

        return response()->json(null, 204);
    }

    public function confirm(Event $event): EventResource
    {
        $this->authorize('update', $event);

        $event = $this->eventService->confirm($event);

        return new EventResource($event);
    }

    public function cancel(Request $request, Event $event): EventResource
    {
        $this->authorize('update', $event);

        $reason = $request->input('reason');
        $event = $this->eventService->cancel($event, $reason);

        return new EventResource($event);
    }

    public function complete(Event $event): EventResource
    {
        $this->authorize('update', $event);

        $event = $this->eventService->complete($event);

        return new EventResource($event);
    }

    public function postpone(Request $request, Event $event): EventResource
    {
        $this->authorize('update', $event);

        $event = $this->eventService->postpone($event, $request->all());

        return new EventResource($event);
    }

    public function upcoming(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Event::class);

        $limit = $request->query('limit') ? (int) $request->query('limit') : null;
        $events = $this->eventService->getUpcoming($limit);

        return EventResource::collection($events);
    }

    public function mandatory(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Event::class);

        $events = $this->eventService->getMandatory();

        return EventResource::collection($events);
    }

    public function expiringSoon(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Event::class);

        $days = $request->query('days', 7);
        $events = $this->eventService->getExpiringSoon((int) $days);

        return EventResource::collection($events);
    }
}
