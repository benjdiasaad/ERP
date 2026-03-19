<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Models\Finance\PaymentMethod;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class PaymentMethodService
{
    /**
     * Create a new payment method.
     */
    public function create(array $data): PaymentMethod
    {
        return DB::transaction(function () use ($data): PaymentMethod {
            return PaymentMethod::create($data);
        });
    }

    /**
     * Update an existing payment method.
     */
    public function update(PaymentMethod $paymentMethod, array $data): PaymentMethod
    {
        return DB::transaction(function () use ($paymentMethod, $data): PaymentMethod {
            $paymentMethod->update($data);
            return $paymentMethod->fresh();
        });
    }

    /**
     * Delete a payment method (soft delete).
     */
    public function delete(PaymentMethod $paymentMethod): bool
    {
        return (bool) $paymentMethod->delete();
    }

    /**
     * Search and filter payment methods with pagination.
     */
    public function search(array $filters): LengthAwarePaginator
    {
        $query = PaymentMethod::query();

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('type', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
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
            $query->orderBy('name', 'asc');
        }

        return $query->paginate($filters['per_page'] ?? 15);
    }
}
