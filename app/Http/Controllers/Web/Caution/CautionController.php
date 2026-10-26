<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Caution;

use App\Http\Controllers\Controller;
use App\Http\Requests\Caution\StoreCautionRequest;
use App\Http\Requests\Caution\UpdateCautionRequest;
use App\Http\Resources\Caution\CautionResource;
use App\Models\Caution\Caution;
use App\Models\Caution\CautionType;
use App\Models\Purchasing\Supplier;
use App\Models\Sales\Customer;
use App\Services\Caution\CautionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Guarantees (cautions) given to or received from partners:
 * draft → active → partially returned / returned / forfeited (or cancelled while draft).
 */
class CautionController extends Controller
{
    private const STATUSES = ['draft', 'active', 'partially_returned', 'returned', 'expired', 'forfeited', 'cancelled'];

    public function __construct(
        private readonly CautionService $cautionService,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Caution::class);

        $filters = $request->only(['search', 'status', 'direction', 'caution_type_id', 'expiring']);

        $cautions = Caution::query()
            ->with(['cautionType', 'partner'])
            ->when($filters['search'] ?? null, function ($query, $search) {
                $like = '%' . $search . '%';
                $query->where(fn ($query) => $query
                    ->whereLike('document_reference', $like)->orWhereLike('bank_reference', $like)->orWhereLike('bank_name', $like)
                    ->orWhereHasMorph('partner', [Customer::class, Supplier::class], fn ($query) => $query->whereLike('name', $like)));
            })
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['direction'] ?? null, fn ($query, $direction) => $query->where('direction', $direction))
            ->when($filters['caution_type_id'] ?? null, fn ($query, $id) => $query->where('caution_type_id', $id))
            ->when($filters['expiring'] ?? null, fn ($query) => $query
                ->whereIn('status', ['active', 'partially_returned'])
                ->whereBetween('expiry_date', [now()->toDateString(), now()->addDays(30)->toDateString()]))
            ->orderBy('expiry_date')
            ->paginate(15)
            ->withQueryString();

        $counts = Caution::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        // Amounts still held (neither returned nor forfeited), per direction.
        $held = Caution::query()->whereIn('status', ['active', 'partially_returned'])
            ->selectRaw('direction, sum(amount - amount_returned - amount_forfeited) as total')
            ->groupBy('direction')->pluck('total', 'direction');

        return Inertia::render('Caution/Cautions/Index', [
            'cautions' => CautionResource::collection($cautions),
            'counts'   => collect(self::STATUSES)->mapWithKeys(fn ($status) => [$status => (int) ($counts[$status] ?? 0)]),
            'stats'    => [
                'given'    => round((float) ($held['given'] ?? 0), 2),
                'received' => round((float) ($held['received'] ?? 0), 2),
                'expiring' => Caution::whereIn('status', ['active', 'partially_returned'])
                    ->whereBetween('expiry_date', [now()->toDateString(), now()->addDays(30)->toDateString()])->count(),
            ],
            'types'    => $this->typeOptions(),
            'filters'  => $filters,
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', Caution::class);

        return Inertia::render('Caution/Cautions/Create', [
            ...$this->formOptions(),
            'defaults' => [
                'direction'    => $request->input('direction') === 'received' ? 'received' : 'given',
                'partner_type' => in_array($request->input('partner_type'), ['customer', 'supplier'], true) ? $request->input('partner_type') : 'customer',
                'partner_id'   => $request->integer('partner_id') ?: null,
                'issue_date'   => now()->toDateString(),
                'expiry_date'  => now()->addMonths(6)->toDateString(),
            ],
        ]);
    }

    public function store(StoreCautionRequest $request): RedirectResponse
    {
        $this->authorize('create', Caution::class);

        $caution = $this->cautionService->create($request->validated());

        return redirect()->route('finance.cautions.show', $caution)->with('success', 'Caution enregistrée.');
    }

    public function show(Caution $caution): Response
    {
        $this->authorize('view', $caution);

        $caution->load(['cautionType', 'partner', 'histories' => fn ($query) => $query->latest('id'), 'histories.author']);

        return Inertia::render('Caution/Cautions/Show', [
            'caution' => new CautionResource($caution),
        ]);
    }

    public function edit(Caution $caution): Response|RedirectResponse
    {
        $this->authorize('update', $caution);

        if ($caution->status !== 'draft') {
            return redirect()->route('finance.cautions.show', $caution)->with('error', 'Seules les cautions en brouillon sont modifiables.');
        }

        return Inertia::render('Caution/Cautions/Edit', [
            'caution' => new CautionResource($caution->load('partner')),
            ...$this->formOptions(),
        ]);
    }

    public function update(UpdateCautionRequest $request, Caution $caution): RedirectResponse
    {
        $this->authorize('update', $caution);

        if ($caution->status !== 'draft') {
            return back()->withErrors(['status' => 'Seules les cautions en brouillon sont modifiables.']);
        }

        $this->cautionService->update($caution, $request->validated());

        return redirect()->route('finance.cautions.show', $caution)->with('success', 'Caution mise à jour.');
    }

    public function destroy(Caution $caution): RedirectResponse
    {
        $this->authorize('delete', $caution);

        if (! in_array($caution->status, ['draft', 'cancelled'], true)) {
            return back()->withErrors(['status' => 'Seules les cautions en brouillon ou annulées peuvent être supprimées.']);
        }

        $this->cautionService->delete($caution);

        return redirect()->route('finance.cautions.index')->with('success', 'Caution supprimée.');
    }

    public function activate(Caution $caution): RedirectResponse
    {
        $this->cautionService->activate($caution);

        return back()->with('success', 'Caution activée.');
    }

    public function partialReturn(Request $request, Caution $caution): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0'],
            'notes'  => ['nullable', 'string', 'max:1000'],
        ]);

        $this->cautionService->partialReturn($caution, (float) $data['amount'], $data['notes'] ?? null);

        return back()->with('success', 'Restitution enregistrée.');
    }

    public function fullReturn(Request $request, Caution $caution): RedirectResponse
    {
        $data = $request->validate(['notes' => ['nullable', 'string', 'max:1000']]);

        $this->cautionService->fullReturn($caution, $data['notes'] ?? null);

        return back()->with('success', 'Caution restituée.');
    }

    public function extend(Request $request, Caution $caution): RedirectResponse
    {
        $data = $request->validate([
            'expiry_date' => ['required', 'date'],
            'notes'       => ['nullable', 'string', 'max:1000'],
        ]);

        $this->cautionService->extend($caution, Carbon::parse($data['expiry_date']), $data['notes'] ?? null);

        return back()->with('success', 'Échéance prolongée.');
    }

    public function forfeit(Request $request, Caution $caution): RedirectResponse
    {
        $data = $request->validate(['notes' => ['nullable', 'string', 'max:1000']]);

        $this->cautionService->forfeit($caution, $data['notes'] ?? null);

        return back()->with('success', 'Caution saisie.');
    }

    public function cancel(Request $request, Caution $caution): RedirectResponse
    {
        $data = $request->validate(['notes' => ['nullable', 'string', 'max:1000']]);

        $this->cautionService->cancel($caution, $data['notes'] ?? null);

        return back()->with('success', 'Caution annulée.');
    }

    private function typeOptions()
    {
        return CautionType::orderBy('name')->get(['id', 'name', 'default_percentage', 'is_active'])
            ->map(fn (CautionType $t) => ['value' => $t->id, 'label' => $t->name, 'is_active' => (bool) $t->is_active]);
    }

    private function formOptions(): array
    {
        return [
            'types'     => $this->typeOptions()->where('is_active', true)->values(),
            'customers' => Customer::where('is_active', true)->orderBy('name')->get(['id', 'code', 'name'])
                ->map(fn (Customer $c) => ['value' => $c->id, 'label' => "{$c->name} ({$c->code})"]),
            'suppliers' => Supplier::where('is_active', true)->orderBy('name')->get(['id', 'code', 'name'])
                ->map(fn (Supplier $s) => ['value' => $s->id, 'label' => "{$s->name} ({$s->code})"]),
        ];
    }
}
