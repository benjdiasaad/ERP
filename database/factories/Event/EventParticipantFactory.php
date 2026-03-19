<?php

namespace Database\Factories\Event;

use App\Models\Auth\User;
use App\Models\Company\Company;
use App\Models\Event\Event;
use App\Models\Event\EventParticipant;
use App\Models\Personnel\Personnel;
use Illuminate\Database\Eloquent\Factories\Factory;

class EventParticipantFactory extends Factory
{
    protected $model = EventParticipant::class;

    public function definition(): array
    {
        $isInternal = $this->faker->boolean(70);

        if ($isInternal) {
            $useUser = $this->faker->boolean();
            return [
                'company_id'   => Company::factory(),
                'event_id'     => Event::factory(),
                'user_id'      => $useUser ? User::factory() : null,
                'personnel_id' => !$useUser ? Personnel::factory() : null,
                'name'         => null,
                'email'        => null,
                'role'         => $this->faker->randomElement(['organizer', 'speaker', 'attendee', 'guest']),
                'rsvp_status'  => $this->faker->randomElement(['pending', 'confirmed', 'declined']),
            ];
        }

        return [
            'company_id'   => Company::factory(),
            'event_id'     => Event::factory(),
            'user_id'      => null,
            'personnel_id' => null,
            'name'         => $this->faker->name(),
            'email'        => $this->faker->email(),
            'role'         => $this->faker->randomElement(['organizer', 'speaker', 'attendee', 'guest']),
            'rsvp_status'  => $this->faker->randomElement(['pending', 'confirmed', 'declined']),
        ];
    }

    public function internal(): self
    {
        return $this->state(fn(array $attributes) => [
            'user_id'      => User::factory(),
            'personnel_id' => null,
            'name'         => null,
            'email'        => null,
        ]);
    }

    public function external(): self
    {
        return $this->state(fn(array $attributes) => [
            'user_id'      => null,
            'personnel_id' => null,
            'name'         => $this->faker->name(),
            'email'        => $this->faker->email(),
        ]);
    }

    public function organizer(): self
    {
        return $this->state(fn(array $attributes) => [
            'role' => 'organizer',
        ]);
    }

    public function speaker(): self
    {
        return $this->state(fn(array $attributes) => [
            'role' => 'speaker',
        ]);
    }

    public function attendee(): self
    {
        return $this->state(fn(array $attributes) => [
            'role' => 'attendee',
        ]);
    }

    public function confirmed(): self
    {
        return $this->state(fn(array $attributes) => [
            'rsvp_status' => 'confirmed',
        ]);
    }

    public function declined(): self
    {
        return $this->state(fn(array $attributes) => [
            'rsvp_status' => 'declined',
        ]);
    }

    public function pending(): self
    {
        return $this->state(fn(array $attributes) => [
            'rsvp_status' => 'pending',
        ]);
    }
}
