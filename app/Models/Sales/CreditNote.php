<?php

declare(strict_types=1);

namespace App\Models\Sales;

use App\Models\Auth\User;
use App\Traits\BelongsToCompany;
use App\Traits\GeneratesReference;
use App\Traits\HasAuditTrail;
use App\Traits\HasStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CreditNote extends Model
{
    use BelongsToCompany, GeneratesReference, HasAuditTrail, HasFactory, HasStatus, SoftDeletes;

    protected array $statusTransitions = [
        'draft'     => ['confirmed'],
        'confirmed' => ['applied'],
        'applied'   => [],
    ];

    protected $fillable = [
        'company_id',
        'invoice_id',
        'customer_id',
        'reference',
        'reason',
        'status',
        'date',
        'subtotal_ht',
        'total_discount',
        'total_tax',
        'total_ttc',
        'notes',
        'created_by',
        'confirmed_at',
        'applied_at',
    ];

    protected function casts(): array
    {
        return [
            'date'           => 'date',
            'subtotal_ht'    => 'decimal:2',
            'total_discount' => 'decimal:2',
            'total_tax'      => 'decimal:2',
            'total_ttc'      => 'decimal:2',
            'confirmed_at'   => 'datetime',
            'applied_at'     => 'datetime',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(CreditNoteLine::class)->orderBy('sort_order');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeByStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    public function scopeByCustomer(Builder $query, int $customerId): Builder
    {
        return $query->where('customer_id', $customerId);
    }
}
