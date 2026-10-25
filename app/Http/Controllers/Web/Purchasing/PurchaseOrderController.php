<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Purchasing;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Purchasing\Concerns\ProvidesPurchaseOptions;
use App\Http\Requests\Purchasing\StorePurchaseOrderRequest;
use App\Http\Requests\Purchasing\UpdatePurchaseOrderRequest;
use App\Http\Resources\Purchasing\PurchaseOrderResource;
use App\Models\Purchasing\PurchaseOrder;
use App\Services\Purchasing\PurchaseOrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PurchaseOrderController extends Controller
{
    use ProvidesPurchaseOptions;

    public const STATUSES = ['draft', 'sent', 'confirmed', 'in_progress', 'received', 'invoiced', 'cancelled'];

    public function __construct(
        private readonly PurchaseOrderService $orderService,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', PurchaseOrder::class);

        $filters = $request->only(['search', 'status', 'supplier_id']);

        $orders = PurchaseOrder::query()
            ->with('supplier:id,name,code')
            ->when($filters['search'] ?? null, function ($query, $search) {
                $like = '%' . $search . '%';
                $query->where(fn ($query) => $query
                    ->whereLike('reference', $like)
                    ->orWhereHas('supplier', fn ($query) => $query->whereLike('name', $like)));
            })
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['supplier_id'] ?? null, fn ($query, $id) => $query->where('supplier_id', $id))
            ->latest('order_date')
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        $counts = PurchaseOrder::query()->selectRaw('status, COUNT(*) as aggregate, COALESCE(SUM(total_ttc), 0) as amount')
            ->groupBy('status')->get()->keyBy('status');

        return Inertia::render('Purchasing/Orders/Index', [
            'orders'   => PurchaseOrderResource::collection($orders),
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
        $this->authorize('create', PurchaseOrder::class);

        return Inertia::render('Purchasing/Orders/Create', [
            ...$this->purchaseOptions(),
            'defaults' => [
                'supplier_id' => $request->integer('supplier_id') ?: null,
                'order_date'  => now()->toDateString(),
                'currency_id' => $this->defaultCurrencyId(),
            ],
        ]);
    }

    public function store(StorePurchaseOrderRequest $request): RedirectResponse
    {
        $this->authorize('create', PurchaseOrder::class);

        $order = $this->orderService->create($request->validated());

        return redirect()->route('purchasing.orders.show', $order)->with('success', "Bon de commande {$order->reference} créé.");
    }

    public function show(PurchaseOrder $purchaseOrder): Response
    {
        $this->authorize('view', $purchaseOrder);

        $purchaseOrder->load(['supplier', 'lines.product', 'purchaseRequest:id,reference', 'paymentTerm']);

        return Inertia::render('Purchasing/Orders/Show', [
            'order'      => new PurchaseOrderResource($purchaseOrder),
            'paymentTerm' => $purchaseOrder->paymentTerm?->name,
            'receptions' => $purchaseOrder->receptionNotes()->latest()->get(['id', 'reference', 'reception_date', 'status']),
            'invoices'   => $purchaseOrder->purchaseInvoices()->latest()->get(['id', 'reference', 'invoice_date', 'status', 'total_ttc', 'amount_due']),
        ]);
    }

    public function edit(PurchaseOrder $purchaseOrder): Response
    {
        $this->authorize('update', $purchaseOrder);

        $purchaseOrder->load('lines');

        return Inertia::render('Purchasing/Orders/Edit', [
            'order' => new PurchaseOrderResource($purchaseOrder),
            ...$this->purchaseOptions(),
        ]);
    }

    public function update(UpdatePurchaseOrderRequest $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->authorize('update', $purchaseOrder);

        $this->orderService->update($purchaseOrder, $request->validated());

        return redirect()->route('purchasing.orders.show', $purchaseOrder)->with('success', 'Bon de commande mis à jour.');
    }

    public function destroy(PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->authorize('delete', $purchaseOrder);

        $this->orderService->delete($purchaseOrder);

        return redirect()->route('purchasing.orders.index')->with('success', "Bon de commande {$purchaseOrder->reference} supprimé.");
    }

    public function send(PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->authorize('send', $purchaseOrder);
        $this->orderService->send($purchaseOrder);

        return back()->with('success', 'Bon de commande marqué comme envoyé au fournisseur.');
    }

    public function confirm(PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->authorize('confirm', $purchaseOrder);
        $this->orderService->confirm($purchaseOrder);

        return back()->with('success', 'Commande confirmée par le fournisseur.');
    }

    public function cancel(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->authorize('cancel', $purchaseOrder);

        $data = $request->validate(['reason' => ['nullable', 'string', 'max:1000']]);
        $this->orderService->cancel($purchaseOrder, $data['reason'] ?? null);

        return back()->with('success', 'Bon de commande annulé.');
    }

    public function generateReception(PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->authorize('generateReception', $purchaseOrder);

        $note = $this->orderService->generateReceptionNote($purchaseOrder);

        return redirect()->route('purchasing.receptions.show', $note)
            ->with('success', "Bon de réception {$note->reference} créé : vérifiez les quantités reçues puis confirmez.");
    }

    public function generateInvoice(PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->authorize('generateInvoice', $purchaseOrder);

        $invoice = $this->orderService->generatePurchaseInvoice($purchaseOrder);

        return redirect()->route('purchasing.invoices.show', $invoice)->with('success', "Facture fournisseur {$invoice->reference} créée en brouillon.");
    }
}
