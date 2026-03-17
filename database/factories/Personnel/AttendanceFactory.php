<?php

declare(strict_types=1);

namespace Database\Factories\Personnel;

use App\Models\Company\Company;
use App\Models\Personnel\Attendance;
use App\Models\Personnel\Personnel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Attendance>
 */
class AttendanceFactory extends Factory
{
    protected $model = Attendance::class;

    public function definition(): array
    {
        $checkIn      = $this->faker->time('H:i:s', '09:30:00');
        $checkOut     = $this->faker->time('H:i:s', '18:30:00');
        $totalHours   = round($this->faker->numberBetween(6, 10) + $this->faker->randomFloat(1, 0, 0.9), 2);
        $overtimeHours = $totalHours > 8 ? round($totalHours - 8, 2) : 0;

        return [
            'company_id'     => Company::factory(),
            'personnel_id'   => Personnel::factory(),
            'date'           => $this->faker->dateTimeBetween('-3 months', 'now'),
            'check_in'       => $checkIn,
            'check_out'      => $checkOut,
            'total_hours'    => $totalHours,
            'overtime_hours' => $overtimeHours,
            'status'         => 'present',
            'notes'          => $this->faker->optional()->sentence(),
            'created_by'     => null,
        ];
    }

    public function absent(): static
    {
        return $this->state(fn (array $attributes) => [
            'status'         => 'absent',
            'check_in'       => null,
            'check_out'      => null,
            'total_hours'    => 0,
            'overtime_hours' => 0,
        ]);
    }

    public function remote(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'remote',
        ]);
    }
}
