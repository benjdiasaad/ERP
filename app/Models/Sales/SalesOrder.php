<?php

declare(strict_types=1);

namespace App\Models\Sales;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalesOrder extends Model
{
    use BelongsToCompany, HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'customer_id',
        'reference',
        'quote_id',
        'order_date',
        'status',
        'subtotal_ht',
        'total_discount',
        'total_tax',
        'total_ttc',
        'currency_id',
        'payment_term_id',
        'notes',
        'terms_and_conditions',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'order_date'     => 'date',
            'subtotal_ht'    => 'decimal:2',
            'total_discount' => 'decimal:2',
            'total_tax'      => 'decimal:2',
            'total_ttc'      => 'decimal:2',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(SalesOrderLine::class)->orderBy('sort_order');
    }
}
