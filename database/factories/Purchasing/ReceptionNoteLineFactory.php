<?php

declare(strict_types=1);

namespace Database\Factories\Purchasing;

use App\Models\Company\Company;
use App\Models\Purchasing\ReceptionNote;
use App\Models\Purchasing\ReceptionNoteLine;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReceptionNoteLineFactory extends Factory
{
    protected $model = ReceptionNoteLine::class;

    public function definition(): array
    {
        $ordered  = $this->faker->randomFloat(2, 1, 100);
        $received = $this->faker->randomFloat(2, 0, $ordered);

        return [
            'company_id'        => Company::factory(),
            'reception_note_id' => ReceptionNote::factory(),
            'description'       => $this->faker->words(3, true),
            'ordered_quantity'  => $ordered,
            'received_quantity' => $received,
            'rejected_quantity' => 0,
            'sort_order'        => 0,
        ];
    }
}
