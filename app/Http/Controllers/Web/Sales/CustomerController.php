<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Sales;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\StoreCustomerRequest;
use App\Http\Requests\Sales\UpdateCustomerRequest;
use App\Http\Resources\Sales\CustomerResource;
use App\Models\CRM\Contact;
use App\Models\CRM\Opportunity;
use App\Models\Finance\Currency;
use App\Models\Finance\PaymentTerm;
use App\Models\Sales\Customer;
use App\Services\Sales\CustomerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CustomerController extends Controller
{
    public function __construct(
        private readonly CustomerService $customerService,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Customer::class);

        $filters = $request->only(['search', 'type', 'status']);

        $customers = Customer::query()
            ->withCount('quotes')
            ->when($filters['search'] ?? null, function ($query, $search) {
                $like = '%' . $search . '%';
                $query->where(fn ($query) => $query
                    ->whereLike('name', $like)
                    ->orWhereLike('code', $like)
                    ->orWhereLike('email', $like)
                    ->orWhereLike('city', $like));
            })
            ->when($filters['type'] ?? null, fn ($query, $type) => $query->where('type', $type))
            ->when(($filters['status'] ?? null) === 'active', fn ($query) => $query->where('is_active', true))
            ->when(($filters['status'] ?? null) === 'inactive', fn ($query) => $query->where('is_active', false))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Sales/Customers/Index', [
            'customers' => CustomerResource::collection($customers),
            'filters'   => $filters,
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Customer::class);

        return Inertia::render('Sales/Customers/Create', $this->formOptions());
    }

    public function store(StoreCustomerRequest $request): RedirectResponse
    {
        $this->authorize('create', Customer::class);

        $customer = $this->customerService->create($request->validated());

        return redirect()->route('sales.customers.show', $customer)->with('success', "Client {$customer->code} créé.");
    }

    public function show(Customer $customer): Response
    {
        $this->authorize('view', $customer);

        $customer->load(['paymentTerm', 'currency']);
        $user = auth()->user();

        return Inertia::render('Sales/Customers/Show', [
            'customer' => new CustomerResource($customer),
            'credit'   => $this->customerService->getCreditInfo($customer),
            'quotes'   => $user->hasPermission('quotes.view_any')
                ? $customer->quotes()->latest('quote_date')->limit(10)->get(['id', 'reference', 'quote_date', 'status', 'total_ttc'])
                : null,
            'contacts' => $user->hasPermission('contacts.view_any')
                ? Contact::where('customer_id', $customer->id)->orderBy('last_name')->get(['id', 'first_name', 'last_name', 'job_title', 'email', 'phone'])
                    ->map(fn (Contact $c) => [...$c->only(['id', 'job_title', 'email', 'phone']), 'full_name' => $c->full_name])
                : null,
            'opportunities' => $user->hasPermission('opportunities.view_any')
                ? Opportunity::where('customer_id', $customer->id)->latest()->get(['id', 'reference', 'title', 'stage', 'value'])
                : null,
        ]);
    }

    public function edit(Customer $customer): Response
    {
        $this->authorize('update', $customer);

        return Inertia::render('Sales/Customers/Edit', [
            'customer' => new CustomerResource($customer),
            ...$this->formOptions(),
        ]);
    }

    public function update(UpdateCustomerRequest $request, Customer $customer): RedirectResponse
    {
        $this->authorize('update', $customer);

        $this->customerService->update($customer, $request->validated());

        return redirect()->route('sales.customers.show', $customer)->with('success', 'Client mis à jour.');
    }

    public function destroy(Customer $customer): RedirectResponse
    {
        $this->authorize('delete', $customer);

        $this->customerService->delete($customer);

        return redirect()->route('sales.customers.index')->with('success', 'Client supprimé.');
    }

    private function formOptions(): array
    {
        return [
            'paymentTerms' => PaymentTerm::where('is_active', true)->orderBy('days')->get(['id', 'name', 'days'])
                ->map(fn (PaymentTerm $t) => ['value' => $t->id, 'label' => $t->name]),
            'currencies'   => Currency::orderBy('code')->get(['id', 'code', 'name'])
                ->map(fn (Currency $c) => ['value' => $c->id, 'label' => "{$c->code} · {$c->name}"]),
        ];
    }
}
