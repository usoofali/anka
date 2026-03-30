<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\LogisticsService;
use App\Enums\ShipmentStatus;
use App\Enums\ShippingMode;
use App\Models\DefaultShipmentSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DefaultShipmentSetting>
 */
class DefaultShipmentSettingFactory extends Factory
{
    protected $model = DefaultShipmentSetting::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'carrier_id' => null,
            'origin_port_id' => null,
            'destination_port_id' => null,
            'logistics_service' => null,
            'shipping_mode' => null,
            'status' => null,
            'notes' => null,
        ];
    }

    public function withRoroOceanDraft(): static
    {
        return $this->state(fn (): array => [
            'logistics_service' => LogisticsService::Ocean,
            'shipping_mode' => ShippingMode::Roro,
            'status' => ShipmentStatus::Draft,
        ]);
    }
}
