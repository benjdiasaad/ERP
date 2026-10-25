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
            'variance'             => 'decimal:2',
            'variance_percentage'  => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        // Stored variance is derived from the two quantities.
        static::saving(function (self $line): void {
            $theoretical = (float) $line->theoretical_quantity;
            $variance = (float) $line->counted_quantity - $theoretical;
            $line->variance = round($variance, 2);
            $line->variance_percentage = $theoretical != 0.0 ? round(max(-999999, min(999999, $variance / $theoretical * 100)), 2) : 0;
        });
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
