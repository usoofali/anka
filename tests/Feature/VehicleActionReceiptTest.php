<?php

declare(strict_types=1);

use App\Models\Vehicle;

test('vehicle action_receipt path can be persisted and updated', function () {
    $vehicle = Vehicle::factory()->withoutShipment()->create([
        'action_receipt' => 'documents/vehicles/initial-receipt.pdf',
    ]);

    expect($vehicle->action_receipt)->toBe('documents/vehicles/initial-receipt.pdf');

    $vehicle->update([
        'action_receipt' => 'documents/vehicles/updated-receipt.pdf',
    ]);

    expect($vehicle->fresh()->action_receipt)->toBe('documents/vehicles/updated-receipt.pdf');
});

test('vehicle action_receipt allows null value', function () {
    $vehicle = Vehicle::factory()->withoutShipment()->create([
        'action_receipt' => null,
    ]);

    expect($vehicle->action_receipt)->toBeNull();
});
