<?php

declare(strict_types=1);

use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Enums\ShipmentStatus;
use App\Models\Shipment;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

beforeEach(fn () => $this->seed(RolePermissionSeeder::class));

it('allows users with shipments.update permission to view shipment edit page', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('shipments.update');
    actingAs($user);

    $shipment = Shipment::factory()->create();

    get(route('shipments.edit', $shipment))
        ->assertOk()
        ->assertSee('Edit Shipment');
});

it('forbids users without shipments.update permission from shipment edit page', function () {
    $user = User::factory()->create();
    actingAs($user);

    $shipment = Shipment::factory()->create();

    get(route('shipments.edit', $shipment))
        ->assertForbidden();
});

it('updates shipment fields from edit page', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('shipments.update');
    actingAs($user);

    $shipment = Shipment::factory()->create([
        'reference_no' => 'REF-EDIT-0001',
        'vin' => '1HGCM82633A004352',
        'shipment_status' => ShipmentStatus::Pending->value,
        'invoice_status' => InvoiceStatus::Draft->value,
        'payment_status' => PaymentStatus::Pending->value,
    ]);

    Livewire::test('pages::shipments.edit', ['shipment' => $shipment])
        ->set('shipment_status', ShipmentStatus::Inland->value)
        ->set('invoice_status', InvoiceStatus::Completed->value)
        ->set('payment_status', PaymentStatus::Paid->value)
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('shipments', [
        'id' => $shipment->id,
        'reference_no' => 'REF-EDIT-0001',
        'vin' => '1HGCM82633A004352',
        'shipment_status' => ShipmentStatus::Inland->value,
        'invoice_status' => InvoiceStatus::Completed->value,
        'payment_status' => PaymentStatus::Paid->value,
    ]);
});

it('keeps unique fields for current record and rejects duplicates from another shipment', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('shipments.update');
    actingAs($user);

    $shipment = Shipment::factory()->create([
        'reference_no' => 'REF-UNIQ-0001',
        'vin' => 'JH4KA8260MC000001',
    ]);

    $otherShipment = Shipment::factory()->create([
        'reference_no' => 'REF-UNIQ-0002',
        'vin' => 'JH4KA8260MC000002',
    ]);

    Livewire::test('pages::shipments.edit', ['shipment' => $shipment])
        ->set('reference_no', 'REF-UNIQ-0001')
        ->set('vin', 'JH4KA8260MC000001')
        ->call('save')
        ->assertHasNoErrors();

    Livewire::test('pages::shipments.edit', ['shipment' => $shipment])
        ->set('reference_no', $otherShipment->reference_no)
        ->set('vin', $otherShipment->vin)
        ->call('save')
        ->assertHasErrors(['reference_no', 'vin']);
});
