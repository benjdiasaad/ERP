<?php

declare(strict_types=1);

namespace App\Http\Controllers\Purchasing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Purchasing\StoreSupplierRequest;
use App\Http\Requests\Purchasing\UpdateSupplierRequest;
use App\Http\Resources\Purchasing\SupplierResource;
use App\Models\Purchasing\Supplier;
use App\Services\Purchasing\SupplierService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SupplierController extends Controller
{
    public function __construct(
        private readonly SupplierService $supplierService,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Supplier::class);

        $filters = $request->only(['search', 'is_active', 'paginate']);
        $suppliers = $this->supplierService->list($filters);

        return SupplierResource::collection($suppliers);
    }

    public function store(StoreSupplierRequest $request): JsonResponse
    {
        $this->authorize('create', Supplier::class);

        $supplier = $this->supplierService->create($request->validated());

        return (new SupplierResource($supplier))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Supplier $supplier): SupplierResource
    {
        $this->authorize('view', $supplier);

        $supplier->load(['paymentTerm']);

        return new SupplierResource($supplier);
    }

    public function update(UpdateSupplierRequest $request, Supplier $supplier): SupplierResource
    {
        $this->authorize('update', $supplier);

        $supplier = $this->supplierService->update($supplier, $request->validated());

        return new SupplierResource($supplier);
    }

    public function destroy(Supplier $supplier): JsonResponse
    {
        $this->authorize('delete', $supplier);

        $this->supplierService->delete($supplier);

        return response()->json(null, 204);
    }

    public function search(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Supplier::class);

        $term = $request->get('q', '');
        $suppliers = $this->supplierService->search($term);

        return SupplierResource::collection($suppliers);
    }
}
