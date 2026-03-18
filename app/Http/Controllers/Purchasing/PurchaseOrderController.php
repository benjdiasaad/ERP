<?php

declare(strict_types=1);

namespace App\Http\Controllers\Purchasing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Purchasing\StorePurchaseOrderRequest;
use App\Http\Requests\Purchasing\UpdatePurchaseOrderRequest;
use App\Http\Resources\Purchasing\PurchaseOrderResource;
use App\Http\Resources\Purchasing\ReceptionNoteResource;
use App\Models\Purchasing\PurchaseOrder;
use App\Services\Purchasing\PurchaseOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

class PurchaseOrderController extends Controller
{
    public function __construct(
        private readonly PurchaseOrderService $purchaseOrderService,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', PurchaseOrder::class);

        $orders = PurchaseOrder::with(['lines', 'supplier'])
            ->when($request->search, fn ($q) => $q->where(function ($q) use ($request) {
                $q->where('reference', 'like', "%{$request->search}%");
            }))
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->when($request->supplier_id, fn ($q) => $q->where('supplier_id', $request->supplier_id))
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return PurchaseOrderResource::collection($orders);
    }

    public function store(StorePurchaseOrderRequest $request): JsonResponse
    {
        $this->authorize('create', PurchaseOrder::class);

        $order = $this->purchaseOrderService->create($request->validated());

        return (new PurchaseOrderResource($order))
            ->response()
            ->setStatusCode(201);
    }

    public function show(PurchaseOrder $purchaseOrder): PurchaseOrderResource
    {
        $this->authorize('view', $purchaseOrder);

        $purchaseOrder->load(['lines', 'supplier', 'purchaseRequest']);

        return new PurchaseOrderResource($purchaseOrder);
    }

    public function update(UpdatePurchaseOrderRequest $request, PurchaseOrder $purchaseOrder): JsonResponse|PurchaseOrderResource
    {
        $this->authorize('update', $purchaseOrder);

        try {
            $purchaseOrder = $this->purchaseOrderService->update($purchaseOrder, $request->validated());
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }

        return new PurchaseOrderResource($purchaseOrder);
    }

    public function destroy(PurchaseOrder $purchaseOrder): JsonResponse
    {
        $this->authorize('delete', $purchaseOrder);

        try {
            $this->purchaseOrderService->delete($purchaseOrder);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }

        return response()->json(null, 204);
    }

    public function send(PurchaseOrder $purchaseOrder): JsonResponse|PurchaseOrderResource
    {
        $this->authorize('send', $purchaseOrder);

        try {
            $purchaseOrder = $this->purchaseOrderService->send($purchaseOrder);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }

        return new PurchaseOrderResource($purchaseOrder);
    }

    public function confirm(PurchaseOrder $purchaseOrder): JsonResponse|PurchaseOrderResource
    {
        $this->authorize('confirm', $purchaseOrder);

        try {
            $purchaseOrder = $this->purchaseOrderService->confirm($purchaseOrder);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }

        return new PurchaseOrderResource($purchaseOrder);
    }

    public function cancel(Request $request, PurchaseOrder $purchaseOrder): JsonResponse|PurchaseOrderResource
    {
        $this->authorize('cancel', $purchaseOrder);

        $request->validate([
            'cancellation_reason' => ['nullable', 'string'],
        ]);

        try {
            $purchaseOrder = $this->purchaseOrderService->cancel($purchaseOrder, $request->cancellation_reason);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }

        return new PurchaseOrderResource($purchaseOrder);
    }

    public function generateReception(PurchaseOrder $purchaseOrder): JsonResponse|ReceptionNoteResource
    {
        $this->authorize('generateReception', $purchaseOrder);

        try {
            $note = $this->purchaseOrderService->generateReceptionNote($purchaseOrder);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }

        return (new ReceptionNoteResource($note))->response()->setStatusCode(201);
    }

    public function generateInvoice(PurchaseOrder $purchaseOrder): JsonResponse
    {
        $this->authorize('generateInvoice', $purchaseOrder);

        try {
            $result = $this->purchaseOrderService->generatePurchaseInvoice($purchaseOrder);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }

        return response()->json($result, 201);
    }
}
