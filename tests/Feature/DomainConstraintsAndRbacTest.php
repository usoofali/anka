<?php

declare(strict_types=1);

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Shipment;
use App\Models\User;
use App\Models\Vehicle;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Database\QueryException;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

it('assigns super_admin broad abilities', function () {
    $user = User::factory()->create();
    $user->assignRole('super_admin');

    expect($user->hasRole('super_admin'))->toBeTrue()
        ->and($user->can('shipments.delete'))->toBeTrue()
        ->and($user->can('roles.manage'))->toBeTrue();
});

it('scopes shipper_owner permissions narrowly', function () {
    $user = User::factory()->create();
    $user->assignRole('shipper_owner');

    expect($user->can('shipments.view'))->toBeTrue()
        ->and($user->can('shipments.delete'))->toBeFalse();
});

it('enforces one vehicle per shipment at the database', function () {
    $shipment = Shipment::factory()->create();
    Vehicle::factory()->create(['shipment_id' => $shipment->id]);

    expect(fn () => Vehicle::factory()->create(['shipment_id' => $shipment->id]))
        ->toThrow(QueryException::class);
});

it('enforces one invoice per shipment at the database', function () {
    $shipment = Shipment::factory()->create();
    Invoice::factory()->create([
        'shipment_id' => $shipment->id,
        'invoice_number' => 'INV-1001',
    ]);

    expect(fn () => Invoice::factory()->create([
        'shipment_id' => $shipment->id,
        'invoice_number' => 'INV-1002',
    ]))->toThrow(QueryException::class);
});

it('enforces one payment per invoice at the database', function () {
    $invoice = Invoice::factory()->create();

    Payment::factory()->create(['invoice_id' => $invoice->id]);

    expect(fn () => Payment::factory()->create(['invoice_id' => $invoice->id]))
        ->toThrow(QueryException::class);
});
