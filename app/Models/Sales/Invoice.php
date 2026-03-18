<?php

declare(strict_types=1);

namespace App\Models\Sales;

use App\Models\Auth\User;
use App\Models\Finance\Currency;
use App\Models\Finance\PaymentTerm;
use App\Traits\BelongsToCompany;
use App\Traits\GeneratesReference;
use App\Traits\HasAuditTrail;
use App\Traits\HasStatus;
use Database\Factories\Sales\InvoiceFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends Model
{
    use BelongsToCompany, GeneratesReference, HasAuditTrail, HasFactory, HasStatus, SoftDeletes;

    protected static function newFactory(): Factory
    {
        return InvoiceFactory::new();
    }

    protected array $statusTransitions = [
        'draft'     => ['sent', 'cancelled'],
        'sent'      => ['partial', 'paid', 'overdue', 'cancelled'],
        'partial'   => ['paid', 'overdue', 'cancelled'],
        'overdue'   => ['partial', 'paid', 'cancelled'],
        'paid'      => [],
        'cancelled' => [],
    ];

    protected $fillable = [
        'company_id',
        'sales_order_id',
        'customer_id',
        'reference',
        'status',
        'invoice_date',
        'due_date',
        'payment_term_id',
        'currency_id',
        'subtotal_ht',
        'total_discount',
        'total_tax',
        'total_ttc',
        'amount_paid',
        'amount_due',
        'notes',
        'terms',
        'created_by',
        'updated_by',
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

    // ─── Relationships ────────────────────────────────────────────────────────

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
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
        return $this->hasMany(InvoiceLine::class)->orderBy('sort_order');
    }

    public function creditNotes(): HasMany
    {
        return $this->hasMany(CreditNote::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->whereIn('status', ['sent', 'partial'])
            ->whereNotNull('due_date')
            ->where('due_date', '<', now()->toDateString());
    }

    public function scopeByStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    public function isFullyPaid(): bool
    {
        return bccomp((string) $this->amount_due, '0.00', 2) <= 0;
    }

    public function isOverdue(): bool
    {
        return $this->due_date
            && $this->due_date->isPast()
            && in_array($this->status, ['sent', 'partial']);
    }
}
