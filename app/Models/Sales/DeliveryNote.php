<?php

declare(strict_types=1);

namespace App\Models\Sales;

use App\Traits\BelongsToCompany;
use App\Traits\GeneratesReference;
use App\Traits\HasAuditTrail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Stub model — full implementation in Task 10.
 */
class DeliveryNote extends Model
{
    use BelongsToCompany, GeneratesReference, HasAuditTrail, HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'reference',
        'sales_order_id',
        'customer_id',
        'status',
        'delivery_date',
        'delivery_address',
        'carrier',
        'tracking_number',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'delivery_date' => 'date',
        ];
    }

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(DeliveryNoteLine::class)->orderBy('sort_order');
    }
}
