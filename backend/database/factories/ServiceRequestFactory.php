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
}
