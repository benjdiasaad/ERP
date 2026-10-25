<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Purchasing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Purchasing\StoreSupplierRequest;
use App\Http\Requests\Purchasing\UpdateSupplierRequest;
use App\Http\Resources\Purchasing\SupplierResource;
use App\Models\Finance\PaymentTerm;
use App\Models\Purchasing\Supplier;
use App\Services\Purchasing\SupplierService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SupplierController extends Controller
{
    public function __construct(
        private readonly SupplierService $supplierService,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Supplier::class);

        $filters = $request->only(['search', 'status']);

        $suppliers = Supplier::query()
            ->when($filters['search'] ?? null, function ($query, $search) {
                $like = '%' . $search . '%';
                $query->where(fn ($query) => $query
                    ->whereLike('name', $like)->orWhereLike('code', $like)
                    ->orWhereLike('email', $like)->orWhereLike('city', $like)->orWhereLike('ice', $like));
            })
            ->when(($filters['status'] ?? null) === 'active', fn ($query) => $query->where('is_active', true))
            ->when(($filters['status'] ?? null) === 'inactive', fn ($query) => $query->where('is_active', false))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Purchasing/Suppliers/Index', [
            'suppliers' => SupplierResource::collection($suppliers),
            'filters'   => $filters,
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Supplier::class);

        return Inertia::render('Purchasing/Suppliers/Create', $this->formOptions());
    }

    public function store(StoreSupplierRequest $request): RedirectResponse
    {
        $this->authorize('create', Supplier::class);

        $supplier = $this->supplierService->create($request->validated());

        return redirect()->route('purchasing.suppliers.show', $supplier)->with('success', "Fournisseur {$supplier->code} créé.");
    }

    public function show(Supplier $supplier): Response
    {
        $this->authorize('view', $supplier);

        $supplier->load('paymentTerm');
        $user = auth()->user();

        return Inertia::render('Purchasing/Suppliers/Show', [
            'supplier' => new SupplierResource($supplier),
            'orders'   => $user->hasPermission('purchase_orders.view_any')
                ? $supplier->purchaseOrders()->latest('order_date')->limit(10)->get(['id', 'reference', 'order_date', 'status', 'total_ttc'])
                : null,
            'invoices' => $user->hasPermission('purchase_invoices.view_any')
                ? $supplier->purchaseInvoices()->latest('invoice_date')->limit(10)->get(['id', 'reference', 'invoice_date', 'status', 'total_ttc', 'amount_due'])
                : null,
        ]);
    }

    public function edit(Supplier $supplier): Response
    {
        $this->authorize('update', $supplier);

        return Inertia::render('Purchasing/Suppliers/Edit', [
            'supplier' => new SupplierResource($supplier),
            ...$this->formOptions(),
        ]);
    }

    public function update(UpdateSupplierRequest $request, Supplier $supplier): RedirectResponse
    {
        $this->authorize('update', $supplier);

        $this->supplierService->update($supplier, $request->validated());

        return redirect()->route('purchasing.suppliers.show', $supplier)->with('success', 'Fournisseur mis à jour.');
    }

    public function destroy(Supplier $supplier): RedirectResponse
    {
        $this->authorize('delete', $supplier);

        $this->supplierService->delete($supplier);

        return redirect()->route('purchasing.suppliers.index')->with('success', 'Fournisseur supprimé.');
    }

    private function formOptions(): array
    {
        return [
            'paymentTerms' => PaymentTerm::where('is_active', true)->orderBy('days')->get(['id', 'name'])
                ->map(fn (PaymentTerm $t) => ['value' => $t->id, 'label' => $t->name]),
        ];
    }
}
