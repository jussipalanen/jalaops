<?php

namespace Database\Factories;

use App\Enums\RequestPriority;
use App\Enums\RequestStatus;
use App\Models\ServiceRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceRequest>
 */
class ServiceRequestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'priority' => fake()->randomElement(RequestPriority::cases()),
            'status' => fake()->randomElement(RequestStatus::cases()),
            'due_date' => fake()->dateTimeBetween('-2 weeks', '+6 weeks'),
        ];
    }

    /**
     * A request with an address and coordinates somewhere in Finland.
     */
    public function withLocation(): static
    {
        return $this->state(fn () => [
            'street_address' => fake()->streetAddress(),
            'postal_code' => fake()->postcode(),
            'city' => fake()->city(),
            'latitude' => fake()->randomFloat(7, 60.0, 68.0),
            'longitude' => fake()->randomFloat(7, 21.5, 29.5),
        ]);
    }
}
