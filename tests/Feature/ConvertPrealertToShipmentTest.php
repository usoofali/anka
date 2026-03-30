<?php

declare(strict_types=1);

use App\Actions\Prealerts\ConvertPrealertToShipment;
use App\Enums\LogisticsService;
use App\Enums\ShippingMode;
use App\Models\Carrier;
use App\Models\Consignee;
use App\Models\Port;
use App\Models\Prealert;
use App\Models\Shipment;
use App\Models\Shipper;

use App\Models\Vehicle;
use App\Support\VinNormalizer;

it('converts a prealert into a shipment and deletes the prealert', function () {
    $shipper = Shipper::factory()->create();
    $defaultConsignee = Consignee::factory()->for($shipper)->create(['is_default' => true]);
    $manualConsignee = Consignee::factory()->for($shipper)->create(['is_default' => false]);
    $vehicle = Vehicle::factory()->withoutShipment()->create([
        'vin' => VinNormalizer::normalize('2T1BURHE7FC251274'),
    ]);

    $prealertCarrier = Carrier::factory()->create();
    $inputCarrier = Carrier::factory()->create();
    $origin = Port::factory()->create();
    $prealertDestination = Port::factory()->create();
    $inputDestination = Port::factory()->create();
    $prealert = Prealert::factory()->for($shipper)->withVehicle($vehicle)->create([
        'gatepass_pin' => 'PIN12345678',
        'carrier_id' => $prealertCarrier->id,
        'destination_port_id' => $prealertDestination->id,
    ]);

    $shipment = app(ConvertPrealertToShipment::class)->execute($prealert, [
        'consignee_id' => $manualConsignee->id,
        'driver_id' => null,

        'carrier_id' => $inputCarrier->id,
        'origin_port_id' => $origin->id,
        'destination_port_id' => $inputDestination->id,
        'logistics_service' => LogisticsService::Ocean->value,
        'shipping_mode' => ShippingMode::Roro->value,
    ]);

    expect(Prealert::query()->find($prealert->id))->toBeNull()
        ->and($shipment->shipper_id)->toBe($shipper->id)
        ->and($shipment->consignee_id)->toBe($defaultConsignee->id)
        ->and($shipment->gatepass_pin)->toBe('PIN12345678')
        ->and($shipment->carrier_id)->toBe($prealertCarrier->id)
        ->and($shipment->destination_port_id)->toBe($prealertDestination->id)
        ->and($shipment->reference_no)->not->toBeEmpty();

    $shipment->refresh();
    expect($shipment->vehicle_id)->toBe($vehicle->id);
});

it('requires a default consignee during conversion', function () {
    $shipper = Shipper::factory()->create();
    $vehicle = Vehicle::factory()->withoutShipment()->create();
    $prealert = Prealert::factory()->for($shipper)->withVehicle($vehicle)->create();

    expect(fn () => app(ConvertPrealertToShipment::class)->execute($prealert, [
        'driver_id' => null,

        'origin_port_id' => Port::factory()->create()->id,
        'logistics_service' => LogisticsService::Ocean->value,
        'shipping_mode' => ShippingMode::Roro->value,
    ]))->toThrow(InvalidArgumentException::class);
});

it('rejects conversion when the vehicle is already assigned', function () {
    $shipper = Shipper::factory()->create();
    $existing = Shipment::factory()->create(['shipper_id' => $shipper->id]);
    $vehicle = Vehicle::factory()->create();
    $existing->update(['vehicle_id' => $vehicle->id]);
    $prealert = Prealert::factory()->for($shipper)->withVehicle($vehicle)->create();

    expect(fn () => app(ConvertPrealertToShipment::class)->execute($prealert, [
        'consignee_id' => Consignee::factory()->for($shipper)->create()->id,
        'driver_id' => null,

        'carrier_id' => Carrier::factory()->create()->id,
        'origin_port_id' => Port::factory()->create()->id,
        'destination_port_id' => Port::factory()->create()->id,
        'logistics_service' => LogisticsService::Ocean->value,
        'shipping_mode' => ShippingMode::Roro->value,
    ]))->toThrow(InvalidArgumentException::class);
});
