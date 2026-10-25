<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Sales;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Sales\Concerns\ProvidesDocumentOptions;
use App\Http\Requests\Sales\StoreCreditNoteRequest;
use App\Http\Requests\Sales\UpdateCreditNoteRequest;
use App\Http\Resources\Sales\CreditNoteResource;
use App\Models\Sales\CreditNote;
use App\Models\Sales\Invoice;
use App\Services\Sales\CreditNoteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CreditNoteController extends Controller
{
    use ProvidesDocumentOptions;

    public const STATUSES = ['draft', 'confirmed', 'applied'];

    /** Invoices a credit note can be applied to. */
    private const APPLICABLE_INVOICE_STATUSES = ['sent', 'partial', 'overdue', 'paid'];

    public function __construct(
        private readonly CreditNoteService $creditNoteService,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', CreditNote::class);

        $filters = $request->only(['search', 'status']);

        $notes = CreditNote::query()
            ->with(['customer:id,name,code', 'invoice:id,reference'])
            ->when($filters['search'] ?? null, function ($query, $search) {
                $like = '%' . $search . '%';
                $query->where(fn ($query) => $query
                    ->whereLike('reference', $like)
                    ->orWhereHas('customer', fn ($query) => $query->whereLike('name', $like)));
            })
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->latest('date')
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        $counts = CreditNote::query()->selectRaw('status, COUNT(*) as aggregate, COALESCE(SUM(total_ttc), 0) as amount')
            ->groupBy('status')->get()->keyBy('status');

        return Inertia::render('Sales/CreditNotes/Index', [
            'notes'    => CreditNoteResource::collection($notes),
            'filters'  => $filters,
            'statuses' => self::STATUSES,
            'counts'   => collect(self::STATUSES)->mapWithKeys(fn ($status) => [$status => [
                'count'  => (int) ($counts[$status]->aggregate ?? 0),
                'amount' => round((float) ($counts[$status]->amount ?? 0), 2),
            ]]),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', CreditNote::class);

        return Inertia::render('Sales/CreditNotes/Create', [
            ...$this->documentOptions(),
            'invoices' => $this->invoiceOptions(),
            'defaults' => [
                'customer_id' => $request->integer('customer_id') ?: null,
                'date'        => now()->toDateString(),
            ],
        ]);
    }

    public function store(StoreCreditNoteRequest $request): RedirectResponse
    {
        $this->authorize('create', CreditNote::class);

        $note = $this->creditNoteService->create($request->validated());

        return redirect()->route('sales.credit-notes.show', $note)->with('success', "Avoir {$note->reference} créé.");
    }

    public function show(CreditNote $creditNote): Response
    {
        $this->authorize('view', $creditNote);

        $creditNote->load(['customer', 'invoice:id,reference,status,amount_due,total_ttc', 'lines.product']);

        return Inertia::render('Sales/CreditNotes/Show', [
            'note'     => new CreditNoteResource($creditNote),
            'invoices' => $creditNote->status === 'confirmed' ? $this->invoiceOptions($creditNote->customer_id) : [],
        ]);
    }

    public function edit(CreditNote $creditNote): Response
    {
        $this->authorize('update', $creditNote);

        $creditNote->load('lines');

        return Inertia::render('Sales/CreditNotes/Edit', [
            'note'     => new CreditNoteResource($creditNote),
            ...$this->documentOptions(),
            'invoices' => $this->invoiceOptions(),
        ]);
    }

    public function update(UpdateCreditNoteRequest $request, CreditNote $creditNote): RedirectResponse
    {
        $this->authorize('update', $creditNote);

        $this->creditNoteService->update($creditNote, $request->validated());

        return redirect()->route('sales.credit-notes.show', $creditNote)->with('success', 'Avoir mis à jour.');
    }

    public function destroy(CreditNote $creditNote): RedirectResponse
    {
        $this->authorize('delete', $creditNote);

        $this->creditNoteService->delete($creditNote);

        return redirect()->route('sales.credit-notes.index')->with('success', "Avoir {$creditNote->reference} supprimé.");
    }

    public function confirm(CreditNote $creditNote): RedirectResponse
    {
        $this->authorize('confirm', $creditNote);
        $this->creditNoteService->confirm($creditNote);

        return back()->with('success', 'Avoir confirmé.');
    }

    public function apply(Request $request, CreditNote $creditNote): RedirectResponse
    {
        $this->authorize('apply', $creditNote);

        $data = $request->validate([
            'invoice_id' => [
                'required', 'integer',
                Rule::exists('invoices', 'id')
                    ->where('company_id', $request->user()->current_company_id)
                    ->where('customer_id', $creditNote->customer_id)
                    ->whereNull('deleted_at'),
            ],
        ]);

        $this->creditNoteService->applyToInvoice($creditNote, Invoice::findOrFail($data['invoice_id']));

        return back()->with('success', 'Avoir imputé sur la facture.');
    }

    /**
     * Invoices an avoir can be linked or applied to, optionally for one customer.
     */
    private function invoiceOptions(?int $customerId = null): array
    {
        return Invoice::query()
            ->whereIn('status', self::APPLICABLE_INVOICE_STATUSES)
            ->when($customerId, fn ($query) => $query->where('customer_id', $customerId))
            ->latest('invoice_date')
            ->get(['id', 'reference', 'customer_id', 'amount_due', 'status'])
            ->map(fn (Invoice $i) => [
                'value'       => $i->id,
                'label'       => "{$i->reference} — reste dû " . number_format((float) $i->amount_due, 2, ',', ' ') . ' MAD',
                'customer_id' => $i->customer_id,
            ])
            ->all();
    }
}
