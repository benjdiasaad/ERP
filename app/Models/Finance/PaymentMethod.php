<?php

declare(strict_types=1);

namespace App\Models\Finance;

use App\Traits\BelongsToCompany;
use Database\Factories\Finance\PaymentMethodFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PaymentMethod extends Model
{
    use BelongsToCompany, HasFactory, SoftDeletes;

    protected static function newFactory(): Factory
    {
        return PaymentMethodFactory::new();
    }

    protected $fillable = [
        'company_id',
        'name',
        'type',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
