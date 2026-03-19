<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Models\Finance\BankAccount;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BankAccountService
{
    /**
     * Create a new bank account record.
     */
    public function create(array $data): BankAccount
    {
        return DB::transaction(function () use ($data): BankAccount {
            // Initialize balance to 0 if not provided
            if (!isset($data['balance'])) {
                $data['balance'] = 0;
            }

            return BankAccount::create($data);
        });
    }

    /**
     * Update an existing bank account record.
     */
    public function update(BankAccount $account, array $data): BankAccount
    {
        return DB::transaction(function () use ($account, $data): BankAccount {
            $account->update($data);

            return $account->fresh(['currency']);
        });
    }

    /**
     * Soft-delete a bank account record.
     */
    public function delete(BankAccount $account): bool
    {
        return (bool) $account->delete();
    }

    /**
     * Search / filter bank accounts with pagination.
     *
     * Supported filters: search (name/bank/account_number/iban), currency_id, is_active, per_page.
     */
    public function search(array $filters): LengthAwarePaginator
    {
        $query = BankAccount::query()
            ->with(['currency']);

        if (!empty($filters['search'])) {
            $term = mb_strtolower($filters['search']);
            $like = DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $query->where(function ($q) use ($term, $like) {
                $q->where('name', $like, "%{$term}%")
                  ->orWhere('bank', $like, "%{$term}%")
                  ->orWhere('account_number', $like, "%{$term}%")
                  ->orWhere('iban', $like, "%{$term}%");
            });
        }

        if (!empty($filters['currency_id'])) {
            $query->where('currency_id', $filters['currency_id']);
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', (bool) $filters['is_active']);
        }

        $perPage = $filters['per_page'] ?? 15;

        return $query->orderBy('name')->paginate($perPage);
    }

    /**
     * Add or subtract an amount from the bank account's balance.
     *
     * @param  string  $operation  'add' | 'subtract'
     *
     * @throws ValidationException if the operation is invalid.
     */
    public function updateBalance(BankAccount $account, float $amount, string $operation = 'add'): BankAccount
    {
        return DB::transaction(function () use ($account, $amount, $operation): BankAccount {
            $current = (float) $account->balance;

            $newBalance = match ($operation) {
                'add'      => $current + $amount,
                'subtract' => $current - $amount,
                default    => throw ValidationException::withMessages([
                    'operation' => "Invalid operation '{$operation}'. Use 'add' or 'subtract'.",
                ]),
            };

            $account->update(['balance' => $newBalance]);

            return $account->fresh();
        });
    }

    /**
     * Set the balance of a bank account to a specific amount.
     *
     * Useful for reconciliation or initial balance setup.
     */
    public function setBalance(BankAccount $account, float $amount): BankAccount
    {
        return DB::transaction(function () use ($account, $amount): BankAccount {
            $account->update(['balance' => $amount]);

            return $account->fresh();
        });
    }

    /**
     * Get the total balance across all active bank accounts for the current company.
     */
    public function getTotalBalance(): float
    {
        return (float) BankAccount::query()
            ->active()
            ->sum('balance');
    }

    /**
     * Get the total balance across all active bank accounts for a specific currency.
     */
    public function getTotalBalanceByCurrency(int $currencyId): float
    {
        return (float) BankAccount::query()
            ->active()
            ->where('currency_id', $currencyId)
            ->sum('balance');
    }

    /**
     * Get all active bank accounts.
     */
    public function getActive()
    {
        return BankAccount::query()
            ->active()
            ->with(['currency'])
            ->orderBy('name')
            ->get();
    }

    /**
     * Get a summary of all bank accounts grouped by currency.
     *
     * @return array<int, array{currency_code: string, total_balance: float, account_count: int}>
     */
    public function getSummaryByCurrency(): array
    {
        $accounts = BankAccount::query()
            ->active()
            ->with(['currency'])
            ->get()
            ->groupBy('currency_id');

        $summary = [];

        foreach ($accounts as $currencyId => $currencyAccounts) {
            $currency = $currencyAccounts->first()->currency;
            $summary[] = [
                'currency_id'   => $currencyId,
                'currency_code' => $currency->code,
                'total_balance' => (float) $currencyAccounts->sum('balance'),
                'account_count' => $currencyAccounts->count(),
            ];
        }

        return $summary;
    }
}
