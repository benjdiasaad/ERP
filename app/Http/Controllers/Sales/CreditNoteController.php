<?php

declare(strict_types=1);

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\StoreCreditNoteRequest;
use App\Http\Requests\Sales\UpdateCreditNoteRequest;
use App\Http\Resources\Sales\CreditNoteResource;
use App\Models\Sales\CreditNote;
use App\Models\Sales\Invoice;
use App\Services\Sales\CreditNoteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

class CreditNoteController extends Controller
{
    public function __construct(
        private readonly CreditNoteService $creditNoteService,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', CreditNote::class);

        $creditNotes = CreditNote::with(['customer', 'invoice', 'lines'])
            ->when($request->search, fn ($q) => $q->where('reference', 'like', "%{$request->search}%"))
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->when($request->customer_id, fn ($q) => $q->where('customer_id', $request->customer_id))
            ->when($request->invoice_id, fn ($q) => $q->where('invoice_id', $request->invoice_id))
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return CreditNoteResource::collection($creditNotes);
    }

    public function store(StoreCreditNoteRequest $request): JsonResponse
    {
        $this->authorize('create', CreditNote::class);

        $creditNote = $this->creditNoteService->create($request->validated());

        return (new CreditNoteResource($creditNote))
            ->response()
            ->setStatusCode(201);
    }

    public function show(CreditNote $creditNote): CreditNoteResource
    {
        $this->authorize('view', $creditNote);

        $creditNote->load(['customer', 'invoice', 'lines', 'createdBy']);

        return new CreditNoteResource($creditNote);
    }

    public function update(UpdateCreditNoteRequest $request, CreditNote $creditNote): CreditNoteResource
    {
        $this->authorize('update', $creditNote);

        $creditNote = $this->creditNoteService->update($creditNote, $request->validated());

        return new CreditNoteResource($creditNote);
    }

    public function destroy(CreditNote $creditNote): JsonResponse
    {
        $this->authorize('delete', $creditNote);

        try {
            $this->creditNoteService->delete($creditNote);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }

        return response()->json(null, 204);
    }

    public function confirm(CreditNote $creditNote): CreditNoteResource
    {
        $this->authorize('confirm', $creditNote);

        try {
            $creditNote = $this->creditNoteService->confirm($creditNote);
        } catch (ValidationException $e) {
            abort(422, $e->getMessage());
        }

        return new CreditNoteResource($creditNote);
    }

    public function apply(Request $request, CreditNote $creditNote): CreditNoteResource
    {
        $this->authorize('apply', $creditNote);

        $request->validate([
            'invoice_id' => ['required', 'integer', 'exists:invoices,id'],
        ]);

        try {
            $invoice = Invoice::findOrFail($request->invoice_id);
            $creditNote = $this->creditNoteService->applyToInvoice($creditNote, $invoice);
        } catch (ValidationException $e) {
            abort(422, $e->getMessage());
        }

        return new CreditNoteResource($creditNote);
    }
}
