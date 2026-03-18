<?php

declare(strict_types=1);

namespace App\Models\Purchasing;

use App\Traits\BelongsToCompany;
use Database\Factories\Purchasing\ReceptionNoteLineFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReceptionNoteLine extends Model
{
    use BelongsToCompany, HasFactory;

    protected static function newFactory(): Factory
    {
        return ReceptionNoteLineFactory::new();
    }

    protected $fillable = [
        'company_id',
        'reception_note_id',
        'purchase_order_line_id',
        'product_id',
        'description',
        'ordered_quantity',
        'received_quantity',
        'rejected_quantity',
        'unit',
        'notes',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'ordered_quantity'  => 'decimal:4',
            'received_quantity' => 'decimal:4',
            'rejected_quantity' => 'decimal:4',
            'sort_order'        => 'integer',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function receptionNote(): BelongsTo
    {
        return $this->belongsTo(ReceptionNote::class);
    }

    public function purchaseOrderLine(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderLine::class);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    public function acceptedQuantity(): string
    {
        return bcsub((string) $this->received_quantity, (string) $this->rejected_quantity, 4);
    }
}
