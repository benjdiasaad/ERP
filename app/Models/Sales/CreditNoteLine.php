<?php

declare(strict_types=1);

namespace App\Models\Sales;

use App\Models\Finance\Tax;
use App\Models\Inventory\Product;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreditNoteLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'credit_note_id',
        'product_id',
        'description',
        'quantity',
        'unit_price_ht',
        'discount_type',
        'discount_value',
        'tax_rate',
        'subtotal_ht',
        'tax_amount',
        'total_ttc',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'quantity'       => 'decimal:2',
            'unit_price_ht'  => 'decimal:2',
            'discount_value' => 'decimal:2',
            'tax_rate'       => 'decimal:2',
            'subtotal_ht'    => 'decimal:2',
            'tax_amount'     => 'decimal:2',
            'total_ttc'      => 'decimal:2',
            'sort_order'     => 'integer',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function creditNote(): BelongsTo
    {
        return $this->belongsTo(CreditNote::class);
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
