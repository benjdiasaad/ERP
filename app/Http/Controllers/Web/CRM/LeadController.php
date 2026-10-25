<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\CRM;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\CRM\Concerns\ProvidesCrmOptions;
use App\Http\Requests\CRM\AssignLeadRequest;
use App\Http\Requests\CRM\ConvertLeadRequest;
use App\Http\Requests\CRM\StoreLeadRequest;
use App\Http\Requests\CRM\UpdateLeadRequest;
use App\Http\Resources\CRM\ActivityResource;
use App\Http\Resources\CRM\LeadResource;
use App\Models\CRM\Activity;
use App\Models\CRM\Contact;
use App\Models\CRM\Lead;
use App\Services\CRM\LeadService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LeadController extends Controller
{
    use ProvidesCrmOptions;

    public function __construct(
        private readonly LeadService $leadService,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Lead::class);

        $filters = $request->only(['search', 'status', 'mine']);

        $leads = Lead::query()
            ->with(['contact', 'assignee'])
            ->when($filters['search'] ?? null, function ($query, $search) {
                $like = '%' . $search . '%';
                $query->where(fn ($query) => $query->whereLike('title', $like)->orWhereLike('reference', $like));
            })
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($request->boolean('mine'), fn ($query) => $query->where('assigned_to', $request->user()->id))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('CRM/Leads/Index', [
            'leads'    => LeadResource::collection($leads),
            'filters'  => $filters,
            'pipeline' => $this->leadService->pipelineStats(),
            'statuses' => Lead::STATUSES,
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', Lead::class);

        return Inertia::render('CRM/Leads/Create', [
            ...$this->formOptions(),
            'defaults' => ['contact_id' => $request->integer('contact_id') ?: null],
        ]);
    }

    public function store(StoreLeadRequest $request): RedirectResponse
    {
        $this->authorize('create', Lead::class);

        $lead = $this->leadService->create($request->validated());

        return redirect()->route('crm.leads.show', $lead)->with('success', "Lead {$lead->reference} créé.");
    }

    public function show(Lead $lead): Response
    {
        $this->authorize('view', $lead);

        $lead->load(['contact', 'assignee', 'opportunity:id,reference,title,stage,value']);

        return Inertia::render('CRM/Leads/Show', [
            'lead'        => new LeadResource($lead),
            'opportunity' => $lead->opportunity,
            'activities'  => ActivityResource::collection(
                Activity::where('related_type', Lead::class)->where('related_id', $lead->id)
                    ->with('assignee')->orderByDesc('due_date')->get()
            ),
            'users'       => $this->userOptions(),
            'customers'   => $this->customerOptions(),
        ]);
    }

    public function edit(Lead $lead): Response
    {
        $this->authorize('update', $lead);

        return Inertia::render('CRM/Leads/Edit', [
            'lead' => new LeadResource($lead),
            ...$this->formOptions(),
        ]);
    }

    public function update(UpdateLeadRequest $request, Lead $lead): RedirectResponse
    {
        $this->authorize('update', $lead);

        $this->leadService->update($lead, $request->validated());

        return redirect()->route('crm.leads.show', $lead)->with('success', 'Lead mis à jour.');
    }

    public function destroy(Lead $lead): RedirectResponse
    {
        $this->authorize('delete', $lead);

        $this->leadService->delete($lead);

        return redirect()->route('crm.leads.index')->with('success', 'Lead supprimé.');
    }

    public function qualify(Request $request, Lead $lead): RedirectResponse
    {
        $this->authorize('qualify', $lead);

        $this->leadService->qualify($lead, $request->validate([
            'estimated_value'     => ['nullable', 'numeric', 'min:0'],
            'probability'         => ['nullable', 'integer', 'between:0,100'],
            'expected_close_date' => ['nullable', 'date'],
        ]));

        return back()->with('success', 'Lead qualifié.');
    }

    public function convert(ConvertLeadRequest $request, Lead $lead): RedirectResponse
    {
        $this->authorize('convert', $lead);

        ['opportunity' => $opportunity] = $this->leadService->convert($lead, $request->validated());

        return redirect()->route('crm.opportunities.show', $opportunity)
            ->with('success', "Lead converti en opportunité {$opportunity->reference}.");
    }

    public function assign(AssignLeadRequest $request, Lead $lead): RedirectResponse
    {
        $this->authorize('assign', $lead);

        $assignedTo = $request->validated('assigned_to');
        $this->leadService->assign($lead, $assignedTo !== null ? (int) $assignedTo : null);

        return back()->with('success', 'Responsable mis à jour.');
    }

    private function formOptions(): array
    {
        return [
            'contacts' => $this->contactOptions(),
            'users'    => $this->userOptions(),
            'sources'  => Contact::SOURCES,
        ];
    }
}
