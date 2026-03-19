<?php

declare(strict_types=1);

namespace App\Http\Controllers\Caution;

use App\Http\Controllers\Controller;
use App\Http\Requests\Caution\StoreCautionTypeRequest;
use App\Http\Requests\Caution\UpdateCautionTypeRequest;
use App\Http\Resources\Caution\CautionTypeResource;
use App\Models\Caution\CautionType;
use App\Services\Caution\CautionTypeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CautionTypeController extends Controller
{
    public function __construct(
        private readonly CautionTypeService $cautionTypeService,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', CautionType::class);

        $filters = $request->only(['search', 'paginate']);
        $cautionTypes = $this->cautionTypeService->list($filters);

        return CautionTypeResource::collection($cautionTypes);
    }

    public function store(StoreCautionTypeRequest $request): JsonResponse
    {
        $this->authorize('create', CautionType::class);

        $cautionType = $this->cautionTypeService->create($request->validated());

        return (new CautionTypeResource($cautionType))
            ->response()
            ->setStatusCode(201);
    }

    public function show(CautionType $cautionType): CautionTypeResource
    {
        $this->authorize('view', $cautionType);

        return new CautionTypeResource($cautionType);
    }

    public function update(UpdateCautionTypeRequest $request, CautionType $cautionType): CautionTypeResource
    {
        $this->authorize('update', $cautionType);

        $cautionType = $this->cautionTypeService->update($cautionType, $request->validated());

        return new CautionTypeResource($cautionType);
    }

    public function destroy(CautionType $cautionType): JsonResponse
    {
        $this->authorize('delete', $cautionType);

        $this->cautionTypeService->delete($cautionType);

        return response()->json(null, 204);
    }
    public function restore(CautionType $cautionType): CautionTypeResource
    {
        $this->authorize('restore', $cautionType);

        $cautionType = $this->cautionTypeService->restore($cautionType);

        return new CautionTypeResource($cautionType);
    }

}
