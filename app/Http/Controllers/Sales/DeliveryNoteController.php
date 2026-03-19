<?php

declare(strict_types=1);

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\StoreDeliveryNoteRequest;
use App\Http\Requests\Sales\UpdateDeliveryNoteRequest;
use App\Http\Resources\Sales\DeliveryNoteResource;
use App\Models\Sales\DeliveryNote;
use App\Services\Sales\DeliveryNoteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

class DeliveryNoteController extends Controller
{
    public function __construct(
        private readonly DeliveryNoteService $deliveryNoteService,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', DeliveryNote::class);

        $deliveryNotes = DeliveryNote::with(['customer', 'salesOrder', 'lines'])
            ->when($request->search, fn ($q) => $q->where('reference', 'like', "%{$request->search}%"))
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->when($request->customer_id, fn ($q) => $q->where('customer_id', $request->customer_id))
            ->when($request->sales_order_id, fn ($q) => $q->where('sales_order_id', $request->sales_order_id))
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return DeliveryNoteResource::collection($deliveryNotes);
    }

    public function store(StoreDeliveryNoteRequest $request): JsonResponse
    {
        $this->authorize('create', DeliveryNote::class);

        $deliveryNote = $this->deliveryNoteService->create($request->validated());

        return (new DeliveryNoteResource($deliveryNote))
            ->response()
            ->setStatusCode(201);
    }

    public function show(DeliveryNote $deliveryNote): DeliveryNoteResource
    {
        $this->authorize('view', $deliveryNote);

        $deliveryNote->load(['customer', 'salesOrder', 'lines', 'createdBy', 'shippedBy', 'deliveredBy']);

        return new DeliveryNoteResource($deliveryNote);
    }

    public function update(UpdateDeliveryNoteRequest $request, DeliveryNote $deliveryNote): DeliveryNoteResource
    {
        $this->authorize('update', $deliveryNote);

        $deliveryNote = $this->deliveryNoteService->update($deliveryNote, $request->validated());

        return new DeliveryNoteResource($deliveryNote);
    }

    public function destroy(DeliveryNote $deliveryNote): JsonResponse
    {
        $this->authorize('delete', $deliveryNote);

        try {
            $this->deliveryNoteService->delete($deliveryNote);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }

        return response()->json(null, 204);
    }

    public function ship(Request $request, DeliveryNote $deliveryNote): DeliveryNoteResource
    {
        $this->authorize('ship', $deliveryNote);

        $request->validate([
            'shipped_at'      => ['nullable', 'date'],
            'carrier'         => ['nullable', 'string', 'max:255'],
            'tracking_number' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $deliveryNote = $this->deliveryNoteService->ship($deliveryNote, $request->only([
                'shipped_at', 'carrier', 'tracking_number',
            ]));
        } catch (ValidationException $e) {
            abort(422, $e->getMessage());
        }

        return new DeliveryNoteResource($deliveryNote);
    }

    public function deliver(DeliveryNote $deliveryNote): DeliveryNoteResource
    {
        $this->authorize('deliver', $deliveryNote);

        try {
            $deliveryNote = $this->deliveryNoteService->deliver($deliveryNote);
        } catch (ValidationException $e) {
            abort(422, $e->getMessage());
        }

        return new DeliveryNoteResource($deliveryNote);
    }

    public function return(Request $request, DeliveryNote $deliveryNote): DeliveryNoteResource
    {
        $this->authorize('return', $deliveryNote);

        $request->validate([
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $deliveryNote = $this->deliveryNoteService->return($deliveryNote, $request->reason);
        } catch (ValidationException $e) {
            abort(422, $e->getMessage());
        }

        return new DeliveryNoteResource($deliveryNote);
    }
}
