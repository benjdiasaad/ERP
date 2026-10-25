<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Purchasing;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Inventory\Concerns\ProvidesStockOptions;
use App\Http\Requests\Purchasing\UpdateReceptionNoteRequest;
use App\Http\Resources\Purchasing\ReceptionNoteResource;
use App\Models\Purchasing\ReceptionNote;
use App\Services\Purchasing\ReceptionNoteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Reception notes are generated from a purchase order; the screens let the
 * warehouse record what actually arrived, then confirm or cancel.
 */
class ReceptionNoteController extends Controller
{
    use ProvidesStockOptions;

    public const STATUSES = ['draft', 'confirmed', 'cancelled'];

    public function __construct(
        private readonly ReceptionNoteService $receptionService,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', ReceptionNote::class);

        $filters = $request->only(['search', 'status']);

        $notes = ReceptionNote::query()
            ->with(['supplier:id,name', 'purchaseOrder:id,reference'])
            ->when($filters['search'] ?? null, function ($query, $search) {
                $like = '%' . $search . '%';
                $query->where(fn ($query) => $query
                    ->whereLike('reference', $like)
                    ->orWhereHas('supplier', fn ($query) => $query->whereLike('name', $like))
                    ->orWhereHas('purchaseOrder', fn ($query) => $query->whereLike('reference', $like)));
            })
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->latest('reception_date')
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        $counts = ReceptionNote::query()->selectRaw('status, COUNT(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status');

        return Inertia::render('Purchasing/Receptions/Index', [
            'notes'    => ReceptionNoteResource::collection($notes),
            'filters'  => $filters,
            'statuses' => self::STATUSES,
            'counts'   => collect(self::STATUSES)->mapWithKeys(fn ($status) => [$status => (int) ($counts[$status] ?? 0)]),
        ]);
    }

    public function show(ReceptionNote $receptionNote): Response
    {
        $this->authorize('view', $receptionNote);

        $receptionNote->load(['supplier', 'purchaseOrder:id,reference,status', 'lines', 'warehouse:id,code,name']);

        return Inertia::render('Purchasing/Receptions/Show', [
            'note'       => new ReceptionNoteResource($receptionNote),
            'warehouses' => $receptionNote->status === 'draft' ? $this->warehouseOptions() : [],
        ]);
    }

    /**
     * Record received / rejected quantities on a draft (other fields unchanged).
     */
    public function update(UpdateReceptionNoteRequest $request, ReceptionNote $receptionNote): RedirectResponse
    {
        $this->authorize('update', $receptionNote);

        $this->receptionService->update($receptionNote, $request->validated());

        return back()->with('success', 'Quantités enregistrées.');
    }

    public function destroy(ReceptionNote $receptionNote): RedirectResponse
    {
        $this->authorize('delete', $receptionNote);

        $this->receptionService->delete($receptionNote);

        return redirect()->route('purchasing.receptions.index')->with('success', "Bon de réception {$receptionNote->reference} supprimé.");
    }

    public function confirm(ReceptionNote $receptionNote): RedirectResponse
    {
        $this->authorize('confirm', $receptionNote);
        $this->receptionService->confirm($receptionNote);

        return back()->with('success', 'Réception confirmée.');
    }

    public function cancel(Request $request, ReceptionNote $receptionNote): RedirectResponse
    {
        $this->authorize('cancel', $receptionNote);

        $data = $request->validate(['reason' => ['nullable', 'string', 'max:1000']]);
        $this->receptionService->cancel($receptionNote, $data['reason'] ?? null);

        return back()->with('success', 'Bon de réception annulé.');
    }
}
