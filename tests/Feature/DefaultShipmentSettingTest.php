<?php

declare(strict_types=1);

use App\Actions\Shipments\MergeShipmentDefaults;
use App\Enums\LogisticsService;
use App\Enums\ShipmentStatus;
use App\Enums\ShippingMode;
use App\Models\DefaultShipmentSetting;

it('returns a single row from current', function () {
    expect(DefaultShipmentSetting::query()->count())->toBe(0);

    $first = DefaultShipmentSetting::current();
    $second = DefaultShipmentSetting::current();

    expect($first->is($second))->toBeTrue();
    expect(DefaultShipmentSetting::query()->count())->toBe(1);
});

it('merge uses default shipping_mode when input omits it', function () {
    DefaultShipmentSetting::factory()->withRoroOceanDraft()->create();

    $merged = MergeShipmentDefaults::merge([
        'shipper_id' => 1,
        'consignee_id' => 2,
    ]);

    expect($merged['shipping_mode'])->toBe(ShippingMode::Roro->value)
        ->and($merged['logistics_service'])->toBe(LogisticsService::Ocean->value)
        ->and($merged['status'])->toBe(ShipmentStatus::Draft->value);
});

it('merge lets explicit input override defaults', function () {
    DefaultShipmentSetting::factory()->withRoroOceanDraft()->create();

    $merged = MergeShipmentDefaults::merge([
        'shipper_id' => 10,
        'shipping_mode' => ShippingMode::Container,
    ]);

    expect($merged['shipping_mode'])->toBe(ShippingMode::Container->value)
        ->and($merged['logistics_service'])->toBe(LogisticsService::Ocean->value);
});

it('merge never pulls shipper_id consignee_id driver_id from defaults', function () {
    $defaults = DefaultShipmentSetting::factory()->create([
        'notes' => 'From defaults row only',
    ]);

    $merged = MergeShipmentDefaults::merge([], $defaults);

    expect($merged)->not->toHaveKey('shipper_id')
        ->not->toHaveKey('consignee_id')
        ->not->toHaveKey('driver_id')
        ->and($merged['notes'] ?? null)->toBe('From defaults row only');
});

it('merge falls back to draft when status missing in input and defaults', function () {
    DefaultShipmentSetting::factory()->create([
        'logistics_service' => LogisticsService::Ocean,
        'shipping_mode' => ShippingMode::Roro,
        'status' => null,
    ]);

    $merged = MergeShipmentDefaults::merge([
        'shipper_id' => 1,
        'consignee_id' => 1,
    ]);

    expect($merged['status'])->toBe(ShipmentStatus::Draft->value);
});

it('merge passes through reference_no and driver_id from input', function () {
    $merged = MergeShipmentDefaults::merge([
        'reference_no' => 'REF-UNIT',
        'driver_id' => null,
        'shipper_id' => 5,
    ]);

    expect($merged['reference_no'])->toBe('REF-UNIT')
        ->and($merged['driver_id'])->toBeNull()
        ->and($merged['shipper_id'])->toBe(5);
});
