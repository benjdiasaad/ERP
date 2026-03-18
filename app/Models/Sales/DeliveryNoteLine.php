<?php

declare(strict_types=1);

namespace App\Models\Sales;

use App\Models\Inventory\Product;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryNoteLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'delivery_note_id',
        'sales_order_line_id',
        'product_id',
        'description',
        'ordered_quantity',
        'shipped_quantity',
        'returned_quantity',
        'unit',
        'sort_order',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'ordered_quantity'  => 'decimal:2',
            'shipped_quantity'  => 'decimal:2',
            'returned_quantity' => 'decimal:2',
            'sort_order'        => 'integer',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function deliveryNote(): BelongsTo
    {
        return $this->belongsTo(DeliveryNote::class);
    }

    public function salesOrderLine(): BelongsTo
    {
        return $this->belongsTo(SalesOrderLine::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    public function remainingQuantity(): string
    {
        return bcsub((string) $this->ordered_quantity, (string) $this->shipped_quantity, 2);
    }
}
