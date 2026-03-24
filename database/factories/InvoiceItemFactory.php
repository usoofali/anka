<?php

namespace Database\Factories;

use App\Models\ChargeItem;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InvoiceItem>
 */
class InvoiceItemFactory extends Factory
{
    protected $model = InvoiceItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $qty = fake()->numberBetween(1, 5);
        $unit = fake()->randomFloat(2, 10, 500);
        $amount = round($qty * $unit, 2);

        return [
            'invoice_id' => Invoice::factory(),
            'charge_item_id' => ChargeItem::factory(),
            'description' => fake()->optional()->sentence(),
            'quantity' => $qty,
            'unit_price' => $unit,
            'amount' => $amount,
        ];
    }
}
