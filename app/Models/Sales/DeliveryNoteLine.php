<?php

declare(strict_types=1);

namespace App\Models\Sales;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Stub model — full implementation in Task 10.
 */
class DeliveryNoteLine extends Model
{
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'company_id',
        'delivery_note_id',
        'sales_order_line_id',
        'product_id',
        'description',
        'ordered_quantity',
        'delivered_quantity',
        'sort_order',
    ];

    public function deliveryNote(): BelongsTo
    {
        return $this->belongsTo(DeliveryNote::class);
    }

    public function salesOrderLine(): BelongsTo
    {
        return $this->belongsTo(SalesOrderLine::class);
    }
}
