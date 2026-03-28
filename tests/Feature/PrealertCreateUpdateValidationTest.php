<?php

declare(strict_types=1);

use App\Actions\Prealerts\CreatePrealert;
use App\Actions\Prealerts\UpdatePrealert;
use App\Models\Carrier;
use App\Models\Port;
use App\Models\Prealert;
use App\Models\Shipment;
use App\Models\Shipper;
use Illuminate\Validation\ValidationException;

it('creates prealert with gatepass pin carrier and destination port', function () {
    $shipper = Shipper::factory()->create();
    $carrier = Carrier::factory()->create();
    $destination = Port::factory()->create();

    $prealert = app(CreatePrealert::class)->execute([
        'shipper_id' => $shipper->id,
        'vin' => '2t1burhe7fc251274',
        'gatepass_pin' => 'AB12CD34EF5',
        'carrier_id' => $carrier->id,
        'destination_port_id' => $destination->id,
        'action_receipt' => 'documents/prealerts/AR-1001.pdf',
    ]);

    expect($prealert->shipper_id)->toBe($shipper->id)
        ->and($prealert->vin)->toBe('2T1BURHE7FC251274')
        ->and($prealert->gatepass_pin)->toBe('AB12CD34EF5')
        ->and($prealert->carrier_id)->toBe($carrier->id)
        ->and($prealert->destination_port_id)->toBe($destination->id)
        ->and($prealert->action_receipt)->toBe('documents/prealerts/AR-1001.pdf');
});

it('allows duplicate gatepass_pin values in prealerts', function () {
    $shipper = Shipper::factory()->create();

    $first = app(CreatePrealert::class)->execute([
        'shipper_id' => $shipper->id,
        'vin' => '2T1BURHE7FC251274',
        'gatepass_pin' => 'DUPLICATE11',
    ]);

    $second = app(CreatePrealert::class)->execute([
        'shipper_id' => $shipper->id,
        'vin' => '2T1BURHE7FC251275',
        'gatepass_pin' => 'DUPLICATE11',
    ]);

    expect($first->gatepass_pin)->toBe('DUPLICATE11')
        ->and($second->gatepass_pin)->toBe('DUPLICATE11');
});

it('rejects gatepass_pin longer than 11 characters', function () {
    $shipper = Shipper::factory()->create();

    expect(fn () => app(CreatePrealert::class)->execute([
        'shipper_id' => $shipper->id,
        'vin' => '2T1BURHE7FC251274',
        'gatepass_pin' => 'TOO-LONG-PIN-12',
    ]))->toThrow(ValidationException::class);
});

it('updates prealert gatepass carrier and destination with validation', function () {
    $prealert = Prealert::factory()->create();
    $carrier = Carrier::factory()->create();
    $destination = Port::factory()->create();

    $updated = app(UpdatePrealert::class)->execute($prealert, [
        'gatepass_pin' => 'ZXCVBNM1234',
        'carrier_id' => $carrier->id,
        'destination_port_id' => $destination->id,
        'action_receipt' => 'documents/prealerts/AR-2002.pdf',
    ]);

    expect($updated->gatepass_pin)->toBe('ZXCVBNM1234')
        ->and($updated->carrier_id)->toBe($carrier->id)
        ->and($updated->destination_port_id)->toBe($destination->id)
        ->and($updated->action_receipt)->toBe('documents/prealerts/AR-2002.pdf');
});

it('allows duplicate gatepass_pin values in shipments', function () {
    Shipment::factory()->create(['gatepass_pin' => 'SHIPDUPL001']);
    $second = Shipment::factory()->create(['gatepass_pin' => 'SHIPDUPL001']);

    expect($second->gatepass_pin)->toBe('SHIPDUPL001');
});
