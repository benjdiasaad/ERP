<?php

declare(strict_types=1);

namespace App\Models\Purchasing;

use App\Models\Auth\User;
use App\Traits\BelongsToCompany;
use App\Traits\HasAuditTrail;
use Database\Factories\Purchasing\ReceptionNoteFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ReceptionNote extends Model
{
    use BelongsToCompany, HasAuditTrail, HasFactory, SoftDeletes;

    protected static function newFactory(): Factory
    {
        return ReceptionNoteFactory::new();
    }

    protected $fillable = [
        'company_id',
        'reference',
        'purchase_order_id',
        'supplier_id',
        'status',
        'reception_date',
        'notes',
        'created_by',
        'confirmed_by',
        'confirmed_at',
        'cancelled_by',
        'cancelled_at',
        'cancellation_reason',
    ];

    protected function casts(): array
    {
        return [
            'reception_date' => 'date',
            'confirmed_at'   => 'datetime',
            'cancelled_at'   => 'datetime',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(ReceptionNoteLine::class)->orderBy('sort_order');
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
