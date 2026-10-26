<?php

namespace App\Models\Caution;

use App\Models\Auth\User;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CautionHistory extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'caution_id',
        'action',
        'amount',
        'previous_status',
        'new_status',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function caution(): BelongsTo
    {
        return $this->belongsTo(Caution::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
