<?php

namespace Database\Factories;

use App\Enums\RequestItemType;
use App\Models\RequestItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RequestItem>
 */
class RequestItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => RequestItemType::Work,
            'description' => 'Asennustyö',
            'quantity' => 1,
            'unit' => 'h',
            'unit_price_cents' => 6500,
            'vat_rate_basis_points' => 2550,
        ];
    }
}
