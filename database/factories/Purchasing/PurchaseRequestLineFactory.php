<?php

declare(strict_types=1);

namespace Database\Factories\Purchasing;

use App\Models\Company\Company;
use App\Models\Purchasing\PurchaseRequest;
use App\Models\Purchasing\PurchaseRequestLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseRequestLine>
 */
class PurchaseRequestLineFactory extends Factory
{
    protected $model = PurchaseRequestLine::class;

    public function definition(): array
    {
        $quantity           = $this->faker->numberBetween(1, 100);
        $estimatedUnitPrice = $this->faker->randomFloat(2, 10, 1000);
        $estimatedTotal     = round($quantity * $estimatedUnitPrice, 2);

        return [
            'company_id'           => Company::factory(),
            'purchase_request_id'  => PurchaseRequest::factory(),
            'product_id'           => null,
            'description'          => $this->faker->words(4, true),
            'quantity'             => $quantity,
            'unit'                 => $this->faker->optional()->randomElement(['pcs', 'kg', 'l', 'm', 'box']),
            'estimated_unit_price' => $estimatedUnitPrice,
            'estimated_total'      => $estimatedTotal,
            'notes'                => $this->faker->optional()->sentence(),
            'sort_order'           => $this->faker->numberBetween(0, 100),
        ];
    }
}
