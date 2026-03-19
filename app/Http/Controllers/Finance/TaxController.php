<?php

declare(strict_types=1);

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\StoreTaxRequest;
use App\Http\Requests\Finance\UpdateTaxRequest;
use App\Http\Resources\Finance\TaxResource;
use App\Models\Finance\Tax;
use App\Services\Finance\TaxService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

class TaxController extends Controller
{
    public function __construct(
        private readonly TaxService $taxService,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Tax::class);

        $filters = $request->only(['search', 'is_active', 'sort', 'per_page']);
        $taxes = $this->taxService->search($filters);

        return TaxResource::collection($taxes);
    }

    public function store(StoreTaxRequest $request): JsonResponse
    {
        $this->authorize('create', Tax::class);

        $tax = $this->taxService->create($request->validated());

        return (new TaxResource($tax))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Tax $tax): TaxResource
    {
        $this->authorize('view', $tax);

        return new TaxResource($tax);
    }

    public function update(UpdateTaxRequest $request, Tax $tax): TaxResource
    {
        $this->authorize('update', $tax);

        $tax = $this->taxService->update($tax, $request->validated());

        return new TaxResource($tax);
    }

    public function destroy(Tax $tax): JsonResponse
    {
        $this->authorize('delete', $tax);

        try {
            $this->taxService->delete($tax);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }

        return response()->json(null, 204);
    }
}
