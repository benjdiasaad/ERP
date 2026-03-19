<?php

declare(strict_types=1);

namespace App\Models\Finance;

use App\Traits\BelongsToCompany;
use Database\Factories\Finance\TaxFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tax extends Model
{
    use BelongsToCompany, HasFactory, SoftDeletes;

    protected static function newFactory(): Factory
    {
        return TaxFactory::new();
    }

    protected $fillable = [
        'company_id',
        'name',
        'rate',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'rate'      => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }
}
