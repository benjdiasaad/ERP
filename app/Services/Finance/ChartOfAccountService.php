<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Models\Finance\ChartOfAccount;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ChartOfAccountService
{
    /**
     * Create a new chart of account record.
     */
    public function create(array $data): ChartOfAccount
    {
        return DB::transaction(function () use ($data): ChartOfAccount {
            // Validate parent exists if provided
            if (!empty($data['parent_id'])) {
                $parent = ChartOfAccount::find($data['parent_id']);
                if (!$parent) {
                    throw ValidationException::withMessages([
                        'parent_id' => 'Parent account not found.',
                    ]);
                }
            }

            // Initialize balance to 0 if not provided
            if (!isset($data['balance'])) {
                $data['balance'] = 0;
            }

            return ChartOfAccount::create($data);
        });
    }

    /**
     * Update an existing chart of account record.
     */
    public function update(ChartOfAccount $account, array $data): ChartOfAccount
    {
        return DB::transaction(function () use ($account, $data): ChartOfAccount {
            // Validate parent exists if provided and different
            if (!empty($data['parent_id']) && $data['parent_id'] !== $account->parent_id) {
                $parent = ChartOfAccount::find($data['parent_id']);
                if (!$parent) {
                    throw ValidationException::withMessages([
                        'parent_id' => 'Parent account not found.',
                    ]);
                }

                // Prevent circular hierarchy
                if ($this->wouldCreateCircularHierarchy($account->id, $data['parent_id'])) {
                    throw ValidationException::withMessages([
                        'parent_id' => 'Cannot set parent to a descendant account.',
                    ]);
                }
            }

            $account->update($data);

            return $account->fresh(['parent', 'children']);
        });
    }

    /**
     * Soft-delete a chart of account record.
     */
    public function delete(ChartOfAccount $account): bool
    {
        return (bool) $account->delete();
    }

    /**
     * Search / filter chart of accounts with pagination.
     *
     * Supported filters: search (code/name), type, is_active, per_page.
     */
    public function search(array $filters): LengthAwarePaginator
    {
        $query = ChartOfAccount::query()
            ->with(['parent', 'children']);

        if (!empty($filters['search'])) {
            $term = mb_strtolower($filters['search']);
            $like = DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $query->where(function ($q) use ($term, $like) {
                $q->where('code', $like, "%{$term}%")
                  ->orWhere('name', $like, "%{$term}%");
            });
        }

        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', (bool) $filters['is_active']);
        }

        $perPage = $filters['per_page'] ?? 15;

        return $query->orderBy('code')->paginate($perPage);
    }

    /**
     * Get the full hierarchical tree of accounts.
     *
     * @return Collection<ChartOfAccount>
     */
    public function getTree(): Collection
    {
        return ChartOfAccount::query()
            ->whereNull('parent_id')
            ->with('children')
            ->orderBy('code')
            ->get();
    }

    /**
     * Get all accounts of a specific type.
     *
     * @return Collection<ChartOfAccount>
     */
    public function getByType(string $type): Collection
    {
        return ChartOfAccount::query()
            ->forType($type)
            ->orderBy('code')
            ->get();
    }

    /**
     * Update the balance of an account.
     *
     * @param  string  $operation  'add' | 'subtract' | 'set'
     *
     * @throws ValidationException if the operation is invalid.
     */
    public function updateBalance(ChartOfAccount $account, float $amount, string $operation = 'add'): ChartOfAccount
    {
        return DB::transaction(function () use ($account, $amount, $operation): ChartOfAccount {
            $current = (float) $account->balance;

            $newBalance = match ($operation) {
                'add'      => $current + $amount,
                'subtract' => $current - $amount,
                'set'      => $amount,
                default    => throw ValidationException::withMessages([
                    'operation' => "Invalid operation '{$operation}'. Use 'add', 'subtract', or 'set'.",
                ]),
            };

            $account->update(['balance' => $newBalance]);

            return $account->fresh();
        });
    }

    /**
     * Calculate the total balance of an account including all descendants.
     *
     * This is useful for parent accounts to see the total balance of their hierarchy.
     */
    public function calculateHierarchyBalance(ChartOfAccount $account): float
    {
        $balance = (float) $account->balance;

        foreach ($account->children as $child) {
            $balance += $this->calculateHierarchyBalance($child);
        }

        return $balance;
    }

    /**
     * Get all descendant accounts of a given account.
     *
     * @return Collection<ChartOfAccount>
     */
    public function getDescendants(ChartOfAccount $account): Collection
    {
        $descendants = collect();

        foreach ($account->children as $child) {
            $descendants->push($child);
            $descendants = $descendants->merge($this->getDescendants($child));
        }

        return $descendants;
    }

    /**
     * Get all ancestor accounts of a given account.
     *
     * @return Collection<ChartOfAccount>
     */
    public function getAncestors(ChartOfAccount $account): Collection
    {
        $ancestors = collect();

        if ($account->parent) {
            $ancestors->push($account->parent);
            $ancestors = $ancestors->merge($this->getAncestors($account->parent));
        }

        return $ancestors;
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Check if setting a parent would create a circular hierarchy.
     */
    private function wouldCreateCircularHierarchy(int $accountId, int $parentId): bool
    {
        $parent = ChartOfAccount::find($parentId);

        if (!$parent) {
            return false;
        }

        if ($parent->id === $accountId) {
            return true;
        }

        if ($parent->parent_id === null) {
            return false;
        }

        return $this->wouldCreateCircularHierarchy($accountId, $parent->parent_id);
    }
}
