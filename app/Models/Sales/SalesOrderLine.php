<?php

declare(strict_types=1);

namespace App\Models\Sales;

use App\Models\Finance\Tax;
use App\Models\Inventory\Product;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesOrderLine extends Model
{
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'company_id',
        'sales_order_id',
        'product_id',
        'description',
        'quantity',
        'unit_price_ht',
        'discount_type',
        'discount_value',
        'subtotal_ht',
        'discount_amount',
        'subtotal_ht_after_discount',
        'tax_id',
        'tax_rate',
        'tax_amount',
        'total_ttc',
        'delivered_quantity',
        'invoiced_quantity',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'unit_price_ht' =>'decimal:2',
            'discount_value' => 'decimal:2',
            'subtotal_ht' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'subtotal_ht_after_discount' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total_ttc'=> 'decimal:2',
            'delivered_quantity' => 'decimal:4',
            'invoiced_quantity' => 'decimal:4',
            'sort_order' => 'integer',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function tax(): BelongsTo
    {
        return $this->belongsTo(Tax::class);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    public function remainingToDeliver(): string
    {
        return bcsub((string) $this->quantity, (string) $this->delivered_quantity, 4);
    }

    public function remainingToInvoice(): string
    {
        return bcsub((string) $this->quantity, (string) $this->invoiced_quantity, 4);
    }
}
