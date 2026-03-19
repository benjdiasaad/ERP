<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Models\Inventory\Warehouse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WarehouseService
{
    // ─── CRUD ─────────────────────────────────────────────────────────────────

    /**
     * Create a new warehouse.
     */
    public function create(array $data): Warehouse
    {
        return DB::transaction(function () use ($data): Warehouse {
            $data['company_id'] = auth()->user()->current_company_id;

            $warehouse = Warehouse::create($data);

            return $warehouse->fresh();
        });
    }

    /**
     * Update an existing warehouse.
     */
    public function update(Warehouse $warehouse, array $data): Warehouse
    {
        return DB::transaction(function () use ($warehouse, $data): Warehouse {
            $warehouse->update(Arr::except($data, ['company_id']));

            return $warehouse->fresh();
        });
    }

    /**
     * Soft-delete a warehouse.
     */
    public function delete(Warehouse $warehouse): bool
    {
        return (bool) $warehouse->delete();
    }

    /**
     * Restore a soft-deleted warehouse.
     */
    public function restore(Warehouse $warehouse): bool
    {
        return (bool) $warehouse->restore();
    }

    // ─── Queries ──────────────────────────────────────────────────────────────

    /**
     * Get all active warehouses for the current company.
     */
    public function getAllActive()
    {
        return Warehouse::active()->get();
    }

    /**
     * Get the default warehouse for the current company.
     */
    public function getDefault(): ?Warehouse
    {
        return Warehouse::where('default', true)->first();
    }

    /**
     * Set a warehouse as the default for the current company.
     */
    public function setAsDefault(Warehouse $warehouse): Warehouse
    {
        return DB::transaction(function () use ($warehouse): Warehouse {
            // Unset any existing default
            Warehouse::where('company_id', $warehouse->company_id)
                ->where('default', true)
                ->update(['default' => false]);

            // Set this warehouse as default
            $warehouse->update(['default' => true]);

            return $warehouse->fresh();
        });
    }
}
