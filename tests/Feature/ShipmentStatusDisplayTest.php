<?php

declare(strict_types=1);

use App\Enums\ShipmentStatus;
use App\Models\Shipment;
use App\Models\Workshop;

it('uses workshop name in shipmentStatusDisplay when at workshop', function (): void {
    $workshop = Workshop::factory()->create(['name' => 'North Bay Customs']);
    $shipment = Shipment::factory()->create([
        'shipment_status' => ShipmentStatus::AtWorkshop,
        'workshop_id' => $workshop->id,
    ]);

    expect($shipment->shipmentStatusDisplay())->toBe('North Bay Customs');
});

it('falls back to enum case name when at workshop without a linked workshop', function (): void {
    $shipment = Shipment::factory()->create([
        'shipment_status' => ShipmentStatus::AtWorkshop,
        'workshop_id' => null,
    ]);

    expect($shipment->shipmentStatusDisplay())->toBe('AtWorkshop');
});

it('uses enum case name for other statuses', function (): void {
    $shipment = Shipment::factory()->create([
        'shipment_status' => ShipmentStatus::Inland,
    ]);

    expect($shipment->shipmentStatusDisplay())->toBe('Inland');
});
