<?php

declare(strict_types=1);

namespace App\Http\Controllers\CRM;

use App\Http\Controllers\Controller;
use App\Http\Requests\CRM\StoreActivityRequest;
use App\Http\Requests\CRM\UpdateActivityRequest;
use App\Http\Resources\CRM\ActivityResource;
use App\Models\CRM\Activity;
use App\Services\CRM\ActivityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ActivityController extends Controller
{
    public function __construct(
        private readonly ActivityService $activityService,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Activity::class);

        $activities = Activity::query()
            ->with(['assignee'])
            ->when($request->input('search'), function ($query, $search) {
                $query->whereLike('subject', '%' . $search . '%');
            })
            ->when($request->input('type'), fn ($query, $type) => $query->where('type', $type))
            ->when($request->input('status'), fn ($query, $status) => $query->where('status', $status))
            ->when($request->input('priority'), fn ($query, $priority) => $query->where('priority', $priority))
            ->when($request->input('assigned_to'), fn ($query, $userId) => $query->where('assigned_to', $userId))
            ->when($request->input('related_type'), function ($query, $alias) use ($request) {
                $query->where('related_type', Activity::RELATED_TYPES[$alias] ?? $alias)
                    ->when($request->input('related_id'), fn ($query, $id) => $query->where('related_id', $id));
            })
            ->orderByDesc('due_date')
            ->paginate($request->input('per_page', 15));

        return ActivityResource::collection($activities);
    }

    public function store(StoreActivityRequest $request): JsonResponse
    {
        $this->authorize('create', Activity::class);

        $activity = $this->activityService->create($request->validated());

        return (new ActivityResource($activity->load('assignee')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Activity $activity): ActivityResource
    {
        $this->authorize('view', $activity);

        $activity->load(['assignee']);

        return new ActivityResource($activity);
    }

    public function update(UpdateActivityRequest $request, Activity $activity): ActivityResource
    {
        $this->authorize('update', $activity);

        $activity = $this->activityService->update($activity, $request->validated());

        return new ActivityResource($activity);
    }

    public function destroy(Activity $activity): JsonResponse
    {
        $this->authorize('delete', $activity);

        $this->activityService->delete($activity);

        return response()->json(null, 204);
    }

    public function complete(Request $request, Activity $activity): ActivityResource
    {
        $this->authorize('complete', $activity);

        $request->validate(['outcome' => ['nullable', 'string', 'max:5000']]);

        $activity = $this->activityService->complete($activity, $request->input('outcome'));

        return new ActivityResource($activity);
    }

    public function overdue(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Activity::class);

        $assignedTo = $request->boolean('mine') ? (int) $request->user()->id : null;

        return ActivityResource::collection($this->activityService->getOverdue($assignedTo));
    }

    public function upcoming(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Activity::class);

        $request->validate(['days' => ['nullable', 'integer', 'between:1,365']]);
        $assignedTo = $request->boolean('mine') ? (int) $request->user()->id : null;

        return ActivityResource::collection(
            $this->activityService->getUpcoming((int) $request->input('days', 7), $assignedTo)
        );
    }
}
