<?php

declare(strict_types=1);

namespace App\Models\Sales;

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

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }
}
