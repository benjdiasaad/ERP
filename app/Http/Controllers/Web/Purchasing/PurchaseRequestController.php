<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Purchasing;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Purchasing\Concerns\ProvidesPurchaseOptions;
use App\Http\Requests\Purchasing\StorePurchaseRequestRequest;
use App\Http\Requests\Purchasing\UpdatePurchaseRequestRequest;
use App\Http\Resources\Purchasing\PurchaseRequestResource;
use App\Models\Purchasing\PurchaseRequest;
use App\Services\Purchasing\PurchaseRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PurchaseRequestController extends Controller
{
    use ProvidesPurchaseOptions;

    public const STATUSES = ['draft', 'submitted', 'approved', 'rejected', 'converted', 'cancelled'];

    public function __construct(
        private readonly PurchaseRequestService $requestService,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', PurchaseRequest::class);

        $filters = $request->only(['search', 'status', 'priority']);

        $requests = PurchaseRequest::query()
            ->with(['supplier:id,name', 'requestedBy:id,name'])
            ->withSum('lines', 'estimated_total')
            ->when($filters['search'] ?? null, function ($query, $search) {
                $like = '%' . $search . '%';
                $query->where(fn ($query) => $query->whereLike('reference', $like)->orWhereLike('title', $like));
            })
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['priority'] ?? null, fn ($query, $priority) => $query->where('priority', $priority))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $counts = PurchaseRequest::query()->selectRaw('status, COUNT(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status');

        return Inertia::render('Purchasing/Requests/Index', [
            'requests' => PurchaseRequestResource::collection($requests),
            'filters'  => $filters,
            'statuses' => self::STATUSES,
            'counts'   => collect(self::STATUSES)->mapWithKeys(fn ($status) => [$status => (int) ($counts[$status] ?? 0)]),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', PurchaseRequest::class);

        $options = $this->purchaseOptions();

        return Inertia::render('Purchasing/Requests/Create', [
            'suppliers' => $options['suppliers'],
            'products'  => $options['products'],
        ]);
    }

    public function store(StorePurchaseRequestRequest $request): RedirectResponse
    {
        $this->authorize('create', PurchaseRequest::class);

        $pr = $this->requestService->create($request->validated());

        return redirect()->route('purchasing.requests.show', $pr)->with('success', "Demande {$pr->reference} créée.");
    }

    public function show(PurchaseRequest $purchaseRequest): Response
    {
        $this->authorize('view', $purchaseRequest);

        $purchaseRequest->load(['supplier', 'lines', 'requestedBy:id,name', 'approvedBy:id,name', 'rejectedBy:id,name']);

        return Inertia::render('Purchasing/Requests/Show', [
            'request'   => new PurchaseRequestResource($purchaseRequest),
            'order'     => $purchaseRequest->purchaseOrders()->latest()->first(['id', 'reference', 'status']),
            'suppliers' => $purchaseRequest->status === 'approved' ? $this->purchaseOptions()['suppliers'] : [],
        ]);
    }

    public function edit(PurchaseRequest $purchaseRequest): Response
    {
        $this->authorize('update', $purchaseRequest);

        $purchaseRequest->load('lines');
        $options = $this->purchaseOptions();

        return Inertia::render('Purchasing/Requests/Edit', [
            'request'   => new PurchaseRequestResource($purchaseRequest),
            'suppliers' => $options['suppliers'],
            'products'  => $options['products'],
        ]);
    }

    public function update(UpdatePurchaseRequestRequest $request, PurchaseRequest $purchaseRequest): RedirectResponse
    {
        $this->authorize('update', $purchaseRequest);

        $this->requestService->update($purchaseRequest, $request->validated());

        return redirect()->route('purchasing.requests.show', $purchaseRequest)->with('success', 'Demande mise à jour.');
    }

    public function destroy(PurchaseRequest $purchaseRequest): RedirectResponse
    {
        $this->authorize('delete', $purchaseRequest);

        $this->requestService->delete($purchaseRequest);

        return redirect()->route('purchasing.requests.index')->with('success', "Demande {$purchaseRequest->reference} supprimée.");
    }

    public function submit(PurchaseRequest $purchaseRequest): RedirectResponse
    {
        $this->authorize('submit', $purchaseRequest);
        $this->requestService->submit($purchaseRequest);

        return back()->with('success', 'Demande soumise pour approbation.');
    }

    public function approve(PurchaseRequest $purchaseRequest): RedirectResponse
    {
        $this->authorize('approve', $purchaseRequest);
        $this->requestService->approve($purchaseRequest);

        return back()->with('success', 'Demande approuvée.');
    }

    public function reject(Request $request, PurchaseRequest $purchaseRequest): RedirectResponse
    {
        $this->authorize('reject', $purchaseRequest);

        $data = $request->validate(['reason' => ['nullable', 'string', 'max:1000']]);
        $this->requestService->reject($purchaseRequest, $data['reason'] ?? null);

        return back()->with('success', 'Demande rejetée.');
    }

    public function cancel(PurchaseRequest $purchaseRequest): RedirectResponse
    {
        $this->authorize('cancel', $purchaseRequest);
        $this->requestService->cancel($purchaseRequest);

        return back()->with('success', 'Demande annulée.');
    }

    public function convert(Request $request, PurchaseRequest $purchaseRequest): RedirectResponse
    {
        $this->authorize('convert', $purchaseRequest);

        $data = $request->validate([
            'supplier_id' => ['nullable', 'integer', Rule::exists('suppliers', 'id')
                ->where('company_id', $request->user()->current_company_id)->whereNull('deleted_at')],
        ]);

        $order = $this->requestService->convertToOrder($purchaseRequest, $data['supplier_id'] ?? null);

        return redirect()->route('purchasing.orders.show', $order)->with('success', "Bon de commande {$order->reference} créé en brouillon.");
    }
}
