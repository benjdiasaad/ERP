<?php

declare(strict_types=1);

namespace App\Services\Purchasing;

use App\Models\Purchasing\Supplier;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SupplierService
{
    /**
     * List / filter suppliers with pagination.
     *
     * Supported filters: search (string), is_active (bool), paginate (int, default 15).
     */
    public function list(array $filters): LengthAwarePaginator
    {
        $query = Supplier::query()->with(['paymentTerm']);

        if (!empty($filters['search'])) {
            $term = mb_strtolower($filters['search']);
            $like = DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $query->where(function ($q) use ($term, $like) {
                $q->where('name', $like, "%{$term}%")
                  ->orWhere('code', $like, "%{$term}%")
                  ->orWhere('email', $like, "%{$term}%")
                  ->orWhere('phone', $like, "%{$term}%")
                  ->orWhere('tax_id', $like, "%{$term}%")
                  ->orWhere('ice', $like, "%{$term}%");
            });
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', (bool) $filters['is_active']);
        }

        $perPage = $filters['paginate'] ?? 15;

        return $query->orderBy('name')->paginate($perPage);
    }

    /**
     * Create a new supplier record.
     */
    public function create(array $data): Supplier
    {
        return DB::transaction(function () use ($data): Supplier {
            if (empty($data['code'])) {
                $data['code'] = $this->generateCode();
            }

            return Supplier::create($data);
        });
    }

    /**
     * Update an existing supplier record.
     */
    public function update(Supplier $supplier, array $data): Supplier
    {
        return DB::transaction(function () use ($supplier, $data): Supplier {
            $supplier->update($data);

            return $supplier->fresh(['paymentTerm']);
        });
    }

    /**
     * Soft-delete a supplier record.
     */
    public function delete(Supplier $supplier): void
    {
        $supplier->delete();
    }

    /**
     * Search suppliers by a term (name, code, email, phone, tax_id, ice).
     */
    public function search(string $term): Collection
    {
        $like = DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';

        return Supplier::query()
            ->where(function ($q) use ($term, $like) {
                $q->where('name', $like, "%{$term}%")
                  ->orWhere('code', $like, "%{$term}%")
                  ->orWhere('email', $like, "%{$term}%")
                  ->orWhere('phone', $like, "%{$term}%")
                  ->orWhere('tax_id', $like, "%{$term}%")
                  ->orWhere('ice', $like, "%{$term}%");
            })
            ->orderBy('name')
            ->get();
    }

    /**
     * Increment or decrement the supplier's balance.
     *
     * Pass a positive amount to add, negative to subtract.
     */
    public function updateBalance(Supplier $supplier, float $amount): Supplier
    {
        return DB::transaction(function () use ($supplier, $amount): Supplier {
            $newBalance = (float) $supplier->balance + $amount;

            $supplier->update(['balance' => $newBalance]);

            return $supplier->fresh();
        });
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function generateCode(): string
    {
        // The BelongsToCompany global scope ensures this count is per-company.
        $count = Supplier::withTrashed()->count() + 1;

        return sprintf('SUPP-%05d', $count);
    }
}
