<?php

declare(strict_types=1);

namespace App\Models\Inventory;

use App\Traits\BelongsToCompany;
use Database\Factories\Inventory\StockLevelFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockLevel extends Model
{
    use BelongsToCompany, HasFactory;

    protected static function newFactory(): Factory
    {
        return StockLevelFactory::new();
    }

    protected $fillable = [
        'company_id',
        'product_id',
        'warehouse_id',
        'quantity_on_hand',
        'quantity_reserved',
        'last_counted_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity_on_hand'  => 'decimal:4',
            'quantity_reserved' => 'decimal:4',
            'last_counted_at'   => 'datetime',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

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
     * Get the available quantity (on_hand - reserved).
     */
    public function getAvailableQuantity(): string
    {
        return (string) bcsub(
            (string) $this->quantity_on_hand,
            (string) $this->quantity_reserved,
            4
        );
    }
}
