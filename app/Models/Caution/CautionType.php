<?php

namespace App\Models\Caution;

use App\Traits\BelongsToCompany;
use Database\Factories\CautionTypeFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CautionType extends Model
{
    use BelongsToCompany, HasFactory;

    protected static function newFactory(): Factory
    {
        return CautionTypeFactory::new();
    }

    protected $fillable = [
        'company_id',
        'name',
        'description',
        'default_percentage',
    ];

    protected $casts = [
        'default_percentage' => 'decimal:2',
    ];

    public function cautions(): HasMany
    {
        return $this->hasMany(Caution::class);
    }
}
