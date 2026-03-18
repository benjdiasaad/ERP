<?php

declare(strict_types=1);

namespace App\Models\Purchasing;

use App\Models\Inventory\Product;
use App\Traits\BelongsToCompany;
use Database\Factories\Purchasing\PurchaseRequestLineFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseRequestLine extends Model
{
    use BelongsToCompany, HasFactory;

    protected static function newFactory(): Factory
    {
        return PurchaseRequestLineFactory::new();
    }

    protected $fillable = [
        'company_id',
        'purchase_request_id',
        'product_id',
        'description',
        'quantity',
        'unit',
        'estimated_unit_price',
        'estimated_total',
        'notes',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'quantity'             => 'decimal:4',
            'estimated_unit_price' => 'decimal:2',
            'estimated_total'      => 'decimal:2',
            'sort_order'           => 'integer',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function purchaseRequest(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
