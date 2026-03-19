<?php

declare(strict_types=1);

namespace App\Models\Finance;

use App\Traits\BelongsToCompany;
use Database\Factories\Finance\PaymentTermFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PaymentTerm extends Model
{
    use BelongsToCompany, HasFactory, SoftDeletes;

    protected static function newFactory(): Factory
    {
        return PaymentTermFactory::new();
    }

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
