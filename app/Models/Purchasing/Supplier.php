<?php

declare(strict_types=1);

namespace App\Models\Purchasing;

use App\Models\Finance\PaymentTerm;
use App\Traits\BelongsToCompany;
use Database\Factories\Purchasing\SupplierFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends Model
{
    use BelongsToCompany, HasFactory, SoftDeletes;

    protected static function newFactory(): Factory
    {
        return SupplierFactory::new();
    }

    protected $fillable = [
        'company_id',
        'code',
        'name',
        'type',
        'email',
        'phone',
        'mobile',
        'address',
        'city',
        'state',
        'country',
        'postal_code',
        'tax_id',
        'ice',
        'rc',
        'payment_term_id',
        'credit_limit',
        'balance',
        'bank_name',
        'bank_account_number',
        'bank_iban',
        'bank_swift',
        'notes',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active'    => 'boolean',
            'credit_limit' => 'decimal:2',
            'balance'      => 'decimal:2',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function paymentTerm(): BelongsTo
    {
        return $this->belongsTo(PaymentTerm::class, 'payment_term_id');
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeSearch(Builder $query, string $term): Builder
    {
        return $query->where(function (Builder $q) use ($term): void {
            $q->where('name', 'like', "%{$term}%")
              ->orWhere('code', 'like', "%{$term}%")
              ->orWhere('email', 'like', "%{$term}%")
              ->orWhere('phone', 'like', "%{$term}%")
              ->orWhere('tax_id', 'like', "%{$term}%")
              ->orWhere('ice', 'like', "%{$term}%");
        });
    }
}
