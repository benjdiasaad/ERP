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
 * Stub model — full implementation in Task 9.
 */
class Invoice extends Model
{
    use BelongsToCompany, GeneratesReference, HasAuditTrail, HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'reference',
        'sales_order_id',
        'customer_id',
        'status',
        'invoice_date',
        'due_date',
        'currency_id',
        'payment_term_id',
        'subtotal_ht',
        'total_discount',
        'total_tax',
        'total_ttc',
        'amount_paid',
        'amount_due',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'invoice_date'   => 'date',
            'due_date'       => 'date',
            'subtotal_ht'    => 'decimal:2',
            'total_discount' => 'decimal:2',
            'total_tax'      => 'decimal:2',
            'total_ttc'      => 'decimal:2',
            'amount_paid'    => 'decimal:2',
            'amount_due'     => 'decimal:2',
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
        return $this->hasMany(InvoiceLine::class)->orderBy('sort_order');
    }
}
