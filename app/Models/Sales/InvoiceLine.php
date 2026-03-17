<?php

declare(strict_types=1);

namespace App\Models\Sales;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Stub model — full implementation in Task 9.
 */
class InvoiceLine extends Model
{
    use BelongsToCompany, HasFactory;

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

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
