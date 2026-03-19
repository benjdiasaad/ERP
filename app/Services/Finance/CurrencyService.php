<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Models\Finance\Currency;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CurrencyService
{
    /**
     * Create a new currency.
     */
    public function create(array $data): Currency
    {
        return DB::transaction(function () use ($data): Currency {
            return Currency::create($data);
        });
    }

    /**
     * Update an existing currency.
     */
    public function update(Currency $currency, array $data): Currency
    {
        return DB::transaction(function () use ($currency, $data): Currency {
            $currency->update($data);
            return $currency->fresh();
        });
    }

    /**
     * Delete a currency (soft delete).
     */
    public function delete(Currency $currency): bool
    {
        return (bool) $currency->delete();
    }

    /**
     * Search and filter currencies with pagination.
     */
    public function search(array $filters): \Illuminate\Pagination\LengthAwarePaginator
    {
        $query = Currency::query();

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%")
                  ->orWhere('symbol', 'like', "%{$search}%");
            });
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', (bool) $filters['is_active']);
        }

        if (!empty($filters['sort'])) {
            $sort = $filters['sort'];
            $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
            $column = ltrim($sort, '-');
            $query->orderBy($column, $direction);
        } else {
            $query->orderBy('code', 'asc');
        }

        return $query->paginate($filters['per_page'] ?? 15);
    }
}
