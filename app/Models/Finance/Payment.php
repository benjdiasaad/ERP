<?php

declare(strict_types=1);

namespace App\Models\Finance;

use App\Models\Auth\User;
use App\Traits\BelongsToCompany;
use Database\Factories\Finance\PaymentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payment extends Model
{
    use BelongsToCompany, HasFactory, SoftDeletes;

    protected static function newFactory(): Factory
    {
        return PaymentFactory::new();
    }

    protected $fillable = [
        'company_id',
        'reference',
        'payable_type',
        'payable_id',
        'direction',
        'amount',
        'payment_method_id',
        'bank_account_id',
        'payment_date',
        'status',
        'notes',
        'confirmed_at',
        'confirmed_by',
    ];

    protected function casts(): array
    {
        return [
            'amount'       => 'decimal:2',
            'payment_date' => 'date',
            'confirmed_at' => 'datetime',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    /**
     * Get the payable model (Invoice, PurchaseInvoice, etc.).
     */
    public function payable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the payment method.
     */
    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    /**
     * Get the bank account.
     */
    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    /**
     * Get the user who confirmed the payment.
     */
    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    /**
     * Filter incoming payments only.
     */
    public function scopeIncoming(Builder $query): Builder
    {
        return $query->where('direction', 'incoming');
    }

    /**
     * Filter outgoing payments only.
     */
    public function scopeOutgoing(Builder $query): Builder
    {
        return $query->where('direction', 'outgoing');
    }

    /**
     * Filter confirmed payments only.
     */
    public function scopeConfirmed(Builder $query): Builder
    {
        return $query->whereNotNull('confirmed_at');
    }

    /**
     * Filter pending payments only.
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->whereNull('confirmed_at');
    }

    /**
     * Filter by status.
     */
    public function scopeByStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }
}
