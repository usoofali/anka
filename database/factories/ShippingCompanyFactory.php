<?php

namespace Database\Factories;

use App\Models\ShippingCompany;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShippingCompany>
 */
class ShippingCompanyFactory extends Factory
{
    protected $model = ShippingCompany::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'code' => strtoupper(fake()->optional()->bothify('???')),
        ];
    }
}
