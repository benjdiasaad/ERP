<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Models\Finance\PaymentTerm;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class PaymentTermService
{
    /**
     * Create a new payment term.
     */
    public function create(array $data): PaymentTerm
    {
        return DB::transaction(function () use ($data): PaymentTerm {
            return PaymentTerm::create($data);
        });
    }

    /**
     * Update an existing payment term.
     */
    public function update(PaymentTerm $paymentTerm, array $data): PaymentTerm
    {
        return DB::transaction(function () use ($paymentTerm, $data): PaymentTerm {
            $paymentTerm->update($data);
            return $paymentTerm->fresh();
        });
    }

    /**
     * Delete a payment term (soft delete).
     */
    public function delete(PaymentTerm $paymentTerm): bool
    {
        return (bool) $paymentTerm->delete();
    }

    /**
     * Search and filter payment terms with pagination.
     */
    public function search(array $filters): LengthAwarePaginator
    {
        $query = PaymentTerm::query();

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
            $query->orderBy('days', 'asc');
        }

        return $query->paginate($filters['per_page'] ?? 15);
    }
}
