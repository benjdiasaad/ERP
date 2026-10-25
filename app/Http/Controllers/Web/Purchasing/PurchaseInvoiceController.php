<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Purchasing;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Purchasing\Concerns\ProvidesPurchaseOptions;
use App\Http\Requests\Purchasing\StorePurchaseInvoiceRequest;
use App\Http\Requests\Purchasing\UpdatePurchaseInvoiceRequest;
use App\Http\Resources\Purchasing\PurchaseInvoiceResource;
use App\Models\Purchasing\PurchaseInvoice;
use App\Services\Purchasing\PurchaseInvoiceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PurchaseInvoiceController extends Controller
{
    use ProvidesPurchaseOptions;

    public const STATUSES = ['draft', 'sent', 'partial', 'paid', 'overdue', 'cancelled'];

    private const UNPAID = ['sent', 'partial', 'overdue'];

    public function __construct(
        private readonly PurchaseInvoiceService $invoiceService,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', PurchaseInvoice::class);

        $filters = $request->only(['search', 'status', 'supplier_id']);

        $invoices = PurchaseInvoice::query()
            ->with('supplier:id,name,code')
            ->when($filters['search'] ?? null, function ($query, $search) {
                $like = '%' . $search . '%';
                $query->where(fn ($query) => $query
                    ->whereLike('reference', $like)
                    ->orWhereHas('supplier', fn ($query) => $query->whereLike('name', $like)));
            })
            ->when(($filters['status'] ?? null) === 'unpaid', fn ($query) => $query->whereIn('status', self::UNPAID))
            ->when(in_array($filters['status'] ?? null, self::STATUSES, true), fn ($query) => $query->where('status', $filters['status']))
            ->when($filters['supplier_id'] ?? null, fn ($query, $id) => $query->where('supplier_id', $id))
            ->latest('invoice_date')
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        $counts = PurchaseInvoice::query()->selectRaw('status, COUNT(*) as aggregate, COALESCE(SUM(total_ttc), 0) as amount, COALESCE(SUM(amount_due), 0) as due')
            ->groupBy('status')->get()->keyBy('status');

        return Inertia::render('Purchasing/Invoices/Index', [
            'invoices' => PurchaseInvoiceResource::collection($invoices),
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
        $this->authorize('create', PurchaseInvoice::class);

        return Inertia::render('Purchasing/Invoices/Create', [
            ...$this->purchaseOptions(),
            'defaults' => [
                'supplier_id'  => $request->integer('supplier_id') ?: null,
                'invoice_date' => now()->toDateString(),
                'currency_id'  => $this->defaultCurrencyId(),
            ],
        ]);
    }

    public function store(StorePurchaseInvoiceRequest $request): RedirectResponse
    {
        $this->authorize('create', PurchaseInvoice::class);

        $invoice = $this->invoiceService->create($request->validated());

        return redirect()->route('purchasing.invoices.show', $invoice)->with('success', "Facture fournisseur {$invoice->reference} enregistrée.");
    }

    public function show(PurchaseInvoice $purchaseInvoice): Response
    {
        $this->authorize('view', $purchaseInvoice);

        $purchaseInvoice->load(['supplier', 'paymentTerm', 'currency', 'lines.product', 'purchaseOrder:id,reference']);

        return Inertia::render('Purchasing/Invoices/Show', [
            'invoice' => new PurchaseInvoiceResource($purchaseInvoice),
        ]);
    }

    public function edit(PurchaseInvoice $purchaseInvoice): Response
    {
        $this->authorize('update', $purchaseInvoice);

        $purchaseInvoice->load('lines');

        return Inertia::render('Purchasing/Invoices/Edit', [
            'invoice' => new PurchaseInvoiceResource($purchaseInvoice),
            ...$this->purchaseOptions(),
        ]);
    }

    public function update(UpdatePurchaseInvoiceRequest $request, PurchaseInvoice $purchaseInvoice): RedirectResponse
    {
        $this->authorize('update', $purchaseInvoice);

        $this->invoiceService->update($purchaseInvoice, $request->validated());

        return redirect()->route('purchasing.invoices.show', $purchaseInvoice)->with('success', 'Facture fournisseur mise à jour.');
    }

    public function destroy(PurchaseInvoice $purchaseInvoice): RedirectResponse
    {
        $this->authorize('delete', $purchaseInvoice);

        $this->invoiceService->delete($purchaseInvoice);

        return redirect()->route('purchasing.invoices.index')->with('success', "Facture {$purchaseInvoice->reference} supprimée.");
    }

    /** "Validate" a received supplier invoice so it can be paid (draft → sent). */
    public function send(PurchaseInvoice $purchaseInvoice): RedirectResponse
    {
        $this->authorize('send', $purchaseInvoice);
        $this->invoiceService->send($purchaseInvoice);

        return back()->with('success', 'Facture validée, prête à être payée.');
    }

    public function cancel(Request $request, PurchaseInvoice $purchaseInvoice): RedirectResponse
    {
        $this->authorize('cancel', $purchaseInvoice);

        $data = $request->validate(['reason' => ['nullable', 'string', 'max:1000']]);
        $this->invoiceService->cancel($purchaseInvoice, $data['reason'] ?? null);

        return back()->with('success', 'Facture fournisseur annulée.');
    }

    public function recordPayment(Request $request, PurchaseInvoice $purchaseInvoice): RedirectResponse
    {
        $this->authorize('recordPayment', $purchaseInvoice);

        $data = $request->validate([
            'amount'         => ['required', 'numeric', 'min:0.01'],
            'payment_date'   => ['nullable', 'date'],
            'payment_method' => ['nullable', 'string', 'max:100'],
            'reference'      => ['nullable', 'string', 'max:255'],
        ]);

        $invoice = $this->invoiceService->recordPayment($purchaseInvoice, (float) $data['amount'], $data);

        return back()->with('success', $invoice->status === 'paid' ? 'Paiement enregistré : facture soldée.' : 'Paiement partiel enregistré.');
    }
}
