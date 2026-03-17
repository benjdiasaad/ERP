<?php

declare(strict_types=1);

namespace App\Services\Sales;

use App\Models\Sales\Customer;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;


class CustomerService
{
    /**
     * Create a new customer record.
     */
    public function create(array $data): Customer
    {
        return DB::transaction(function () use ($data): Customer {
            if (empty($data['code'])) {
                $data['code'] = $this->generateCode();
            }

            return Customer::create($data);
        });
    }

    /**
     * Update an existing customer record.
     */
    public function update(Customer $customer, array $data): Customer
    {
        return DB::transaction(function () use ($customer, $data): Customer {
            $customer->update($data);

            return $customer->fresh(['paymentTerm', 'currency']);
        });
    }

    /**
     * Soft-delete a customer record.
     */
    public function delete(Customer $customer): bool
    {
        return (bool) $customer->delete();
    }

    /**
     * Search / filter customers with pagination.
     *
     * Supported filters: search (name/email/phone/code), type, city, is_active, per_page.
     */
    public function search(array $filters): LengthAwarePaginator
    {
        $query = Customer::query()
            ->with(['paymentTerm', 'currency']);

        if (!empty($filters['search'])) {
            $term  = mb_strtolower($filters['search']);
            $like  = \Illuminate\Support\Facades\DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $query->where(function ($q) use ($term, $like) {
                $q->where('name', $like, "%{$term}%")
                  ->orWhere('first_name', $like, "%{$term}%")
                  ->orWhere('last_name', $like, "%{$term}%")
                  ->orWhere('email', $like, "%{$term}%")
                  ->orWhere('phone', $like, "%{$term}%")
                  ->orWhere('code', $like, "%{$term}%");
            });
        }

        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (!empty($filters['city'])) {
            $like = DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $query->where('city', $like, "%{$filters['city']}%");
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', (bool) $filters['is_active']);
        }

        $perPage = $filters['per_page'] ?? 15;

        return $query->orderBy('name')->paginate($perPage);
    }

    /**
     * Add or subtract an amount from the customer's balance.
     *
     * @param  string  $operation  'add' | 'subtract'
     *
     * @throws ValidationException if the operation is invalid.
     */
    public function updateBalance(Customer $customer, float $amount, string $operation = 'add'): Customer
    {
        return DB::transaction(function () use ($customer, $amount, $operation): Customer {
            $current = (float) $customer->balance;

            $newBalance = match ($operation) {
                'add'      => $current + $amount,
                'subtract' => $current - $amount,
                default    => throw ValidationException::withMessages([
                    'operation' => "Invalid operation '{$operation}'. Use 'add' or 'subtract'.",
                ]),
            };

            $customer->update(['balance' => $newBalance]);

            return $customer->fresh();
        });
    }

    /**
     * Check whether the customer has sufficient credit for the given amount.
     *
     * A credit_limit of 0 means unlimited credit → always returns true.
     * Otherwise returns true if (balance + amount) <= credit_limit.
     */
    public function checkCredit(Customer $customer, float $amount): bool
    {
        $creditLimit = (float) $customer->credit_limit;

        if ($creditLimit == 0) {
            return true;
        }

        return ((float) $customer->balance + $amount) <= $creditLimit;
    }

    /**
     * Return a summary of the customer's credit situation.
     *
     * @return array{
     *     credit_limit: float,
     *     balance: float,
     *     available_credit: float,
     *     has_credit_limit: bool,
     *     is_over_limit: bool,
     * }
     */
    public function getCreditInfo(Customer $customer): array
    {
        $creditLimit    = (float) $customer->credit_limit;
        $balance        = (float) $customer->balance;
        $hasCreditLimit = $creditLimit > 0;
        $availableCredit = $hasCreditLimit ? max(0.0, $creditLimit - $balance) : null;
        $isOverLimit    = $hasCreditLimit && $balance > $creditLimit;

        return [
            'credit_limit'     => $creditLimit,
            'balance'          => $balance,
            'available_credit' => $availableCredit,
            'has_credit_limit' => $hasCreditLimit,
            'is_over_limit'    => $isOverLimit,
        ];
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function generateCode(): string
    {
        $year  = now()->year;
        $count = Customer::withoutGlobalScopes()
            ->whereYear('created_at', $year)
            ->count() + 1;

        return sprintf('CUST-%d-%05d', $year, $count);
    }
}
