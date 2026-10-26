<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\StoreBankAccountRequest;
use App\Http\Requests\Finance\UpdateBankAccountRequest;
use App\Http\Resources\Finance\BankAccountResource;
use App\Models\Finance\BankAccount;
use App\Models\Finance\Currency;
use App\Services\Finance\BankAccountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class BankAccountController extends Controller
{
    public function __construct(
        private readonly BankAccountService $bankAccountService,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', BankAccount::class);

        $filters = $request->only(['search', 'status']);

        $accounts = BankAccount::query()
            ->with('currency')
            ->when($filters['search'] ?? null, function ($query, $search) {
                $like = '%' . $search . '%';
                $query->where(fn ($query) => $query
                    ->whereLike('name', $like)->orWhereLike('bank', $like)
                    ->orWhereLike('account_number', $like)->orWhereLike('iban', $like));
            })
            ->when(($filters['status'] ?? null) === 'active', fn ($query) => $query->where('is_active', true))
            ->when(($filters['status'] ?? null) === 'inactive', fn ($query) => $query->where('is_active', false))
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        // Balances of active accounts, per currency.
        $totals = BankAccount::where('is_active', true)->with('currency')->get()
            ->groupBy(fn (BankAccount $a) => $a->currency?->code ?? '—')
            ->map(fn ($group, $code) => ['currency' => $code, 'balance' => round((float) $group->sum('balance'), 2), 'count' => $group->count()])
            ->values();

        return Inertia::render('Finance/BankAccounts/Index', [
            'accounts' => BankAccountResource::collection($accounts),
            'totals'   => $totals,
            'filters'  => $filters,
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', BankAccount::class);

        return Inertia::render('Finance/BankAccounts/Create', [
            'currencies'      => $this->currencyOptions(),
            'defaultCurrency' => $this->currencyOptions()->firstWhere('label', 'MAD')['value'] ?? null,
        ]);
    }

    public function store(StoreBankAccountRequest $request): RedirectResponse
    {
        $this->authorize('create', BankAccount::class);

        $account = $this->bankAccountService->create($request->validated());

        return redirect()->route('finance.bank-accounts.show', $account)->with('success', "Compte « {$account->name} » créé.");
    }

    public function show(BankAccount $bankAccount): Response
    {
        $this->authorize('view', $bankAccount);

        return Inertia::render('Finance/BankAccounts/Show', [
            'account' => new BankAccountResource($bankAccount->load('currency')),
        ]);
    }

    public function edit(BankAccount $bankAccount): Response
    {
        $this->authorize('update', $bankAccount);

        return Inertia::render('Finance/BankAccounts/Edit', [
            'account'    => new BankAccountResource($bankAccount->load('currency')),
            'currencies' => $this->currencyOptions(),
        ]);
    }

    public function update(UpdateBankAccountRequest $request, BankAccount $bankAccount): RedirectResponse
    {
        $this->authorize('update', $bankAccount);

        // The balance moves with payments: it is only set when the account is created.
        $this->bankAccountService->update($bankAccount, collect($request->validated())->except('balance')->all());

        return redirect()->route('finance.bank-accounts.show', $bankAccount)->with('success', 'Compte mis à jour.');
    }

    public function destroy(BankAccount $bankAccount): RedirectResponse
    {
        $this->authorize('delete', $bankAccount);

        $this->bankAccountService->delete($bankAccount);

        return redirect()->route('finance.bank-accounts.index')->with('success', 'Compte supprimé.');
    }

    /** The company's currencies and the shared ones. */
    private function currencyOptions(): Collection
    {
        return Currency::withoutGlobalScope('company')
            ->where(fn ($query) => $query->where('company_id', auth()->user()->current_company_id)->orWhereNull('company_id'))
            ->orderBy('code')->get(['id', 'code', 'name'])
            ->unique('code')->values()
            ->map(fn (Currency $c) => ['value' => $c->id, 'label' => $c->code]);
    }
}
