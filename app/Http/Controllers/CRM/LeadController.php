<?php

declare(strict_types=1);

namespace App\Http\Controllers\CRM;

use App\Http\Controllers\Controller;
use App\Http\Requests\CRM\AssignLeadRequest;
use App\Http\Requests\CRM\ConvertLeadRequest;
use App\Http\Requests\CRM\StoreLeadRequest;
use App\Http\Requests\CRM\UpdateLeadRequest;
use App\Http\Resources\CRM\LeadResource;
use App\Http\Resources\CRM\OpportunityResource;
use App\Models\CRM\Lead;
use App\Services\CRM\LeadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class LeadController extends Controller
{
    public function __construct(
        private readonly LeadService $leadService,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Lead::class);

        $leads = Lead::query()
            ->with(['contact', 'assignee'])
            ->when($request->input('search'), function ($query, $search) {
                $like = '%' . $search . '%';
                $query->where(function ($query) use ($like) {
                    $query->whereLike('title', $like)
                        ->orWhereLike('reference', $like);
                });
            })
            ->when($request->input('status'), fn ($query, $status) => $query->where('status', $status))
            ->when($request->input('source'), fn ($query, $source) => $query->where('source', $source))
            ->when($request->input('assigned_to'), fn ($query, $userId) => $query->where('assigned_to', $userId))
            ->when($request->input('contact_id'), fn ($query, $id) => $query->where('contact_id', $id))
            ->orderByDesc('created_at')
            ->paginate($request->input('per_page', 15));

        return LeadResource::collection($leads);
    }

    public function store(StoreLeadRequest $request): JsonResponse
    {
        $this->authorize('create', Lead::class);

        $lead = $this->leadService->create($request->validated());

        return (new LeadResource($lead->load(['contact', 'assignee'])))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Lead $lead): LeadResource
    {
        $this->authorize('view', $lead);

        $lead->load(['contact', 'assignee']);

        return new LeadResource($lead);
    }

    public function update(UpdateLeadRequest $request, Lead $lead): LeadResource
    {
        $this->authorize('update', $lead);

        $lead = $this->leadService->update($lead, $request->validated());

        return new LeadResource($lead);
    }

    public function destroy(Lead $lead): JsonResponse
    {
        $this->authorize('delete', $lead);

        $this->leadService->delete($lead);

        return response()->json(null, 204);
    }

    public function qualify(Request $request, Lead $lead): LeadResource
    {
        $this->authorize('qualify', $lead);

        $data = $request->validate([
            'estimated_value'     => ['nullable', 'numeric', 'min:0'],
            'probability'         => ['nullable', 'integer', 'between:0,100'],
            'expected_close_date' => ['nullable', 'date'],
        ]);

        $lead = $this->leadService->qualify($lead, $data);

        return new LeadResource($lead);
    }

    public function convert(ConvertLeadRequest $request, Lead $lead): JsonResponse
    {
        $this->authorize('convert', $lead);

        ['lead' => $lead, 'opportunity' => $opportunity] = $this->leadService->convert($lead, $request->validated());

        return response()->json([
            'data' => [
                'lead'        => new LeadResource($lead),
                'opportunity' => new OpportunityResource($opportunity),
            ],
        ], 201);
    }

    public function assign(AssignLeadRequest $request, Lead $lead): LeadResource
    {
        $this->authorize('assign', $lead);

        $assignedTo = $request->validated('assigned_to');
        $lead = $this->leadService->assign($lead, $assignedTo !== null ? (int) $assignedTo : null);

        return new LeadResource($lead);
    }

    public function pipeline(): JsonResponse
    {
        $this->authorize('viewAny', Lead::class);

        return response()->json(['data' => $this->leadService->pipelineStats()]);
    }
}
