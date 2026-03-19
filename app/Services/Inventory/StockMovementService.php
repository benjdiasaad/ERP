<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Models\Inventory\Product;
use App\Models\Inventory\StockLevel;
use App\Models\Inventory\StockMovement;
use App\Models\Inventory\Warehouse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockMovementService
{
    // ─── Stock Movement Creation ──────────────────────────────────────────────

    /**
     * Create a stock movement and update stock levels.
     *
     * Types: 'in' (increase), 'out' (decrease), 'transfer' (move between warehouses),
     *        'adjustment' (correction), 'return' (return to stock)
     *
     * @param array $data {
     *     product_id: int,
     *     warehouse_id: int,
     *     type: string,
     *     quantity: float,
     *     reference?: string,
     *     source_type?: string,
     *     source_id?: int,
     *     notes?: string,
     * }
     */
    public function createMovement(array $data): StockMovement
    {
        return DB::transaction(function () use ($data): StockMovement {
            $product = Product::findOrFail($data['product_id']);
            $warehouse = Warehouse::findOrFail($data['warehouse_id']);
            $type = $data['type'];
            $quantity = (float) $data['quantity'];

            // Validate quantity
            if ($quantity <= 0) {
                throw ValidationException::withMessages([
                    'quantity' => 'Quantity must be greater than zero.',
                ]);
            }

            // Validate type
            if (!in_array($type, ['in', 'out', 'transfer', 'adjustment', 'return'], true)) {
                throw ValidationException::withMessages([
                    'type' => "Invalid movement type: {$type}.",
                ]);
            }

            // Create the movement record
            $movement = StockMovement::create([
                'company_id'  => auth()->user()->current_company_id,
                'product_id'  => $product->id,
                'warehouse_id' => $warehouse->id,
                'type'        => $type,
                'quantity'    => $quantity,
                'reference'   => $data['reference'] ?? null,
                'source_type' => $data['source_type'] ?? null,
                'source_id'   => $data['source_id'] ?? null,
                'notes'       => $data['notes'] ?? null,
                'created_by'  => auth()->id(),
                'created_at'  => now(),
            ]);

            // Update stock levels based on movement type
            $this->updateStockLevel($product, $warehouse, $type, $quantity);

            return $movement;
        });
    }

    /**
     * Transfer stock between two warehouses.
     *
     * @param array $data {
     *     product_id: int,
     *     from_warehouse_id: int,
     *     to_warehouse_id: int,
     *     quantity: float,
     *     reference?: string,
     *     notes?: string,
     * }
     */
    public function transfer(array $data): array
    {
        return DB::transaction(function () use ($data): array {
            $product = Product::findOrFail($data['product_id']);
            $fromWarehouse = Warehouse::findOrFail($data['from_warehouse_id']);
            $toWarehouse = Warehouse::findOrFail($data['to_warehouse_id']);
            $quantity = (float) $data['quantity'];

            // Validate quantity
            if ($quantity <= 0) {
                throw ValidationException::withMessages([
                    'quantity' => 'Quantity must be greater than zero.',
                ]);
            }

            // Check if source warehouse has sufficient stock
            $sourceLevel = StockLevel::where('product_id', $product->id)
                ->where('warehouse_id', $fromWarehouse->id)
                ->first();

            if (!$sourceLevel || (float) $sourceLevel->quantity_on_hand < $quantity) {
                throw ValidationException::withMessages([
                    'quantity' => 'Insufficient stock in source warehouse.',
                ]);
            }

            // Create outgoing movement from source warehouse
            $outMovement = StockMovement::create([
                'company_id'   => auth()->user()->current_company_id,
                'product_id'   => $product->id,
                'warehouse_id' => $fromWarehouse->id,
                'type'         => 'transfer',
                'quantity'     => -$quantity,
                'reference'    => $data['reference'] ?? null,
                'source_type'  => 'transfer',
                'source_id'    => null,
                'notes'        => $data['notes'] ?? null,
                'created_by'   => auth()->id(),
                'created_at'   => now(),
            ]);

            // Create incoming movement to destination warehouse
            $inMovement = StockMovement::create([
                'company_id'   => auth()->user()->current_company_id,
                'product_id'   => $product->id,
                'warehouse_id' => $toWarehouse->id,
                'type'         => 'transfer',
                'quantity'     => $quantity,
                'reference'    => $data['reference'] ?? null,
                'source_type'  => 'transfer',
                'source_id'    => null,
                'notes'        => $data['notes'] ?? null,
                'created_by'   => auth()->id(),
                'created_at'   => now(),
            ]);

            // Update stock levels
            $this->updateStockLevel($product, $fromWarehouse, 'out', $quantity);
            $this->updateStockLevel($product, $toWarehouse, 'in', $quantity);

            return [
                'out_movement' => $outMovement,
                'in_movement'  => $inMovement,
            ];
        });
    }

    // ─── Stock Level Updates ──────────────────────────────────────────────────

    /**
     * Update stock level based on movement type.
     */
    private function updateStockLevel(Product $product, Warehouse $warehouse, string $type, float $quantity): void
    {
        $stockLevel = StockLevel::firstOrCreate(
            [
                'company_id'   => auth()->user()->current_company_id,
                'product_id'   => $product->id,
                'warehouse_id' => $warehouse->id,
            ],
            [
                'quantity_on_hand'  => 0,
                'quantity_reserved' => 0,
            ]
        );

        $currentQty = (float) $stockLevel->quantity_on_hand;

        $newQty = match ($type) {
            'in', 'return' => $currentQty + $quantity,
            'out', 'transfer' => $currentQty - $quantity,
            'adjustment' => $quantity, // Set to exact quantity
            default => $currentQty,
        };

        // Prevent negative stock (except for adjustments which can be negative)
        if ($newQty < 0 && $type !== 'adjustment') {
            throw ValidationException::withMessages([
                'quantity' => 'Operation would result in negative stock.',
            ]);
        }

        $stockLevel->update([
            'quantity_on_hand' => max(0, $newQty),
            'last_counted_at'  => now(),
        ]);
    }

    // ─── Queries ──────────────────────────────────────────────────────────────

    /**
     * Get stock movements for a product in a warehouse.
     */
    public function getMovementsForProduct(Product $product, Warehouse $warehouse)
    {
        return StockMovement::where('product_id', $product->id)
            ->where('warehouse_id', $warehouse->id)
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Get stock movements by type.
     */
    public function getMovementsByType(string $type)
    {
        return StockMovement::where('type', $type)
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Get stock movements for a date range.
     */
    public function getMovementsByDateRange(\DateTime $startDate, \DateTime $endDate)
    {
        return StockMovement::whereBetween('created_at', [$startDate, $endDate])
            ->orderByDesc('created_at')
            ->get();
    }
}
