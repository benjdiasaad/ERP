<?php

declare(strict_types=1);

namespace App\Http\Controllers\Purchasing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Purchasing\StorePurchaseRequestRequest;
use App\Http\Requests\Purchasing\UpdatePurchaseRequestRequest;
use App\Http\Resources\Purchasing\PurchaseRequestResource;
use App\Models\Purchasing\PurchaseRequest;
use App\Services\Purchasing\PurchaseRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

class PurchaseRequestController extends Controller
{
    public function __construct(
        private readonly PurchaseRequestService $purchaseRequestService,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', PurchaseRequest::class);

        $purchaseRequests = PurchaseRequest::with(['lines', 'supplier'])
            ->when($request->search, fn ($q) => $q->where(function ($q) use ($request) {
                $q->where('title', 'like', "%{$request->search}%")
                  ->orWhere('reference', 'like', "%{$request->search}%");
            }))
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->when($request->priority, fn ($q) => $q->where('priority', $request->priority))
            ->when($request->supplier_id, fn ($q) => $q->where('supplier_id', $request->supplier_id))
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return PurchaseRequestResource::collection($purchaseRequests);
    }

    public function store(StorePurchaseRequestRequest $request): JsonResponse
    {
        $this->authorize('create', PurchaseRequest::class);

        $purchaseRequest = $this->purchaseRequestService->create($request->validated());

        return (new PurchaseRequestResource($purchaseRequest))
            ->response()
            ->setStatusCode(201);
    }

    public function show(PurchaseRequest $purchaseRequest): PurchaseRequestResource
    {
        $this->authorize('view', $purchaseRequest);

        $purchaseRequest->load(['lines', 'supplier']);

        return new PurchaseRequestResource($purchaseRequest);
    }

    public function update(UpdatePurchaseRequestRequest $request, PurchaseRequest $purchaseRequest): JsonResponse|PurchaseRequestResource
    {
        $this->authorize('update', $purchaseRequest);

        try {
            $purchaseRequest = $this->purchaseRequestService->update($purchaseRequest, $request->validated());
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }

        return new PurchaseRequestResource($purchaseRequest);
    }

    public function destroy(PurchaseRequest $purchaseRequest): JsonResponse
    {
        $this->authorize('delete', $purchaseRequest);

        try {
            $this->purchaseRequestService->delete($purchaseRequest);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }

        return response()->json(null, 204);
    }

    public function submit(PurchaseRequest $purchaseRequest): JsonResponse|PurchaseRequestResource
    {
        $this->authorize('submit', $purchaseRequest);

        try {
            $purchaseRequest = $this->purchaseRequestService->submit($purchaseRequest);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }

        return new PurchaseRequestResource($purchaseRequest);
    }

    public function approve(PurchaseRequest $purchaseRequest): JsonResponse|PurchaseRequestResource
    {
        $this->authorize('approve', $purchaseRequest);

        try {
            $purchaseRequest = $this->purchaseRequestService->approve($purchaseRequest);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }

        return new PurchaseRequestResource($purchaseRequest);
    }

    public function reject(Request $request, PurchaseRequest $purchaseRequest): JsonResponse|PurchaseRequestResource
    {
        $this->authorize('reject', $purchaseRequest);

        $request->validate([
            'rejection_reason' => ['nullable', 'string'],
        ]);

        try {
            $purchaseRequest = $this->purchaseRequestService->reject($purchaseRequest, $request->rejection_reason);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }

        return new PurchaseRequestResource($purchaseRequest);
    }

    public function cancel(PurchaseRequest $purchaseRequest): JsonResponse|PurchaseRequestResource
    {
        $this->authorize('cancel', $purchaseRequest);

        try {
            $purchaseRequest = $this->purchaseRequestService->cancel($purchaseRequest);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }

        return new PurchaseRequestResource($purchaseRequest);
    }

    public function convertToOrder(PurchaseRequest $purchaseRequest): JsonResponse
    {
        $this->authorize('convert', $purchaseRequest);

        try {
            $order = $this->purchaseRequestService->convertToOrder($purchaseRequest);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }

        return response()->json([
            'message' => 'Purchase request successfully converted to purchase order.',
            'order'   => $order,
        ], 201);
    }
}
