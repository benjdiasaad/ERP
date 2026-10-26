<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\StoreChartOfAccountRequest;
use App\Http\Requests\Finance\UpdateChartOfAccountRequest;
use App\Http\Resources\Finance\ChartOfAccountResource;
use App\Models\Finance\ChartOfAccount;
use App\Services\Finance\ChartOfAccountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The chart of accounts is managed from a single page (create / edit in a dialog).
 */
class ChartOfAccountController extends Controller
{
    public function __construct(
        private readonly ChartOfAccountService $accountService,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', ChartOfAccount::class);

        $filters = $request->only(['search', 'type']);

        $accounts = ChartOfAccount::query()
            ->with('parent:id,code,name')
            ->when($filters['search'] ?? null, function ($query, $search) {
                $like = '%' . $search . '%';
                $query->where(fn ($query) => $query->whereLike('code', $like)->orWhereLike('name', $like));
            })
            ->when($filters['type'] ?? null, fn ($query, $type) => $query->where('type', $type))
            ->orderBy('code')
            ->get();

        return Inertia::render('Finance/Accounts/Index', [
            'accounts' => ChartOfAccountResource::collection($accounts),
            'parents'  => ChartOfAccount::orderBy('code')->get(['id', 'code', 'name'])
                ->map(fn (ChartOfAccount $a) => ['value' => $a->id, 'label' => "{$a->code} — {$a->name}"]),
            'filters'  => $filters,
        ]);
    }

    public function store(StoreChartOfAccountRequest $request): RedirectResponse
    {
        $this->authorize('create', ChartOfAccount::class);

        $account = $this->accountService->create($request->validated());

        return back()->with('success', "Compte {$account->code} créé.");
    }

    public function update(UpdateChartOfAccountRequest $request, ChartOfAccount $account): RedirectResponse
    {
        $this->authorize('update', $account);

        $this->accountService->update($account, $request->validated());

        return back()->with('success', 'Compte mis à jour.');
    }

    public function destroy(ChartOfAccount $account): RedirectResponse
    {
        $this->authorize('delete', $account);

        $this->accountService->delete($account);

        return back()->with('success', 'Compte supprimé.');
    }
}
