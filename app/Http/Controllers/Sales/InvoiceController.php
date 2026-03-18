<?php

declare(strict_types=1);

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\StoreInvoiceRequest;
use App\Http\Requests\Sales\UpdateInvoiceRequest;
use App\Http\Resources\Sales\InvoiceResource;
use App\Models\Sales\Invoice;
use App\Services\Sales\InvoiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

class InvoiceController extends Controller
{
    public function __construct(
        private readonly InvoiceService $invoiceService,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Invoice::class);

        $invoices = Invoice::with(['customer', 'currency', 'lines'])
            ->when($request->search, fn ($q) => $q->where('reference', 'like', "%{$request->search}%"))
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->when($request->customer_id, fn ($q) => $q->where('customer_id', $request->customer_id))
            ->when($request->sales_order_id, fn ($q) => $q->where('sales_order_id', $request->sales_order_id))
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return InvoiceResource::collection($invoices);
    }

    public function store(StoreInvoiceRequest $request): JsonResponse
    {
        $this->authorize('create', Invoice::class);

        $invoice = $this->invoiceService->create($request->validated());

        return (new InvoiceResource($invoice))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Invoice $invoice): InvoiceResource
    {
        $this->authorize('view', $invoice);

        $invoice->load(['customer', 'currency', 'paymentTerm', 'lines', 'salesOrder', 'createdBy']);

        return new InvoiceResource($invoice);
    }

    public function update(UpdateInvoiceRequest $request, Invoice $invoice): InvoiceResource
    {
        $this->authorize('update', $invoice);

        $invoice = $this->invoiceService->update($invoice, $request->validated());

        return new InvoiceResource($invoice);
    }

    public function destroy(Invoice $invoice): JsonResponse
    {
        $this->authorize('delete', $invoice);

        try {
            $this->invoiceService->delete($invoice);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }

        return response()->json(null, 204);
    }

    public function send(Invoice $invoice): InvoiceResource
    {
        $this->authorize('send', $invoice);

        try {
            $invoice = $this->invoiceService->send($invoice);
        } catch (ValidationException $e) {
            abort(422, $e->getMessage());
        }

        return new InvoiceResource($invoice);
    }

    public function cancel(Request $request, Invoice $invoice): InvoiceResource
    {
        $this->authorize('cancel', $invoice);

        $request->validate([
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $invoice = $this->invoiceService->cancel($invoice, $request->reason);
        } catch (ValidationException $e) {
            abort(422, $e->getMessage());
        }

        return new InvoiceResource($invoice);
    }

    public function recordPayment(Request $request, Invoice $invoice): InvoiceResource
    {
        $this->authorize('recordPayment', $invoice);

        $request->validate([
            'amount'         => ['required', 'numeric', 'min:0.01'],
            'payment_date'   => ['nullable', 'date'],
            'payment_method' => ['nullable', 'string', 'max:100'],
            'reference'      => ['nullable', 'string', 'max:255'],
            'notes'          => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $invoice = $this->invoiceService->recordPayment($invoice, (float) $request->amount, $request->only([
                'payment_date', 'payment_method', 'reference', 'notes',
            ]));
        } catch (ValidationException $e) {
            abort(422, $e->getMessage());
        }

        return new InvoiceResource($invoice);
    }

    public function createCreditNote(Request $request, Invoice $invoice): JsonResponse
    {
        $this->authorize('createCreditNote', $invoice);

        $request->validate([
            'reason'              => ['nullable', 'string', 'max:1000'],
            'note_date'           => ['nullable', 'date'],
            'lines'               => ['nullable', 'array'],
            'lines.*.product_id'  => ['nullable', 'integer', 'exists:products,id'],
            'lines.*.description' => ['required_with:lines', 'string', 'max:500'],
            'lines.*.quantity'    => ['required_with:lines', 'numeric', 'min:0.01'],
            'lines.*.unit_price_ht' => ['required_with:lines', 'numeric', 'min:0'],
            'lines.*.tax_rate'    => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        try {
            $creditNote = $this->invoiceService->createCreditNote($invoice, $request->all());
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }

        return response()->json(['data' => $creditNote], 201);
    }

    public function overdue(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Invoice::class);

        $invoices = Invoice::with(['customer'])
            ->overdue()
            ->latest()
            ->paginate(15);

        return InvoiceResource::collection($invoices);
    }

    public function pdf(Invoice $invoice): JsonResponse
    {
        $this->authorize('view', $invoice);

        return response()->json([
            'message' => 'PDF generation not yet implemented.',
        ], 501);
    }
}
