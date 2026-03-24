<?php

namespace Database\Factories;

use App\Models\Shipper;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Shipper>
 */
class ShipperFactory extends Factory
{
    protected $model = Shipper::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'company_name' => fake()->optional()->company(),
            'phone' => fake()->optional()->phoneNumber(),
            'address' => fake()->optional()->address(),
        ];
    }
}
