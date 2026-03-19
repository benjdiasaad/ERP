<?php

namespace App\Models\Caution;

use App\Traits\BelongsToCompany;
use Database\Factories\CautionFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Caution extends Model
{
    use BelongsToCompany, HasFactory, SoftDeletes;

    protected static function newFactory(): Factory
    {
        return CautionFactory::new();
    }

    protected $fillable = [
        'company_id',
        'caution_type_id',
        'direction',
        'partner_type',
        'partner_id',
        'related_type',
        'related_id',
        'amount',
        'currency',
        'issue_date',
        'expiry_date',
        'return_date',
        'amount_returned',
        'amount_forfeited',
        'bank_name',
        'bank_account',
        'bank_reference',
        'document_reference',
        'status',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'amount_returned' => 'decimal:2',
        'amount_forfeited' => 'decimal:2',
        'issue_date' => 'date',
        'expiry_date' => 'date',
        'return_date' => 'date',
    ];

    public function cautionType(): BelongsTo
    {
        return $this->belongsTo(CautionType::class);
    }

    public function partner(): MorphTo
    {
        return $this->morphTo();
    }

    public function related(): MorphTo
    {
        return $this->morphTo();
    }

    public function histories(): HasMany
    {
        return $this->hasMany(CautionHistory::class);
    }
}
