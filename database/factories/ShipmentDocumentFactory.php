<?php

namespace Database\Factories;

use App\Models\DocumentType;
use App\Models\Shipment;
use App\Models\ShipmentDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShipmentDocument>
 */
class ShipmentDocumentFactory extends Factory
{
    protected $model = ShipmentDocument::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'shipment_id' => Shipment::factory(),
            'document_type_id' => DocumentType::factory(),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
