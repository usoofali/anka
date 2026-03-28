<?php

declare(strict_types=1);

use App\Models\Vehicle;

test('vehicle auction_receipt path can be persisted and updated', function () {
    $vehicle = Vehicle::factory()->withoutShipment()->create([
        'auction_receipt' => 'documents/vehicles/initial-receipt.pdf',
    ]);

    expect($vehicle->auction_receipt)->toBe('documents/vehicles/initial-receipt.pdf');

    $vehicle->update([
        'auction_receipt' => 'documents/vehicles/updated-receipt.pdf',
    ]);

    expect($vehicle->fresh()->auction_receipt)->toBe('documents/vehicles/updated-receipt.pdf');
});

test('vehicle auction_receipt allows null value', function () {
    $vehicle = Vehicle::factory()->withoutShipment()->create([
        'auction_receipt' => null,
    ]);

    expect($vehicle->auction_receipt)->toBeNull();
});
