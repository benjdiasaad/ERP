<?php

declare(strict_types=1);

namespace App\Http\Controllers\Purchasing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Purchasing\StoreReceptionNoteRequest;
use App\Http\Requests\Purchasing\UpdateReceptionNoteRequest;
use App\Http\Resources\Purchasing\ReceptionNoteResource;
use App\Models\Purchasing\ReceptionNote;
use App\Services\Purchasing\ReceptionNoteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

class ReceptionNoteController extends Controller
{
    public function __construct(
        private readonly ReceptionNoteService $receptionNoteService,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', ReceptionNote::class);

        $notes = ReceptionNote::with(['lines', 'supplier', 'purchaseOrder'])
            ->when($request->search, fn ($q) => $q->where('reference', 'like', "%{$request->search}%"))
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->when($request->purchase_order_id, fn ($q) => $q->where('purchase_order_id', $request->purchase_order_id))
            ->when($request->supplier_id, fn ($q) => $q->where('supplier_id', $request->supplier_id))
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return ReceptionNoteResource::collection($notes);
    }

    public function store(StoreReceptionNoteRequest $request): JsonResponse
    {
        $this->authorize('create', ReceptionNote::class);

        $note = $this->receptionNoteService->create($request->validated());

        return (new ReceptionNoteResource($note))
            ->response()
            ->setStatusCode(201);
    }

    public function show(ReceptionNote $receptionNote): ReceptionNoteResource
    {
        $this->authorize('view', $receptionNote);

        $receptionNote->load(['lines', 'supplier', 'purchaseOrder']);

        return new ReceptionNoteResource($receptionNote);
    }

    public function update(UpdateReceptionNoteRequest $request, ReceptionNote $receptionNote): JsonResponse|ReceptionNoteResource
    {
        $this->authorize('update', $receptionNote);

        try {
            $receptionNote = $this->receptionNoteService->update($receptionNote, $request->validated());
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }

        return new ReceptionNoteResource($receptionNote);
    }

    public function destroy(ReceptionNote $receptionNote): JsonResponse
    {
        $this->authorize('delete', $receptionNote);

        try {
            $this->receptionNoteService->delete($receptionNote);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }

        return response()->json(null, 204);
    }

    public function confirm(ReceptionNote $receptionNote): JsonResponse|ReceptionNoteResource
    {
        $this->authorize('confirm', $receptionNote);

        try {
            $receptionNote = $this->receptionNoteService->confirm($receptionNote);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }

        return new ReceptionNoteResource($receptionNote);
    }

    public function cancel(Request $request, ReceptionNote $receptionNote): JsonResponse|ReceptionNoteResource
    {
        $this->authorize('cancel', $receptionNote);

        $request->validate([
            'cancellation_reason' => ['nullable', 'string'],
        ]);

        try {
            $receptionNote = $this->receptionNoteService->cancel($receptionNote, $request->cancellation_reason);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }

        return new ReceptionNoteResource($receptionNote);
    }
}
