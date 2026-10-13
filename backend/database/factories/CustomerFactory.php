<?php

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'contact_person' => fake()->name(),
            'email' => fake()->safeEmail(),
            'billing_street_address' => fake()->streetAddress(),
            'billing_postal_code' => fake()->postcode(),
            'billing_city' => fake()->city(),
            'payment_terms_days' => 14,
        ];
    }
}
