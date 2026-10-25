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
            $data['code'] = $data['code'] ?? $this->generateCode();

            $warehouse = Warehouse::create($data);
            $this->keepSingleDefault($warehouse);

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
            $this->keepSingleDefault($warehouse);

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
        return Warehouse::where('is_default', true)->first();
    }

    /**
     * Set a warehouse as the default for the current company.
     */
    public function setAsDefault(Warehouse $warehouse): Warehouse
    {
        return DB::transaction(function () use ($warehouse): Warehouse {
            // Unset any existing default
            Warehouse::where('company_id', $warehouse->company_id)
                ->where('is_default', true)
                ->update(['is_default' => false]);

            // Set this warehouse as default
            $warehouse->update(['is_default' => true]);

            return $warehouse->fresh();
        });
    }

    /**
     * Only one default warehouse per company: the one just saved as default wins.
     */
    private function keepSingleDefault(Warehouse $warehouse): void
    {
        if ($warehouse->is_default) {
            Warehouse::whereKeyNot($warehouse->id)->where('is_default', true)->update(['is_default' => false]);
        }
    }

    /**
     * Next WH-NNNNN code for the current company (soft-deleted included, so codes are never reused).
     */
    private function generateCode(): string
    {
        $last = Warehouse::withTrashed()->where('code', 'like', 'WH-%')->orderByDesc('code')->value('code');
        $next = $last && preg_match('/^WH-(\d+)$/', $last, $m) ? ((int) $m[1]) + 1 : 1;

        return sprintf('WH-%05d', $next);
    }
}
