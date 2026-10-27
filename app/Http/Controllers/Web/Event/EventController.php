<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Event;

use App\Http\Controllers\Controller;
use App\Http\Requests\Event\InviteParticipantRequest;
use App\Http\Requests\Event\StoreEventRequest;
use App\Http\Requests\Event\UpdateEventRequest;
use App\Http\Resources\Event\EventResource;
use App\Models\Event\Event;
use App\Models\Event\EventCategory;
use App\Models\Event\EventParticipant;
use App\Models\Personnel\Personnel;
use App\Services\Event\EventParticipantService;
use App\Services\Event\EventService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Company events: planned → confirmed → completed (or postponed / cancelled), with participants.
 */
class EventController extends Controller
{
    private const STATUSES = ['planned', 'confirmed', 'postponed', 'completed', 'cancelled'];

    public function __construct(
        private readonly EventService $eventService,
        private readonly EventParticipantService $participantService,
    ) {}

    public function index(Request $request): Response
    {
        $filters = $request->only(['search', 'status', 'event_category_id', 'period']);

        $events = Event::query()
            ->with('category')
            ->withCount('participants')
            ->when($filters['search'] ?? null, function ($query, $search) {
                $like = '%' . $search . '%';
                $query->where(fn ($query) => $query->whereLike('title', $like)->orWhereLike('reference', $like)->orWhereLike('location', $like));
            })
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['event_category_id'] ?? null, fn ($query, $id) => $query->where('event_category_id', $id))
            ->when(($filters['period'] ?? null) === 'upcoming', fn ($query) => $query->where('end_date', '>=', now()))
            ->when(($filters['period'] ?? null) === 'past', fn ($query) => $query->where('end_date', '<', now()))
            // Past events: most recent first; otherwise the next ones first.
            ->orderBy('start_date', ($filters['period'] ?? null) === 'past' ? 'desc' : 'asc')
            ->paginate(15)
            ->withQueryString();

        $counts = Event::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return Inertia::render('Event/Events/Index', [
            'events'     => EventResource::collection($events),
            'counts'     => collect(self::STATUSES)->mapWithKeys(fn ($status) => [$status => (int) ($counts[$status] ?? 0)]),
            'categories' => $this->categoryOptions(),
            'filters'    => $filters,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Event/Events/Create', ['categories' => $this->categoryOptions()]);
    }

    public function store(StoreEventRequest $request): RedirectResponse
    {
        $event = $this->eventService->create(collect($request->validated())->except('status')->all());

        return redirect()->route('organisation.events.show', $event)->with('success', "Événement {$event->reference} créé.");
    }

    public function show(Event $event): Response
    {
        $event->load(['category', 'participants.personnel:id,first_name,last_name,matricule', 'participants.user:id,first_name,last_name,email']);

        return Inertia::render('Event/Events/Show', [
            'event'      => new EventResource($event),
            'personnels' => in_array($event->status, ['completed', 'cancelled'], true) ? [] : Personnel::where('status', 'active')
                ->whereNotIn('id', $event->participants->pluck('personnel_id')->filter())
                ->orderBy('last_name')->get(['id', 'first_name', 'last_name', 'matricule'])
                ->map(fn (Personnel $p) => ['value' => $p->id, 'label' => "{$p->last_name} {$p->first_name} ({$p->matricule})"]),
        ]);
    }

    public function edit(Event $event): Response|RedirectResponse
    {
        if (in_array($event->status, ['completed', 'cancelled'], true)) {
            return redirect()->route('organisation.events.show', $event)->with('error', 'Un événement terminé ou annulé ne peut plus être modifié.');
        }

        return Inertia::render('Event/Events/Edit', [
            'event'      => new EventResource($event),
            'categories' => $this->categoryOptions(),
        ]);
    }

    public function update(UpdateEventRequest $request, Event $event): RedirectResponse
    {
        $this->eventService->update($event, collect($request->validated())->except('status')->all());

        return redirect()->route('organisation.events.show', $event)->with('success', 'Événement mis à jour.');
    }

    public function destroy(Event $event): RedirectResponse
    {
        $this->eventService->delete($event);

        return redirect()->route('organisation.events.index')->with('success', 'Événement supprimé.');
    }

    public function confirm(Event $event): RedirectResponse
    {
        $this->eventService->confirm($event);

        return back()->with('success', 'Événement confirmé.');
    }

    public function complete(Event $event): RedirectResponse
    {
        $this->eventService->complete($event);

        return back()->with('success', 'Événement terminé.');
    }

    public function cancel(Request $request, Event $event): RedirectResponse
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:1000']]);
        $this->eventService->cancel($event, $data['reason'] ?? null);

        return back()->with('success', 'Événement annulé.');
    }

    public function postpone(Request $request, Event $event): RedirectResponse
    {
        $data = $request->validate([
            'start_date' => ['required', 'date'],
            'end_date'   => ['required', 'date', 'after_or_equal:start_date'],
            'reason'     => ['nullable', 'string', 'max:1000'],
        ]);
        $this->eventService->postpone($event, $data);

        return back()->with('success', 'Événement reporté.');
    }

    public function invite(InviteParticipantRequest $request, Event $event): RedirectResponse
    {
        $this->participantService->invite($event, $request->validated());

        return back()->with('success', 'Participant invité.');
    }

    public function removeParticipant(Event $event, EventParticipant $participant): RedirectResponse
    {
        abort_unless($participant->event_id === $event->id, 404);
        $this->participantService->remove($participant);

        return back()->with('success', 'Participant retiré.');
    }

    public function rsvp(Request $request, Event $event, EventParticipant $participant): RedirectResponse
    {
        abort_unless($participant->event_id === $event->id, 404);
        $data = $request->validate(['rsvp_status' => ['required', 'in:confirmed,declined']]);

        $data['rsvp_status'] === 'confirmed'
            ? $this->participantService->confirm($participant)
            : $this->participantService->decline($participant);

        return back()->with('success', 'Réponse enregistrée.');
    }

    private function categoryOptions()
    {
        return EventCategory::orderBy('name')->get(['id', 'name', 'color'])
            ->map(fn (EventCategory $c) => ['value' => $c->id, 'label' => $c->name, 'color' => $c->color]);
    }
}
