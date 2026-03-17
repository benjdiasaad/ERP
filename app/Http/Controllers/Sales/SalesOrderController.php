<?php

declare(strict_types=1);

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\StoreSalesOrderRequest;
use App\Http\Requests\Sales\UpdateSalesOrderRequest;
use App\Http\Resources\Sales\SalesOrderResource;
use App\Models\Sales\SalesOrder;
use App\Services\Sales\SalesOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

class SalesOrderController extends Controller
{
    public function __construct(
        private readonly SalesOrderService $salesOrderService,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', SalesOrder::class);

        $orders = SalesOrder::with(['customer', 'currency', 'lines'])
            ->when($request->search, fn ($q) => $q->where('reference', 'like', "%{$request->search}%"))
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->when($request->customer_id, fn ($q) => $q->where('customer_id', $request->customer_id))
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return SalesOrderResource::collection($orders);
    }

    public function store(StoreSalesOrderRequest $request): JsonResponse
    {
        $this->authorize('create', SalesOrder::class);

        $order = $this->salesOrderService->create($request->validated());

        return (new SalesOrderResource($order))
            ->response()
            ->setStatusCode(201);
    }

    public function show(SalesOrder $salesOrder): SalesOrderResource
    {
        $this->authorize('view', $salesOrder);

        $salesOrder->load(['customer', 'currency', 'paymentTerm', 'lines', 'createdBy', 'confirmedBy']);

        return new SalesOrderResource($salesOrder);
    }

    public function update(UpdateSalesOrderRequest $request, SalesOrder $salesOrder): SalesOrderResource
    {
        $this->authorize('update', $salesOrder);

        $order = $this->salesOrderService->update($salesOrder, $request->validated());

        return new SalesOrderResource($order);
    }

    public function destroy(SalesOrder $salesOrder): JsonResponse
    {
        $this->authorize('delete', $salesOrder);

        try {
            $this->salesOrderService->delete($salesOrder);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }

        return response()->json(null, 204);
    }

    public function confirm(SalesOrder $salesOrder): SalesOrderResource
    {
        $this->authorize('confirm', $salesOrder);

        try {
            $order = $this->salesOrderService->confirm($salesOrder);
        } catch (ValidationException $e) {
            abort(422, $e->getMessage());
        }

        return new SalesOrderResource($order);
    }

    public function cancel(Request $request, SalesOrder $salesOrder): SalesOrderResource
    {
        $this->authorize('cancel', $salesOrder);

        $request->validate([
            'cancellation_reason' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $order = $this->salesOrderService->cancel($salesOrder, $request->cancellation_reason);
        } catch (ValidationException $e) {
            abort(422, $e->getMessage());
        }

        return new SalesOrderResource($order);
    }

    public function generateInvoice(SalesOrder $salesOrder): JsonResponse
    {
        $this->authorize('generateInvoice', $salesOrder);

        try {
            $invoice = $this->salesOrderService->generateInvoice($salesOrder);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }

        return response()->json([
            'message' => 'Invoice generated successfully.',
            'invoice' => $invoice,
        ], 201);
    }

    public function generateDeliveryNote(SalesOrder $salesOrder): JsonResponse
    {
        $this->authorize('generateDeliveryNote', $salesOrder);

        try {
            $deliveryNote = $this->salesOrderService->generateDeliveryNote($salesOrder);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }

        return response()->json([
            'message'       => 'Delivery note generated successfully.',
            'delivery_note' => $deliveryNote,
        ], 201);
    }

    public function pdf(SalesOrder $salesOrder): JsonResponse
    {
        $this->authorize('view', $salesOrder);

        return response()->json([
            'message' => 'PDF generation not yet implemented.',
        ], 501);
    }
}
