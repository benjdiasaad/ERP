<?php

declare(strict_types=1);

namespace App\Models\Inventory;

use Database\Factories\Inventory\StockInventoryLineFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockInventoryLine extends Model
{
    use HasFactory;

    protected static function newFactory(): Factory
    {
        return StockInventoryLineFactory::new();
    }

    protected $fillable = [
        'stock_inventory_id',
        'product_id',
        'warehouse_id',
        'theoretical_quantity',
        'counted_quantity',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'theoretical_quantity' => 'decimal:4',
            'counted_quantity'     => 'decimal:4',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function stockInventory(): BelongsTo
    {
        return $this->belongsTo(StockInventory::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    // ─── Methods ───────────────────────────────────────────────────────────────

    /**
     * Get the variance (counted_quantity - theoretical_quantity).
     */
    public function getVariance(): string
    {
        return (string) bcsub(
            (string) $this->counted_quantity,
            (string) $this->theoretical_quantity,
            4
        );
    }
}
