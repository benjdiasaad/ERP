<?php

declare(strict_types=1);

namespace App\Models\Sales;

use App\Models\Auth\User;
use App\Models\Finance\Currency;
use App\Models\Finance\PaymentTerm;
use App\Traits\BelongsToCompany;
use App\Traits\GeneratesReference;
use App\Traits\HasStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Quote extends Model
{
    use BelongsToCompany, GeneratesReference, HasFactory, HasStatus, SoftDeletes;

    protected array $statusTransitions = [
        'draft'     => ['sent', 'rejected'],
        'sent'      => ['accepted', 'rejected', 'expired'],
        'accepted'  => ['converted'],
        'rejected'  => [],
        'expired'   => [],
        'converted' => [],
    ];

    protected $fillable = [
        'company_id',
        'customer_id',
        'reference',
        'quote_date',
        'validity_date',
        'status',
        'subtotal_ht',
        'total_discount',
        'total_tax',
        'total_ttc',
        'currency_id',
        'payment_term_id',
        'notes',
        'terms_and_conditions',
        'converted_to_order_id',
        'sent_at',
        'accepted_at',
        'rejected_at',
        'rejection_reason',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'quote_date'    => 'date',
            'validity_date' => 'date',
            'subtotal_ht'   => 'decimal:2',
            'total_discount' => 'decimal:2',
            'total_tax'     => 'decimal:2',
            'total_ttc'     => 'decimal:2',
            'sent_at'       => 'datetime',
            'accepted_at'   => 'datetime',
            'rejected_at'   => 'datetime',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
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
        return $this->hasMany(QuoteLine::class)->orderBy('sort_order');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class, 'converted_to_order_id');
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeDraft(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('status', 'draft');
    }

    public function scopeExpired(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('status', 'sent')
            ->whereNotNull('validity_date')
            ->where('validity_date', '<', now()->toDateString());
    }
}
