<?php

declare(strict_types=1);

namespace App\Http\Controllers\CRM;

use App\Http\Controllers\Controller;
use App\Http\Requests\CRM\AdvanceOpportunityRequest;
use App\Http\Requests\CRM\LoseOpportunityRequest;
use App\Http\Requests\CRM\StoreOpportunityRequest;
use App\Http\Requests\CRM\UpdateOpportunityRequest;
use App\Http\Requests\CRM\WinOpportunityRequest;
use App\Http\Resources\CRM\OpportunityResource;
use App\Models\CRM\Opportunity;
use App\Services\CRM\OpportunityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class OpportunityController extends Controller
{
    public function __construct(
        private readonly OpportunityService $opportunityService,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Opportunity::class);

        $opportunities = Opportunity::query()
            ->with(['contact', 'customer', 'assignee'])
            ->when($request->input('search'), function ($query, $search) {
                $like = '%' . mb_strtolower($search) . '%';
                $query->where(function ($query) use ($like) {
                    $query->whereRaw('LOWER(title) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(reference) LIKE ?', [$like]);
                });
            })
            ->when($request->input('stage'), fn ($query, $stage) => $query->where('stage', $stage))
            ->when($request->boolean('open'), fn ($query) => $query->whereIn('stage', Opportunity::OPEN_STAGES))
            ->when($request->input('assigned_to'), fn ($query, $userId) => $query->where('assigned_to', $userId))
            ->when($request->input('customer_id'), fn ($query, $id) => $query->where('customer_id', $id))
            ->when($request->input('contact_id'), fn ($query, $id) => $query->where('contact_id', $id))
            ->orderBy('expected_close_date')
            ->paginate($request->input('per_page', 15));

        return OpportunityResource::collection($opportunities);
    }

    public function store(StoreOpportunityRequest $request): JsonResponse
    {
        $this->authorize('create', Opportunity::class);

        $opportunity = $this->opportunityService->create($request->validated());

        return (new OpportunityResource($opportunity->load(['contact', 'customer', 'assignee'])))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Opportunity $opportunity): OpportunityResource
    {
        $this->authorize('view', $opportunity);

        $opportunity->load(['contact', 'customer', 'assignee']);

        return new OpportunityResource($opportunity);
    }

    public function update(UpdateOpportunityRequest $request, Opportunity $opportunity): OpportunityResource
    {
        $this->authorize('update', $opportunity);

        $opportunity = $this->opportunityService->update($opportunity, $request->validated());

        return new OpportunityResource($opportunity);
    }

    public function destroy(Opportunity $opportunity): JsonResponse
    {
        $this->authorize('delete', $opportunity);

        $this->opportunityService->delete($opportunity);

        return response()->json(null, 204);
    }

    public function advance(AdvanceOpportunityRequest $request, Opportunity $opportunity): OpportunityResource
    {
        $this->authorize('advance', $opportunity);

        $probability = $request->validated('probability');
        $opportunity = $this->opportunityService->advance(
            $opportunity,
            $request->validated('stage'),
            $probability !== null ? (int) $probability : null
        );

        return new OpportunityResource($opportunity);
    }

    public function win(WinOpportunityRequest $request, Opportunity $opportunity): OpportunityResource
    {
        $this->authorize('win', $opportunity);

        $opportunity = $this->opportunityService->win($opportunity, $request->validated());

        return new OpportunityResource($opportunity);
    }

    public function lose(LoseOpportunityRequest $request, Opportunity $opportunity): OpportunityResource
    {
        $this->authorize('lose', $opportunity);

        $opportunity = $this->opportunityService->lose(
            $opportunity,
            $request->validated('lost_reason'),
            $request->validated('actual_close_date')
        );

        return new OpportunityResource($opportunity);
    }

    public function pipeline(): JsonResponse
    {
        $this->authorize('viewAny', Opportunity::class);

        return response()->json(['data' => $this->opportunityService->pipeline()]);
    }

    public function forecast(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Opportunity::class);

        $request->validate(['months' => ['nullable', 'integer', 'between:1,24']]);

        return response()->json([
            'data' => $this->opportunityService->forecast((int) $request->input('months', 3)),
        ]);
    }
}
