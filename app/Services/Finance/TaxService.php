<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Models\Finance\Tax;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class TaxService
{
    /**
     * Create a new tax.
     */
    public function create(array $data): Tax
    {
        return DB::transaction(function () use ($data): Tax {
            return Tax::create($data);
        });
    }

    /**
     * Update an existing tax.
     */
    public function update(Tax $tax, array $data): Tax
    {
        return DB::transaction(function () use ($tax, $data): Tax {
            $tax->update($data);
            return $tax->fresh();
        });
    }

    /**
     * Delete a tax (soft delete).
     */
    public function delete(Tax $tax): bool
    {
        return (bool) $tax->delete();
    }

    /**
     * Search and filter taxes with pagination.
     */
    public function search(array $filters): LengthAwarePaginator
    {
        $query = Tax::query();

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
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
            $query->orderBy('rate', 'desc');
        }

        return $query->paginate($filters['per_page'] ?? 15);
    }
}
