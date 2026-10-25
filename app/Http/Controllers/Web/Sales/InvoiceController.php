<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Sales;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Sales\Concerns\ProvidesDocumentOptions;
use App\Http\Requests\Sales\StoreInvoiceRequest;
use App\Http\Requests\Sales\UpdateInvoiceRequest;
use App\Http\Resources\Sales\InvoiceResource;
use App\Models\Sales\Invoice;
use App\Services\Sales\InvoiceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InvoiceController extends Controller
{
    use ProvidesDocumentOptions;

    public const STATUSES = ['draft', 'sent', 'partial', 'paid', 'overdue', 'cancelled'];

    /** Statuses still waiting for money. */
    private const UNPAID = ['sent', 'partial', 'overdue'];

    public function __construct(
        private readonly InvoiceService $invoiceService,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Invoice::class);

        // Keep statuses current before listing (sent/partial invoices past their due date).
        $this->invoiceService->markOverdueInvoices();

        $filters = $request->only(['search', 'status', 'customer_id']);

        $invoices = Invoice::query()
            ->with('customer:id,name,code')
            ->when($filters['search'] ?? null, function ($query, $search) {
                $like = '%' . $search . '%';
                $query->where(fn ($query) => $query
                    ->whereLike('reference', $like)
                    ->orWhereHas('customer', fn ($query) => $query->whereLike('name', $like)));
            })
            ->when(($filters['status'] ?? null) === 'unpaid', fn ($query) => $query->whereIn('status', self::UNPAID))
            ->when(in_array($filters['status'] ?? null, self::STATUSES, true), fn ($query) => $query->where('status', $filters['status']))
            ->when($filters['customer_id'] ?? null, fn ($query, $id) => $query->where('customer_id', $id))
            ->latest('invoice_date')
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        $counts = Invoice::query()->selectRaw('status, COUNT(*) as aggregate, COALESCE(SUM(total_ttc), 0) as amount, COALESCE(SUM(amount_due), 0) as due')
            ->groupBy('status')->get()->keyBy('status');

        return Inertia::render('Sales/Invoices/Index', [
            'invoices' => InvoiceResource::collection($invoices),
            'filters'  => $filters,
            'statuses' => self::STATUSES,
            'counts'   => collect(self::STATUSES)->mapWithKeys(fn ($status) => [$status => [
                'count'  => (int) ($counts[$status]->aggregate ?? 0),
                'amount' => round((float) ($counts[$status]->amount ?? 0), 2),
                'due'    => round((float) ($counts[$status]->due ?? 0), 2),
            ]]),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', Invoice::class);

        return Inertia::render('Sales/Invoices/Create', [
            ...$this->documentOptions(),
            'defaults' => [
                'customer_id'  => $request->integer('customer_id') ?: null,
                'invoice_date' => now()->toDateString(),
                'currency_id'  => $this->defaultCurrencyId(),
            ],
        ]);
    }

    public function store(StoreInvoiceRequest $request): RedirectResponse
    {
        $this->authorize('create', Invoice::class);

        $invoice = $this->invoiceService->create($request->validated());

        return redirect()->route('sales.invoices.show', $invoice)->with('success', "Facture {$invoice->reference} créée.");
    }

    public function show(Invoice $invoice): Response
    {
        $this->authorize('view', $invoice);

        $this->invoiceService->checkOverdue($invoice);
        $invoice->load(['customer', 'paymentTerm', 'currency', 'lines.product', 'salesOrder:id,reference']);

        return Inertia::render('Sales/Invoices/Show', [
            'invoice'     => new InvoiceResource($invoice),
            'creditNotes' => $invoice->creditNotes()->latest()->get(['id', 'reference', 'status', 'total_ttc']),
        ]);
    }

    public function edit(Invoice $invoice): Response
    {
        $this->authorize('update', $invoice);

        $invoice->load('lines');

        return Inertia::render('Sales/Invoices/Edit', [
            'invoice' => new InvoiceResource($invoice),
            ...$this->documentOptions(),
        ]);
    }

    public function update(UpdateInvoiceRequest $request, Invoice $invoice): RedirectResponse
    {
        $this->authorize('update', $invoice);

        $this->invoiceService->update($invoice, $request->validated());

        return redirect()->route('sales.invoices.show', $invoice)->with('success', 'Facture mise à jour.');
    }

    public function destroy(Invoice $invoice): RedirectResponse
    {
        $this->authorize('delete', $invoice);

        $this->invoiceService->delete($invoice);

        return redirect()->route('sales.invoices.index')->with('success', "Facture {$invoice->reference} supprimée.");
    }

    public function send(Invoice $invoice): RedirectResponse
    {
        $this->authorize('send', $invoice);
        $this->invoiceService->send($invoice);

        return back()->with('success', 'Facture marquée comme envoyée.');
    }

    public function cancel(Request $request, Invoice $invoice): RedirectResponse
    {
        $this->authorize('cancel', $invoice);

        $data = $request->validate(['reason' => ['nullable', 'string', 'max:1000']]);
        $this->invoiceService->cancel($invoice, $data['reason'] ?? null);

        return back()->with('success', 'Facture annulée.');
    }

    public function recordPayment(Request $request, Invoice $invoice): RedirectResponse
    {
        $this->authorize('recordPayment', $invoice);

        $data = $request->validate([
            'amount'         => ['required', 'numeric', 'min:0.01'],
            'payment_date'   => ['nullable', 'date'],
            'payment_method' => ['nullable', 'string', 'max:100'],
            'reference'      => ['nullable', 'string', 'max:255'],
        ]);

        $invoice = $this->invoiceService->recordPayment($invoice, (float) $data['amount'], $data);

        return back()->with('success', $invoice->status === 'paid' ? 'Paiement enregistré : facture soldée.' : 'Paiement partiel enregistré.');
    }

    public function createCreditNote(Request $request, Invoice $invoice): RedirectResponse
    {
        $this->authorize('createCreditNote', $invoice);

        $data = $request->validate([
            'reason'    => ['nullable', 'string', 'max:1000'],
            'note_date' => ['nullable', 'date'],
        ]);

        $note = $this->invoiceService->createCreditNote($invoice, $data);

        return back()->with('success', "Avoir {$note->reference} créé en brouillon pour la totalité de la facture.");
    }
}
