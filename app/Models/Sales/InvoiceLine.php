<?php

declare(strict_types=1);

namespace App\Models\Sales;

use App\Models\Finance\Tax;
use App\Models\Inventory\Product;
use App\Traits\BelongsToCompany;
use Database\Factories\Sales\InvoiceLineFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceLine extends Model
{
    use BelongsToCompany, HasFactory;

    protected static function newFactory(): Factory
    {
        return InvoiceLineFactory::new();
    }

    protected $fillable = [
        'company_id',
        'invoice_id',
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
            'sort_order'                 => 'integer',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function tax(): BelongsTo
    {
        return $this->belongsTo(Tax::class);
    }
}
