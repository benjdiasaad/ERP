<?php

declare(strict_types=1);

namespace App\Http\Controllers\Caution;

use App\Http\Controllers\Controller;
use App\Http\Requests\Caution\StoreCautionRequest;
use App\Http\Requests\Caution\UpdateCautionRequest;
use App\Http\Resources\Caution\CautionResource;
use App\Models\Caution\Caution;
use App\Services\Caution\CautionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CautionController extends Controller
{
    public function __construct(
        private readonly CautionService $cautionService,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Caution::class);

        $filters = $request->only(['search', 'direction', 'status', 'partner_type', 'paginate']);
        $cautions = $this->cautionService->list($filters);

        return CautionResource::collection($cautions);
    }

    public function store(StoreCautionRequest $request): JsonResponse
    {
        $this->authorize('create', Caution::class);

        $caution = $this->cautionService->create($request->validated());

        return (new CautionResource($caution))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Caution $caution): CautionResource
    {
        $this->authorize('view', $caution);

        return new CautionResource($caution);
    }

    public function update(UpdateCautionRequest $request, Caution $caution): CautionResource
    {
        $this->authorize('update', $caution);

        $caution = $this->cautionService->update($caution, $request->validated());

        return new CautionResource($caution);
    }

    public function destroy(Caution $caution): JsonResponse
    {
        $this->authorize('delete', $caution);

        $this->cautionService->delete($caution);

        return response()->json(null, 204);
    }
    public function activate(Caution $caution): CautionResource
    {
        $this->authorize('update', $caution);

        $caution = $this->cautionService->activate($caution);

        return new CautionResource($caution);
    }

    public function partialReturn(Request $request, Caution $caution): CautionResource
    {
        $this->authorize('update', $caution);

        $amount = (float) $request->input('amount');
        $notes = $request->input('notes');

        $caution = $this->cautionService->partialReturn($caution, $amount, $notes);

        return new CautionResource($caution);
    }

    public function fullReturn(Request $request, Caution $caution): CautionResource
    {
        $this->authorize('update', $caution);

        $notes = $request->input('notes');

        $caution = $this->cautionService->fullReturn($caution, $notes);

        return new CautionResource($caution);
    }

    public function extend(Request $request, Caution $caution): CautionResource
    {
        $this->authorize('update', $caution);

        $newExpiryDate = \Carbon\Carbon::parse($request->input('expiry_date'));
        $notes = $request->input('notes');

        $caution = $this->cautionService->extend($caution, $newExpiryDate, $notes);

        return new CautionResource($caution);
    }

    public function forfeit(Request $request, Caution $caution): CautionResource
    {
        $this->authorize('update', $caution);

        $notes = $request->input('notes');

        $caution = $this->cautionService->forfeit($caution, $notes);

        return new CautionResource($caution);
    }

    public function cancel(Request $request, Caution $caution): CautionResource
    {
        $this->authorize('update', $caution);

        $notes = $request->input('notes');

        $caution = $this->cautionService->cancel($caution, $notes);

        return new CautionResource($caution);
    }

    public function expiring(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Caution::class);

        $days = $request->query('days', 30);
        $cautions = $this->cautionService->getExpiring($days);

        return CautionResource::collection($cautions);
    }

    public function stats(): JsonResponse
    {
        $this->authorize('viewAny', Caution::class);

        $stats = $this->cautionService->getDashboardStats();

        return response()->json($stats);
    }

}
