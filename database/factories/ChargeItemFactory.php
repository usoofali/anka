<?php

namespace Database\Factories;

use App\Models\ChargeItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChargeItem>
 */
class ChargeItemFactory extends Factory
{
    protected $model = ChargeItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'item' => fake()->words(2, true),
            'description' => fake()->optional()->sentence(),
        ];
    }
}
