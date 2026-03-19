<?php

declare(strict_types=1);

namespace App\Models\Inventory;

use App\Traits\BelongsToCompany;
use App\Traits\HasAuditTrail;
use Database\Factories\Inventory\ProductFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use BelongsToCompany, HasAuditTrail, HasFactory, SoftDeletes;

    protected static function newFactory(): Factory
    {
        return ProductFactory::new();
    }

    protected $fillable = [
        'company_id',
        'category_id',
        'code',
        'name',
        'description',
        'type',
        'unit',
        'purchase_price',
        'sale_price',
        'tax_rate',
        'barcode',
        'image_path',
        'min_stock_level',
        'max_stock_level',
        'reorder_point',
        'is_active',
        'is_purchasable',
        'is_sellable',
        'is_stockable',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'purchase_price'  => 'decimal:2',
            'sale_price'      => 'decimal:2',
            'tax_rate'        => 'decimal:2',
            'min_stock_level' => 'decimal:4',
            'max_stock_level' => 'decimal:4',
            'reorder_point'   => 'decimal:4',
            'is_active'       => 'boolean',
            'is_purchasable'  => 'boolean',
            'is_sellable'     => 'boolean',
            'is_stockable'    => 'boolean',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeSellable(Builder $query): Builder
    {
        return $query->where('is_sellable', true);
    }

    public function scopePurchasable(Builder $query): Builder
    {
        return $query->where('is_purchasable', true);
    }

    public function scopeStockable(Builder $query): Builder
    {
        return $query->where('is_stockable', true);
    }

    /**
     * Placeholder scope for low stock — returns products where min_stock_level > 0.
     * Full implementation requires StockLevel table (Task 17).
     */
    public function scopeLowStock(Builder $query): Builder
    {
        return $query->where('is_stockable', true)->where('min_stock_level', '>', 0);
    }

    /**
     * Check if this product is considered low stock (placeholder until Task 17).
     */
    public function isLowStock(): bool
    {
        return $this->is_stockable && (float) $this->min_stock_level > 0;
    }
}
