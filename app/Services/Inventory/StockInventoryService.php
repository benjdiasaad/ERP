<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Models\Inventory\Product;
use App\Models\Inventory\StockInventory;
use App\Models\Inventory\StockInventoryLine;
use App\Models\Inventory\StockLevel;
use App\Models\Inventory\Warehouse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockInventoryService
{
    public function __construct(
        private readonly StockMovementService $stockMovementService,
    ) {}

    // ─── CRUD ─────────────────────────────────────────────────────────────────

    /**
     * Create a new stock inventory (physical count sheet).
     */
    public function create(array $data): StockInventory
    {
        return DB::transaction(function () use ($data): StockInventory {
            $warehouse = Warehouse::findOrFail($data['warehouse_id']);

            $inventory = StockInventory::create([
                'company_id'   => auth()->user()->current_company_id,
                'warehouse_id' => $warehouse->id,
                'reference'    => $data['reference'] ?? null,
                'status'       => 'draft',
                'notes'        => $data['notes'] ?? null,
            ]);

            // Add lines if provided
            if (!empty($data['lines'])) {
                foreach ($data['lines'] as $lineData) {
                    $this->addLine($inventory, $lineData);
                }
            }

            return $inventory->fresh(['lines']);
        });
    }

    /**
     * Update a stock inventory (only allowed in draft status).
     */
    public function update(StockInventory $inventory, array $data): StockInventory
    {
        if ($inventory->status !== 'draft') {
            throw ValidationException::withMessages([
                'status' => "Only draft inventories can be updated. Current status: {$inventory->status}.",
            ]);
        }

        return DB::transaction(function () use ($inventory, $data): StockInventory {
            $inventory->update(Arr::except($data, ['lines']));

            if (array_key_exists('lines', $data)) {
                $existingIds = $inventory->lines()->pluck('id')->toArray();
                $incomingIds = [];

                foreach ($data['lines'] as $lineData) {
                    if (!empty($lineData['id'])) {
                        $line = $inventory->lines()->find($lineData['id']);
                        if ($line) {
                            $line->update(Arr::except($lineData, ['id']));
                            $incomingIds[] = $lineData['id'];
                        }
                    } else {
                        $newLine = $this->addLine($inventory, $lineData);
                        $incomingIds[] = $newLine->id;
                    }
                }

                $toDelete = array_diff($existingIds, $incomingIds);
                if (!empty($toDelete)) {
                    $inventory->lines()->whereIn('id', $toDelete)->delete();
                }
            }

            return $inventory->fresh(['lines']);
        });
    }

    /**
     * Soft-delete a stock inventory (only allowed in draft status).
     */
    public function delete(StockInventory $inventory): bool
    {
        if ($inventory->status !== 'draft') {
            throw ValidationException::withMessages([
                'status' => "Only draft inventories can be deleted. Current status: {$inventory->status}.",
            ]);
        }

        return (bool) $inventory->delete();
    }

    /**
     * Restore a soft-deleted stock inventory.
     */
    public function restore(StockInventory $inventory): bool
    {
        return (bool) $inventory->restore();
    }

    // ─── Line Management ──────────────────────────────────────────────────────

    /**
     * Add a line to a stock inventory.
     */
    private function addLine(StockInventory $inventory, array $lineData): StockInventoryLine
    {
        $product = Product::findOrFail($lineData['product_id']);

        // Get theoretical quantity from current stock level
        $stockLevel = StockLevel::where('product_id', $product->id)
            ->where('warehouse_id', $inventory->warehouse_id)
            ->first();

        $theoreticalQty = $stockLevel?->quantity_on_hand ?? 0;

        return $inventory->lines()->create([
            'product_id'           => $product->id,
            'warehouse_id'         => $inventory->warehouse_id,
            'theoretical_quantity' => $theoreticalQty,
            'counted_quantity'     => $lineData['counted_quantity'] ?? 0,
            'notes'                => $lineData['notes'] ?? null,
        ]);
    }

    // ─── Lifecycle ────────────────────────────────────────────────────────────

    /**
     * Start a physical count (transition from draft to in_progress).
     */
    public function start(StockInventory $inventory): StockInventory
    {
        if ($inventory->status !== 'draft') {
            throw ValidationException::withMessages([
                'status' => "Only draft inventories can be started. Current status: {$inventory->status}.",
            ]);
        }

        if ($inventory->lines()->count() === 0) {
            throw ValidationException::withMessages([
                'lines' => 'Inventory must have at least one line before starting.',
            ]);
        }

        $inventory->update([
            'status'     => 'in_progress',
            'counted_at' => now(),
        ]);

        return $inventory->fresh(['lines']);
    }

    /**
     * Validate and finalize a physical count.
     * Generates adjustment movements for any variances.
     */
    public function validate(StockInventory $inventory): StockInventory
    {
        if ($inventory->status !== 'in_progress') {
            throw ValidationException::withMessages([
                'status' => "Only in_progress inventories can be validated. Current status: {$inventory->status}.",
            ]);
        }

        return DB::transaction(function () use ($inventory): StockInventory {
            $inventory->loadMissing('lines');

            // Generate adjustment movements for each line with variance
            foreach ($inventory->lines as $line) {
                $variance = (float) $line->getVariance();

                if ($variance !== 0.0) {
                    // Create adjustment movement
                    $this->stockMovementService->createMovement([
                        'product_id'   => $line->product_id,
                        'warehouse_id' => $line->warehouse_id,
                        'type'         => 'adjustment',
                        'quantity'     => (float) $line->counted_quantity,
                        'reference'    => $inventory->reference,
                        'source_type'  => StockInventory::class,
                        'source_id'    => $inventory->id,
                        'notes'        => "Physical inventory adjustment: {$line->notes}",
                    ]);
                }
            }

            // Mark as validated
            $inventory->update([
                'status'       => 'validated',
                'validated_at' => now(),
                'validated_by' => auth()->id(),
            ]);

            return $inventory->fresh(['lines']);
        });
    }

    /**
     * Cancel a stock inventory (only allowed in draft or in_progress status).
     */
    public function cancel(StockInventory $inventory, ?string $reason = null): StockInventory
    {
        if (!in_array($inventory->status, ['draft', 'in_progress'], true)) {
            throw ValidationException::withMessages([
                'status' => "Inventories with status '{$inventory->status}' cannot be cancelled.",
            ]);
        }

        $inventory->update([
            'status' => 'cancelled',
            'notes'  => $reason ? ($inventory->notes ? $inventory->notes . "\n\nCancellation reason: {$reason}" : "Cancellation reason: {$reason}") : $inventory->notes,
        ]);

        return $inventory->fresh(['lines']);
    }

    // ─── Queries ──────────────────────────────────────────────────────────────

    /**
     * Get all inventories for a warehouse.
     */
    public function getByWarehouse(Warehouse $warehouse)
    {
        return StockInventory::where('warehouse_id', $warehouse->id)
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Get inventories by status.
     */
    public function getByStatus(string $status)
    {
        return StockInventory::where('status', $status)
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Get the total variance for an inventory.
     */
    public function getTotalVariance(StockInventory $inventory): string
    {
        $inventory->loadMissing('lines');

        $totalVariance = 0;
        foreach ($inventory->lines as $line) {
            $totalVariance += (float) $line->getVariance();
        }

        return (string) $totalVariance;
    }

    /**
     * Get variance summary (count of lines with variance).
     */
    public function getVarianceSummary(StockInventory $inventory): array
    {
        $inventory->loadMissing('lines');

        $withVariance = 0;
        $withoutVariance = 0;

        foreach ($inventory->lines as $line) {
            if ((float) $line->getVariance() !== 0.0) {
                $withVariance++;
            } else {
                $withoutVariance++;
            }
        }

        return [
            'total_lines'      => $inventory->lines()->count(),
            'with_variance'    => $withVariance,
            'without_variance' => $withoutVariance,
        ];
    }
}
