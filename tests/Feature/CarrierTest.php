<?php

declare(strict_types=1);

use App\Models\Carrier;
use App\Models\DefaultShipmentSetting;
use App\Models\Shipment;

it('persists a carrier with name and description', function () {
    $carrier = Carrier::query()->create([
        'name' => 'Sallaum Lines',
        'description' => 'Ocean carrier specializing in RoRo cargo.',
    ]);

    expect($carrier->refresh())
        ->name->toBe('Sallaum Lines')
        ->description->toBe('Ocean carrier specializing in RoRo cargo.');
});

it('links shipments to a carrier', function () {
    $shipment = Shipment::factory()->create();

    expect($shipment->carrier)->toBeInstanceOf(Carrier::class)
        ->and($shipment->carrier_id)->toBe($shipment->carrier->id);

    expect($shipment->carrier->shipments()->whereKey($shipment->id)->exists())->toBeTrue();
});

it('stores a default ocean carrier on default shipment settings', function () {
    $carrier = Carrier::factory()->create();
    $defaults = DefaultShipmentSetting::current();
    $defaults->update(['carrier_id' => $carrier->id]);

    expect($defaults->refresh()->carrier)
        ->toBeInstanceOf(Carrier::class)
        ->id->toBe($carrier->id);
});
