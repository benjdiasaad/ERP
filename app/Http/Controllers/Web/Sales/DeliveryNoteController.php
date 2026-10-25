<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Sales;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Inventory\Concerns\ProvidesStockOptions;
use App\Http\Controllers\Web\Sales\Concerns\ProvidesDocumentOptions;
use App\Http\Requests\Sales\StoreDeliveryNoteRequest;
use App\Http\Requests\Sales\UpdateDeliveryNoteRequest;
use App\Http\Resources\Sales\DeliveryNoteResource;
use App\Models\Inventory\Warehouse;
use App\Models\Sales\DeliveryNote;
use App\Services\Sales\DeliveryNoteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DeliveryNoteController extends Controller
{
    use ProvidesDocumentOptions, ProvidesStockOptions;

    public const STATUSES = ['draft', 'ready', 'shipped', 'delivered', 'returned'];

    public function __construct(
        private readonly DeliveryNoteService $deliveryNoteService,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', DeliveryNote::class);

        $filters = $request->only(['search', 'status']);

        $notes = DeliveryNote::query()
            ->with(['customer:id,name,code', 'salesOrder:id,reference'])
            ->when($filters['search'] ?? null, function ($query, $search) {
                $like = '%' . $search . '%';
                $query->where(fn ($query) => $query
                    ->whereLike('reference', $like)
                    ->orWhereLike('tracking_number', $like)
                    ->orWhereHas('customer', fn ($query) => $query->whereLike('name', $like)));
            })
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->latest('date')
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        $counts = DeliveryNote::query()->selectRaw('status, COUNT(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status');

        return Inertia::render('Sales/DeliveryNotes/Index', [
            'notes'    => DeliveryNoteResource::collection($notes),
            'filters'  => $filters,
            'statuses' => self::STATUSES,
            'counts'   => collect(self::STATUSES)->mapWithKeys(fn ($status) => [$status => (int) ($counts[$status] ?? 0)]),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', DeliveryNote::class);

        $options = $this->documentOptions();

        return Inertia::render('Sales/DeliveryNotes/Create', [
            'customers'  => $options['customers'],
            'products'   => $options['products'],
            'warehouses' => $this->warehouseOptions(),
            'defaults'   => [
                'customer_id'  => $request->integer('customer_id') ?: null,
                'date'         => now()->toDateString(),
                'warehouse_id' => Warehouse::defaultId(),
            ],
        ]);
    }

    public function store(StoreDeliveryNoteRequest $request): RedirectResponse
    {
        $this->authorize('create', DeliveryNote::class);

        $note = $this->deliveryNoteService->create($request->validated());

        return redirect()->route('sales.delivery-notes.show', $note)->with('success', "Bon de livraison {$note->reference} créé.");
    }

    public function show(DeliveryNote $deliveryNote): Response
    {
        $this->authorize('view', $deliveryNote);

        $deliveryNote->load(['customer', 'salesOrder:id,reference,status', 'lines.product', 'warehouse:id,code,name']);

        return Inertia::render('Sales/DeliveryNotes/Show', [
            'note' => new DeliveryNoteResource($deliveryNote),
        ]);
    }

    public function edit(DeliveryNote $deliveryNote): Response
    {
        $this->authorize('update', $deliveryNote);

        $deliveryNote->load('lines');
        $options = $this->documentOptions();

        return Inertia::render('Sales/DeliveryNotes/Edit', [
            'note'       => new DeliveryNoteResource($deliveryNote),
            'customers'  => $options['customers'],
            'products'   => $options['products'],
            'warehouses' => $this->warehouseOptions(),
        ]);
    }

    public function update(UpdateDeliveryNoteRequest $request, DeliveryNote $deliveryNote): RedirectResponse
    {
        $this->authorize('update', $deliveryNote);

        $this->deliveryNoteService->update($deliveryNote, $request->validated());

        return redirect()->route('sales.delivery-notes.show', $deliveryNote)->with('success', 'Bon de livraison mis à jour.');
    }

    public function destroy(DeliveryNote $deliveryNote): RedirectResponse
    {
        $this->authorize('delete', $deliveryNote);

        $this->deliveryNoteService->delete($deliveryNote);

        return redirect()->route('sales.delivery-notes.index')->with('success', "Bon de livraison {$deliveryNote->reference} supprimé.");
    }

    public function ready(DeliveryNote $deliveryNote): RedirectResponse
    {
        $this->authorize('update', $deliveryNote);
        $this->deliveryNoteService->markReady($deliveryNote);

        return back()->with('success', 'Bon de livraison prêt à expédier.');
    }

    public function ship(Request $request, DeliveryNote $deliveryNote): RedirectResponse
    {
        $this->authorize('ship', $deliveryNote);

        $data = $request->validate([
            'shipped_at'      => ['nullable', 'date'],
            'carrier'         => ['nullable', 'string', 'max:255'],
            'tracking_number' => ['nullable', 'string', 'max:255'],
        ]);
        $this->deliveryNoteService->ship($deliveryNote, $data);

        return back()->with('success', 'Expédition enregistrée.');
    }

    public function deliver(DeliveryNote $deliveryNote): RedirectResponse
    {
        $this->authorize('deliver', $deliveryNote);
        $this->deliveryNoteService->deliver($deliveryNote);

        return back()->with('success', 'Livraison confirmée.');
    }

    public function return(Request $request, DeliveryNote $deliveryNote): RedirectResponse
    {
        $this->authorize('return', $deliveryNote);

        $data = $request->validate(['reason' => ['nullable', 'string', 'max:1000']]);
        $this->deliveryNoteService->return($deliveryNote, $data['reason'] ?? '');

        return back()->with('success', 'Retour enregistré.');
    }
}
