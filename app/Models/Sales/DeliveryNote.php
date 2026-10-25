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
use App\Models\Inventory\Warehouse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class DeliveryNote extends Model
{
    use BelongsToCompany, GeneratesReference, HasAuditTrail, HasFactory, HasStatus, SoftDeletes;

    protected array $statusTransitions = [
        'draft'     => ['ready', 'cancelled'],
        'ready'     => ['shipped', 'cancelled'],
        'shipped'   => ['delivered', 'returned'],
        'delivered' => ['returned'],
        'returned'  => [],
        'cancelled' => [],
    ];

    protected $fillable = [
        'company_id',
        'reference',
        'sales_order_id',
        'customer_id',
        'warehouse_id',
        'status',
        'date',
        'expected_delivery_date',
        'shipped_at',
        'delivered_at',
        'delivery_address',
        'carrier',
        'tracking_number',
        'notes',
        'created_by',
        'shipped_by',
        'delivered_by',
    ];

    protected function casts(): array
    {
        return [
            'date'                   => 'date',
            'expected_delivery_date' => 'date',
            'shipped_at'             => 'date',
            'delivered_at'           => 'date',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

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

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function shippedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'shipped_by');
    }

    public function deliveredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delivered_by');
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

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }
}
