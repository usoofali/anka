<?php

namespace Database\Factories;

use App\Models\City;
use App\Models\Port;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Port>
 */
class PortFactory extends Factory
{
    protected $model = Port::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $city = City::factory()->create();
        $state = $city->state;
        $country = $state->country;

        return [
            'country_id' => $country->id,
            'state_id' => $state->id,
            'city_id' => $city->id,
            'name' => fake()->words(2, true).' Port',
            'code' => strtoupper(fake()->optional()->bothify('???')),
        ];
    }
}
