<?php

declare(strict_types=1);

namespace App\Models\Purchasing;

use App\Models\Auth\User;
use App\Models\Finance\Currency;
use App\Models\Finance\PaymentTerm;
use App\Traits\BelongsToCompany;
use App\Traits\HasAuditTrail;
use Database\Factories\Purchasing\PurchaseOrderFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseOrder extends Model
{
    use BelongsToCompany, HasAuditTrail, HasFactory, SoftDeletes;

    protected static function newFactory(): Factory
    {
        return PurchaseOrderFactory::new();
    }

    protected $fillable = [
        'company_id',
        'reference',
        'supplier_id',
        'purchase_request_id',
        'status',
        'order_date',
        'expected_delivery_date',
        'delivery_address',
        'payment_term_id',
        'currency_id',
        'subtotal_ht',
        'total_discount',
        'total_tax',
        'total_ttc',
        'amount_received',
        'amount_invoiced',
        'notes',
        'terms_conditions',
        'created_by',
        'confirmed_by',
        'confirmed_at',
        'sent_at',
        'cancelled_by',
        'cancelled_at',
        'cancellation_reason',
    ];

    protected function casts(): array
    {
        return [
            'order_date'             => 'date',
            'expected_delivery_date' => 'date',
            'subtotal_ht'            => 'decimal:2',
            'total_discount'         => 'decimal:2',
            'total_tax'              => 'decimal:2',
            'total_ttc'              => 'decimal:2',
            'amount_received'        => 'decimal:2',
            'amount_invoiced'        => 'decimal:2',
            'confirmed_at'           => 'datetime',
            'sent_at'                => 'datetime',
            'cancelled_at'           => 'datetime',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function purchaseRequest(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function paymentTerm(): BelongsTo
    {
        return $this->belongsTo(PaymentTerm::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseOrderLine::class)->orderBy('sort_order');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeByStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }
}
