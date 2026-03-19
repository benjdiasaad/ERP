<?php

declare(strict_types=1);

namespace App\Http\Controllers\Purchasing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Purchasing\StorePurchaseInvoiceRequest;
use App\Http\Requests\Purchasing\UpdatePurchaseInvoiceRequest;
use App\Http\Resources\Purchasing\PurchaseInvoiceResource;
use App\Models\Purchasing\PurchaseInvoice;
use App\Services\Purchasing\PurchaseInvoiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

class PurchaseInvoiceController extends Controller
{
    public function __construct(
        private readonly PurchaseInvoiceService $invoiceService,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', PurchaseInvoice::class);

        $invoices = PurchaseInvoice::with(['supplier', 'currency', 'lines'])
            ->when($request->search, fn ($q) => $q->where('reference', 'like', "%{$request->search}%"))
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->when($request->supplier_id, fn ($q) => $q->where('supplier_id', $request->supplier_id))
            ->when($request->purchase_order_id, fn ($q) => $q->where('purchase_order_id', $request->purchase_order_id))
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return PurchaseInvoiceResource::collection($invoices);
    }

    public function store(StorePurchaseInvoiceRequest $request): JsonResponse
    {
        $this->authorize('create', PurchaseInvoice::class);

        $invoice = $this->invoiceService->create($request->validated());

        return (new PurchaseInvoiceResource($invoice))
            ->response()
            ->setStatusCode(201);
    }

    public function show(PurchaseInvoice $purchaseInvoice): PurchaseInvoiceResource
    {
        $this->authorize('view', $purchaseInvoice);

        $purchaseInvoice->load(['supplier', 'currency', 'paymentTerm', 'lines', 'purchaseOrder', 'createdBy']);

        return new PurchaseInvoiceResource($purchaseInvoice);
    }

    public function update(UpdatePurchaseInvoiceRequest $request, PurchaseInvoice $purchaseInvoice): PurchaseInvoiceResource
    {
        $this->authorize('update', $purchaseInvoice);

        $invoice = $this->invoiceService->update($purchaseInvoice, $request->validated());

        return new PurchaseInvoiceResource($invoice);
    }

    public function destroy(PurchaseInvoice $purchaseInvoice): JsonResponse
    {
        $this->authorize('delete', $purchaseInvoice);

        try {
            $this->invoiceService->delete($purchaseInvoice);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }

        return response()->json(null, 204);
    }

    public function send(PurchaseInvoice $purchaseInvoice): PurchaseInvoiceResource
    {
        $this->authorize('send', $purchaseInvoice);

        try {
            $invoice = $this->invoiceService->send($purchaseInvoice);
        } catch (ValidationException $e) {
            abort(422, $e->getMessage());
        }

        return new PurchaseInvoiceResource($invoice);
    }

    public function cancel(Request $request, PurchaseInvoice $purchaseInvoice): PurchaseInvoiceResource
    {
        $this->authorize('cancel', $purchaseInvoice);

        $request->validate([
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $invoice = $this->invoiceService->cancel($purchaseInvoice, $request->reason);
        } catch (ValidationException $e) {
            abort(422, $e->getMessage());
        }

        return new PurchaseInvoiceResource($invoice);
    }

    public function recordPayment(Request $request, PurchaseInvoice $purchaseInvoice): PurchaseInvoiceResource
    {
        $this->authorize('recordPayment', $purchaseInvoice);

        $request->validate([
            'amount'         => ['required', 'numeric', 'min:0.01'],
            'payment_date'   => ['nullable', 'date'],
            'payment_method' => ['nullable', 'string', 'max:100'],
            'reference'      => ['nullable', 'string', 'max:255'],
            'notes'          => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $invoice = $this->invoiceService->recordPayment(
                $purchaseInvoice,
                (float) $request->amount,
                $request->only(['payment_date', 'payment_method', 'reference', 'notes'])
            );
        } catch (ValidationException $e) {
            abort(422, $e->getMessage());
        }

        return new PurchaseInvoiceResource($invoice);
    }

    public function markPaid(PurchaseInvoice $purchaseInvoice): PurchaseInvoiceResource
    {
        $this->authorize('recordPayment', $purchaseInvoice);

        try {
            $invoice = $this->invoiceService->markPaid($purchaseInvoice);
        } catch (ValidationException $e) {
            abort(422, $e->getMessage());
        }

        return new PurchaseInvoiceResource($invoice);
    }

    public function pdf(PurchaseInvoice $purchaseInvoice): JsonResponse
    {
        $this->authorize('view', $purchaseInvoice);

        return response()->json([
            'message' => 'PDF generation not yet implemented.',
        ], 501);
    }
}
