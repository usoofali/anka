<?php

declare(strict_types=1);

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Shipment;
use App\Models\Shipper;
use App\Models\User;
use App\Models\Wallet;
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
        ->and($user->can('roles.manage'))->toBeTrue()
        ->and($user->can('prealerts.update'))->toBeTrue()
        ->and($user->can('shippers.view'))->toBeTrue();
});

it('scopes shipper permissions narrowly', function () {
    $user = User::factory()->create();
    $user->assignRole('shipper');

    expect($user->can('shipments.view'))->toBeTrue()
        ->and($user->can('shipments.delete'))->toBeFalse()
        ->and($user->can('prealerts.create'))->toBeTrue()
        ->and($user->can('shippers.view'))->toBeTrue();
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

it('stores invoices and wallets in the configured financial currency', function () {
    $shipment = Shipment::factory()->create();
    $invoice = new Invoice([
        'invoice_number' => 'INV-USD-1',
        'shipment_id' => $shipment->id,
        'status' => InvoiceStatus::Draft->value,
        'subtotal' => '100.00',
        'tax_amount' => '0.00',
        'total_amount' => '100.00',
    ]);
    $invoice->forceFill(['currency' => 'EUR']);
    $invoice->save();

    expect($invoice->refresh()->currency)->toBe(config('financial.currency'));

    $wallet = new Wallet([
        'shipper_id' => Shipper::factory()->create()->id,
        'balance' => '0.00',
    ]);
    $wallet->forceFill(['currency' => 'GBP']);
    $wallet->save();

    expect($wallet->refresh()->currency)->toBe(config('financial.currency'));
});

it('links shipper to a consistent country, state, and city', function () {
    $shipper = Shipper::factory()->create();

    expect($shipper->city)->not->toBeNull()
        ->and($shipper->state)->not->toBeNull()
        ->and($shipper->country)->not->toBeNull()
        ->and($shipper->state_id)->toBe($shipper->city->state_id)
        ->and($shipper->country_id)->toBe($shipper->state->country_id);
});
