<?php

declare(strict_types=1);

namespace App\Models\Finance;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PaymentTerm extends Model
{
    use BelongsToCompany, SoftDeletes;

    protected $fillable = [
        'company_id',
        'name',
        'days',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'days'      => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
