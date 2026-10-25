<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Sales;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Sales\Concerns\ProvidesDocumentOptions;
use App\Http\Requests\Sales\StoreSalesOrderRequest;
use App\Http\Requests\Sales\UpdateSalesOrderRequest;
use App\Http\Resources\Sales\SalesOrderResource;
use App\Models\Sales\SalesOrder;
use App\Services\Sales\SalesOrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SalesOrderController extends Controller
{
    use ProvidesDocumentOptions;

    public const STATUSES = ['draft', 'confirmed', 'in_progress', 'delivered', 'invoiced', 'cancelled'];

    public function __construct(
        private readonly SalesOrderService $orderService,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', SalesOrder::class);

        $filters = $request->only(['search', 'status', 'customer_id']);

        $orders = SalesOrder::query()
            ->with('customer:id,name,code')
            ->when($filters['search'] ?? null, function ($query, $search) {
                $like = '%' . $search . '%';
                $query->where(fn ($query) => $query
                    ->whereLike('reference', $like)
                    ->orWhereHas('customer', fn ($query) => $query->whereLike('name', $like)));
            })
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['customer_id'] ?? null, fn ($query, $id) => $query->where('customer_id', $id))
            ->latest('order_date')
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        $counts = SalesOrder::query()->selectRaw('status, COUNT(*) as aggregate, COALESCE(SUM(total_ttc), 0) as amount')
            ->groupBy('status')->get()->keyBy('status');

        return Inertia::render('Sales/Orders/Index', [
            'orders'   => SalesOrderResource::collection($orders),
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
        $this->authorize('create', SalesOrder::class);

        return Inertia::render('Sales/Orders/Create', [
            ...$this->documentOptions(),
            'defaults' => [
                'customer_id' => $request->integer('customer_id') ?: null,
                'order_date'  => now()->toDateString(),
                'currency_id' => $this->defaultCurrencyId(),
            ],
        ]);
    }

    public function store(StoreSalesOrderRequest $request): RedirectResponse
    {
        $this->authorize('create', SalesOrder::class);

        $order = $this->orderService->create($request->validated());

        return redirect()->route('sales.orders.show', $order)->with('success', "Commande {$order->reference} créée.");
    }

    public function show(SalesOrder $order): Response
    {
        $this->authorize('view', $order);

        $order->load(['customer', 'paymentTerm', 'currency', 'lines.product', 'quote:id,reference']);

        return Inertia::render('Sales/Orders/Show', [
            'order'         => new SalesOrderResource($order),
            'quote'         => $order->quote,
            'invoices'      => $order->invoices()->latest()->get(['id', 'reference', 'invoice_date', 'status', 'total_ttc', 'amount_due']),
            'deliveryNotes' => $order->deliveryNotes()->latest()->get(['id', 'reference', 'date', 'status']),
        ]);
    }

    public function edit(SalesOrder $order): Response
    {
        $this->authorize('update', $order);

        $order->load('lines');

        return Inertia::render('Sales/Orders/Edit', [
            'order' => new SalesOrderResource($order),
            ...$this->documentOptions(),
        ]);
    }

    public function update(UpdateSalesOrderRequest $request, SalesOrder $order): RedirectResponse
    {
        $this->authorize('update', $order);

        $this->orderService->update($order, $request->validated());

        return redirect()->route('sales.orders.show', $order)->with('success', 'Commande mise à jour.');
    }

    public function destroy(SalesOrder $order): RedirectResponse
    {
        $this->authorize('delete', $order);

        $this->orderService->delete($order);

        return redirect()->route('sales.orders.index')->with('success', "Commande {$order->reference} supprimée.");
    }

    public function confirm(SalesOrder $order): RedirectResponse
    {
        $this->authorize('confirm', $order);
        $this->orderService->confirm($order);

        return back()->with('success', 'Commande confirmée.');
    }

    public function cancel(Request $request, SalesOrder $order): RedirectResponse
    {
        $this->authorize('cancel', $order);

        $data = $request->validate(['reason' => ['nullable', 'string', 'max:2000']]);
        $this->orderService->cancel($order, $data['reason'] ?? null);

        return back()->with('success', 'Commande annulée.');
    }

    public function generateInvoice(SalesOrder $order): RedirectResponse
    {
        $this->authorize('generateInvoice', $order);

        $invoice = $this->orderService->generateInvoice($order);

        return redirect()->route('sales.invoices.show', $invoice)->with('success', "Facture {$invoice->reference} créée en brouillon.");
    }

    public function generateDeliveryNote(SalesOrder $order): RedirectResponse
    {
        $this->authorize('generateDeliveryNote', $order);

        $note = $this->orderService->generateDeliveryNote($order);

        return back()->with('success', "Bon de livraison {$note->reference} créé.");
    }
}
