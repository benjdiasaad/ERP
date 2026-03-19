<?php

declare(strict_types=1);

namespace App\Http\Controllers\Event;

use App\Http\Controllers\Controller;
use App\Http\Requests\Event\StoreEventCategoryRequest;
use App\Http\Requests\Event\UpdateEventCategoryRequest;
use App\Http\Resources\Event\EventCategoryResource;
use App\Models\Event\EventCategory;
use App\Services\Event\EventCategoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class EventCategoryController extends Controller
{
    public function __construct(
        private readonly EventCategoryService $eventCategoryService,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', EventCategory::class);

        $categories = EventCategory::query()
            ->when($request->input('search'), function ($query, $search) {
                $query->where('name', 'ilike', "%{$search}%");
            })
            ->orderBy('name')
            ->paginate($request->input('per_page', 15));

        return EventCategoryResource::collection($categories);
    }

    public function store(StoreEventCategoryRequest $request): JsonResponse
    {
        $this->authorize('create', EventCategory::class);

        $category = $this->eventCategoryService->create($request->validated());

        return (new EventCategoryResource($category))
            ->response()
            ->setStatusCode(201);
    }

    public function show(EventCategory $eventCategory): EventCategoryResource
    {
        $this->authorize('view', $eventCategory);

        return new EventCategoryResource($eventCategory);
    }

    public function update(UpdateEventCategoryRequest $request, EventCategory $eventCategory): EventCategoryResource
    {
        $this->authorize('update', $eventCategory);

        $category = $this->eventCategoryService->update($eventCategory, $request->validated());

        return new EventCategoryResource($category);
    }

    public function destroy(EventCategory $eventCategory): JsonResponse
    {
        $this->authorize('delete', $eventCategory);

        $this->eventCategoryService->delete($eventCategory);

        return response()->json(null, 204);
    }
}
