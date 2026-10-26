<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\StoreJournalEntryRequest;
use App\Http\Requests\Finance\UpdateJournalEntryRequest;
use App\Http\Resources\Finance\JournalEntryResource;
use App\Models\Finance\ChartOfAccount;
use App\Models\Finance\JournalEntry;
use App\Services\Finance\JournalEntryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Journal entries ("écritures"): draft (editable) → posted (balanced, final) or cancelled.
 */
class JournalEntryController extends Controller
{
    private const STATUSES = ['draft', 'posted', 'cancelled'];

    public function __construct(
        private readonly JournalEntryService $entryService,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', JournalEntry::class);

        $filters = $request->only(['search', 'status', 'from', 'to']);

        $entries = JournalEntry::query()
            ->withCount('lines')
            ->when($filters['search'] ?? null, function ($query, $search) {
                $like = '%' . $search . '%';
                $query->where(fn ($query) => $query->whereLike('reference', $like)->orWhereLike('description', $like));
            })
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['from'] ?? null, fn ($query, $from) => $query->whereDate('date', '>=', $from))
            ->when($filters['to'] ?? null, fn ($query, $to) => $query->whereDate('date', '<=', $to))
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        $counts = JournalEntry::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return Inertia::render('Finance/JournalEntries/Index', [
            'entries' => JournalEntryResource::collection($entries),
            'counts'  => collect(self::STATUSES)->mapWithKeys(fn ($status) => [$status => (int) ($counts[$status] ?? 0)]),
            'filters' => $filters,
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', JournalEntry::class);

        return Inertia::render('Finance/JournalEntries/Create', [
            'accounts' => $this->accountOptions(),
            'today'    => now()->toDateString(),
        ]);
    }

    public function store(StoreJournalEntryRequest $request): RedirectResponse
    {
        $this->authorize('create', JournalEntry::class);

        $entry = $this->entryService->create($request->validated());

        return redirect()->route('finance.journal-entries.show', $entry)->with('success', "Écriture {$entry->reference} créée.");
    }

    public function show(JournalEntry $journalEntry): Response
    {
        $this->authorize('view', $journalEntry);

        $journalEntry->load(['lines' => fn ($query) => $query->orderBy('sort_order'), 'lines.chartOfAccount', 'postedBy']);

        return Inertia::render('Finance/JournalEntries/Show', [
            'entry' => new JournalEntryResource($journalEntry),
        ]);
    }

    public function edit(JournalEntry $journalEntry): Response|RedirectResponse
    {
        $this->authorize('update', $journalEntry);

        if ($journalEntry->status !== 'draft') {
            return redirect()->route('finance.journal-entries.show', $journalEntry)->with('error', 'Seules les écritures en brouillon sont modifiables.');
        }

        $journalEntry->load(['lines' => fn ($query) => $query->orderBy('sort_order')]);

        return Inertia::render('Finance/JournalEntries/Edit', [
            'entry'    => new JournalEntryResource($journalEntry),
            'accounts' => $this->accountOptions(),
        ]);
    }

    public function update(UpdateJournalEntryRequest $request, JournalEntry $journalEntry): RedirectResponse
    {
        $this->authorize('update', $journalEntry);

        $this->entryService->update($journalEntry, $request->validated());

        return redirect()->route('finance.journal-entries.show', $journalEntry)->with('success', 'Écriture mise à jour.');
    }

    public function destroy(JournalEntry $journalEntry): RedirectResponse
    {
        $this->authorize('delete', $journalEntry);

        $this->entryService->delete($journalEntry);

        return redirect()->route('finance.journal-entries.index')->with('success', "Écriture {$journalEntry->reference} supprimée.");
    }

    public function post(JournalEntry $journalEntry): RedirectResponse
    {
        $this->authorize('post', $journalEntry);

        $this->entryService->post($journalEntry);

        return back()->with('success', 'Écriture comptabilisée.');
    }

    public function cancel(Request $request, JournalEntry $journalEntry): RedirectResponse
    {
        $this->authorize('cancel', $journalEntry);

        $request->validate(['reason' => ['nullable', 'string', 'max:1000']]);
        $this->entryService->cancel($journalEntry, $request->input('reason'));

        return back()->with('success', 'Écriture annulée.');
    }

    private function accountOptions(): Collection
    {
        return ChartOfAccount::where('is_active', true)->orderBy('code')->get(['id', 'code', 'name', 'type'])
            ->map(fn (ChartOfAccount $a) => ['value' => $a->id, 'label' => "{$a->code} — {$a->name}", 'type' => $a->type]);
    }
}
