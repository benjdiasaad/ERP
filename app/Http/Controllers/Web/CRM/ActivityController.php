<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\CRM;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\CRM\Concerns\ProvidesCrmOptions;
use App\Http\Requests\CRM\StoreActivityRequest;
use App\Http\Requests\CRM\UpdateActivityRequest;
use App\Http\Resources\CRM\ActivityResource;
use App\Models\CRM\Activity;
use App\Models\CRM\Contact;
use App\Models\CRM\Lead;
use App\Models\CRM\Opportunity;
use App\Services\CRM\ActivityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ActivityController extends Controller
{
    use ProvidesCrmOptions;

    /** Views offered as tabs on the index page. */
    private const VIEWS = ['todo', 'overdue', 'upcoming', 'completed', 'all'];

    public function __construct(
        private readonly ActivityService $activityService,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Activity::class);

        $filters = $request->only(['search', 'view', 'type', 'mine']);
        $filters['view'] = in_array($filters['view'] ?? null, self::VIEWS, true) ? $filters['view'] : 'todo';
        $open = ['pending', 'in_progress'];

        $activities = Activity::query()
            ->with(['related', 'assignee'])
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->whereLike('subject', '%' . $search . '%'))
            ->when($filters['type'] ?? null, fn ($query, $type) => $query->where('type', $type))
            ->when($request->boolean('mine'), fn ($query) => $query->where('assigned_to', $request->user()->id))
            ->when($filters['view'] === 'todo', fn ($query) => $query->whereIn('status', $open))
            ->when($filters['view'] === 'overdue', fn ($query) => $query->whereIn('status', $open)
                ->whereNotNull('due_date')->where('due_date', '<', now()))
            ->when($filters['view'] === 'upcoming', fn ($query) => $query->whereIn('status', $open)
                ->whereBetween('due_date', [now(), now()->addDays(7)]))
            ->when($filters['view'] === 'completed', fn ($query) => $query->where('status', 'completed'))
            ->when(in_array($filters['view'], ['completed', 'all'], true),
                fn ($query) => $query->orderByDesc('due_date'),
                fn ($query) => $query->orderByRaw('due_date IS NULL')->orderBy('due_date'))
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('CRM/Activities/Index', [
            'activities' => ActivityResource::collection($activities),
            'filters'    => $filters,
            'counts'     => [
                'overdue' => $this->activityService->getOverdue()->count(),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', Activity::class);

        $relatedType = $request->string('related_type')->toString();

        return Inertia::render('CRM/Activities/Create', [
            ...$this->formOptions(),
            'defaults' => [
                'related_type' => array_key_exists($relatedType, Activity::RELATED_TYPES) ? $relatedType : null,
                'related_id'   => $request->integer('related_id') ?: null,
            ],
            'back' => url()->previous() !== url()->current() ? url()->previous() : route('crm.activities.index'),
        ]);
    }

    public function store(StoreActivityRequest $request): RedirectResponse
    {
        $this->authorize('create', Activity::class);

        $activity = $this->activityService->create($request->validated());

        return redirect()->to($this->relatedUrl($activity) ?? route('crm.activities.index'))
            ->with('success', 'Activité enregistrée.');
    }

    public function edit(Activity $activity): Response
    {
        $this->authorize('update', $activity);

        return Inertia::render('CRM/Activities/Edit', [
            'activity' => new ActivityResource($activity),
            ...$this->formOptions(),
        ]);
    }

    public function update(UpdateActivityRequest $request, Activity $activity): RedirectResponse
    {
        $this->authorize('update', $activity);

        $this->activityService->update($activity, $request->validated());

        return redirect()->route('crm.activities.index')->with('success', 'Activité mise à jour.');
    }

    public function destroy(Activity $activity): RedirectResponse
    {
        $this->authorize('delete', $activity);

        $this->activityService->delete($activity);

        return back()->with('success', 'Activité supprimée.');
    }

    public function complete(Request $request, Activity $activity): RedirectResponse
    {
        $this->authorize('complete', $activity);

        $request->validate(['outcome' => ['nullable', 'string', 'max:5000']]);
        $this->activityService->complete($activity, $request->input('outcome'));

        return back()->with('success', 'Activité terminée.');
    }

    private function relatedUrl(Activity $activity): ?string
    {
        return match ($activity->related_type) {
            Contact::class                 => route('crm.contacts.show', $activity->related_id),
            Lead::class                    => route('crm.leads.show', $activity->related_id),
            Opportunity::class             => route('crm.opportunities.show', $activity->related_id),
            default                        => null,
        };
    }

    /**
     * Records an activity can be linked to, grouped by the API alias.
     */
    private function formOptions(): array
    {
        return [
            'users'   => $this->userOptions(),
            'related' => [
                'contact'     => $this->contactOptions(),
                'lead'        => Lead::whereIn('status', Lead::OPEN_STATUSES)->latest()->get(['id', 'reference', 'title'])
                    ->map(fn (Lead $lead) => ['value' => $lead->id, 'label' => "{$lead->reference} · {$lead->title}"])->all(),
                'opportunity' => Opportunity::whereIn('stage', Opportunity::OPEN_STAGES)->latest()->get(['id', 'reference', 'title'])
                    ->map(fn (Opportunity $o) => ['value' => $o->id, 'label' => "{$o->reference} · {$o->title}"])->all(),
                'customer'    => $this->customerOptions(),
            ],
            'types'      => Activity::TYPES,
            'priorities' => Activity::PRIORITIES,
        ];
    }
}
