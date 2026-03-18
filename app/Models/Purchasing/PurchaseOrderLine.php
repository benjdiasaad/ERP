<?php

declare(strict_types=1);

namespace App\Models\Purchasing;

use App\Traits\BelongsToCompany;
use Database\Factories\Purchasing\PurchaseOrderLineFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderLine extends Model
{
    use BelongsToCompany, HasFactory;

    protected static function newFactory(): Factory
    {
        return PurchaseOrderLineFactory::new();
    }

    protected $fillable = [
        'company_id',
        'purchase_order_id',
        'product_id',
        'description',
        'quantity',
        'unit',
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
        'received_quantity',
        'invoiced_quantity',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'quantity'                   => 'decimal:4',
            'unit_price_ht'              => 'decimal:2',
            'discount_value'             => 'decimal:2',
            'subtotal_ht'                => 'decimal:2',
            'discount_amount'            => 'decimal:2',
            'subtotal_ht_after_discount' => 'decimal:2',
            'tax_rate'                   => 'decimal:2',
            'tax_amount'                 => 'decimal:2',
            'total_ttc'                  => 'decimal:2',
            'received_quantity'          => 'decimal:4',
            'invoiced_quantity'          => 'decimal:4',
            'sort_order'                 => 'integer',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    public function remainingToReceive(): string
    {
        return bcsub((string) $this->quantity, (string) $this->received_quantity, 4);
    }

    public function remainingToInvoice(): string
    {
        return bcsub((string) $this->quantity, (string) $this->invoiced_quantity, 4);
    }
}
