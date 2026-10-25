<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\CRM;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\CRM\Concerns\ProvidesCrmOptions;
use App\Http\Requests\CRM\AdvanceOpportunityRequest;
use App\Http\Requests\CRM\LoseOpportunityRequest;
use App\Http\Requests\CRM\StoreOpportunityRequest;
use App\Http\Requests\CRM\UpdateOpportunityRequest;
use App\Http\Requests\CRM\WinOpportunityRequest;
use App\Http\Resources\CRM\ActivityResource;
use App\Http\Resources\CRM\OpportunityResource;
use App\Models\CRM\Activity;
use App\Models\CRM\Opportunity;
use App\Models\Sales\Quote;
use App\Services\CRM\OpportunityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OpportunityController extends Controller
{
    use ProvidesCrmOptions;

    public function __construct(
        private readonly OpportunityService $opportunityService,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Opportunity::class);

        $filters = $request->only(['search', 'stage', 'mine']);
        $filters['stage'] ??= 'open';

        $opportunities = Opportunity::query()
            ->with(['contact', 'customer', 'assignee'])
            ->when($filters['search'] ?? null, function ($query, $search) {
                $like = '%' . $search . '%';
                $query->where(fn ($query) => $query->whereLike('title', $like)->orWhereLike('reference', $like));
            })
            ->when($filters['stage'] === 'open', fn ($query) => $query->whereIn('stage', Opportunity::OPEN_STAGES))
            ->when($filters['stage'] === 'closed', fn ($query) => $query->whereIn('stage', Opportunity::CLOSED_STAGES))
            ->when(in_array($filters['stage'], array_keys(Opportunity::STAGE_PROBABILITIES), true),
                fn ($query) => $query->where('stage', $filters['stage']))
            ->when($request->boolean('mine'), fn ($query) => $query->where('assigned_to', $request->user()->id))
            ->orderByRaw('expected_close_date IS NULL')
            ->orderBy('expected_close_date')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('CRM/Opportunities/Index', [
            'opportunities' => OpportunityResource::collection($opportunities),
            'filters'       => $filters,
            'pipeline'      => $this->opportunityService->pipeline(),
            'forecast'      => $this->opportunityService->forecast(3),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', Opportunity::class);

        return Inertia::render('CRM/Opportunities/Create', [
            ...$this->formOptions(),
            'defaults' => [
                'contact_id'  => $request->integer('contact_id') ?: null,
                'customer_id' => $request->integer('customer_id') ?: null,
            ],
        ]);
    }

    public function store(StoreOpportunityRequest $request): RedirectResponse
    {
        $this->authorize('create', Opportunity::class);

        $opportunity = $this->opportunityService->create($request->validated());

        return redirect()->route('crm.opportunities.show', $opportunity)
            ->with('success', "Opportunité {$opportunity->reference} créée.");
    }

    public function show(Opportunity $opportunity): Response
    {
        $this->authorize('view', $opportunity);

        $opportunity->load(['contact', 'customer', 'assignee', 'lead:id,reference,title', 'wonQuote:id,reference']);

        return Inertia::render('CRM/Opportunities/Show', [
            'opportunity'   => new OpportunityResource($opportunity),
            'lead'          => $opportunity->lead,
            'wonQuote'      => $opportunity->wonQuote,
            'stages'        => Opportunity::OPEN_STAGES,
            'probabilities' => Opportunity::STAGE_PROBABILITIES,
            'activities'    => ActivityResource::collection(
                Activity::where('related_type', Opportunity::class)->where('related_id', $opportunity->id)
                    ->with('assignee')->orderByDesc('due_date')->get()
            ),
            'quotes'        => $opportunity->customer_id
                ? Quote::where('customer_id', $opportunity->customer_id)->latest()->get(['id', 'reference', 'total_ttc', 'status'])
                    ->map(fn (Quote $quote) => ['value' => $quote->id, 'label' => "{$quote->reference} ({$quote->status})"])
                : [],
        ]);
    }

    public function edit(Opportunity $opportunity): Response
    {
        $this->authorize('update', $opportunity);

        return Inertia::render('CRM/Opportunities/Edit', [
            'opportunity' => new OpportunityResource($opportunity),
            ...$this->formOptions(),
        ]);
    }

    public function update(UpdateOpportunityRequest $request, Opportunity $opportunity): RedirectResponse
    {
        $this->authorize('update', $opportunity);

        $this->opportunityService->update($opportunity, $request->validated());

        return redirect()->route('crm.opportunities.show', $opportunity)->with('success', 'Opportunité mise à jour.');
    }

    public function destroy(Opportunity $opportunity): RedirectResponse
    {
        $this->authorize('delete', $opportunity);

        $this->opportunityService->delete($opportunity);

        return redirect()->route('crm.opportunities.index')->with('success', 'Opportunité supprimée.');
    }

    public function advance(AdvanceOpportunityRequest $request, Opportunity $opportunity): RedirectResponse
    {
        $this->authorize('advance', $opportunity);

        $probability = $request->validated('probability');
        $this->opportunityService->advance(
            $opportunity,
            $request->validated('stage'),
            $probability !== null ? (int) $probability : null
        );

        return back()->with('success', 'Étape mise à jour.');
    }

    public function win(WinOpportunityRequest $request, Opportunity $opportunity): RedirectResponse
    {
        $this->authorize('win', $opportunity);

        $this->opportunityService->win($opportunity, $request->validated());

        return back()->with('success', 'Opportunité gagnée.');
    }

    public function lose(LoseOpportunityRequest $request, Opportunity $opportunity): RedirectResponse
    {
        $this->authorize('lose', $opportunity);

        $this->opportunityService->lose(
            $opportunity,
            $request->validated('lost_reason'),
            $request->validated('actual_close_date')
        );

        return back()->with('success', 'Opportunité marquée perdue.');
    }

    private function formOptions(): array
    {
        return [
            'contacts'  => $this->contactOptions(),
            'customers' => $this->customerOptions(),
            'users'     => $this->userOptions(),
            'stages'    => Opportunity::OPEN_STAGES,
        ];
    }
}
