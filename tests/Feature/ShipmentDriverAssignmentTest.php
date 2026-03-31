<?php

declare(strict_types=1);

use App\Enums\ShipmentStatus;
use App\Models\Driver;
use App\Models\Shipment;
use App\Models\Staff;
use App\Models\User;
use App\Notifications\ShipmentDispatchedNotification;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;

beforeEach(fn () => $this->seed(RolePermissionSeeder::class));

it('assigns a selected driver to a shipment from shipment show', function () {
    Notification::fake();

    $user = User::factory()->create();
    $user->givePermissionTo(['shipments.update', 'drivers.view']);
    actingAs($user);

    $staffUser = User::factory()->create();
    Staff::factory()->create(['user_id' => $staffUser->id]);

    $shipment = Shipment::factory()->create([
        'driver_id' => null,
        'shipment_status' => ShipmentStatus::Pending,
    ]);
    $shipperOwner = $shipment->shipper->user;

    $driver = Driver::factory()->create([
        'company' => 'Danmazari Transport LTD',
        'phone' => '+2348167768410',
    ]);

    Livewire::test('pages::shipments.show', ['shipment' => $shipment])
        ->call('openAssignDriverModal')
        ->assertSet('showAssignDriverModal', true)
        ->set('driver_id', $driver->id)
        ->call('assignDriver')
        ->assertHasNoErrors()
        ->assertSet('showAssignDriverModal', false);

    $this->assertDatabaseHas('shipments', [
        'id' => $shipment->id,
        'driver_id' => $driver->id,
        'shipment_status' => ShipmentStatus::Dispatched->value,
    ]);

    $this->assertDatabaseHas('activity_logs', [
        'shipment_id' => $shipment->id,
        'user_id' => $user->id,
        'action' => 'driver_assigned',
    ]);

    $this->assertDatabaseHas('shipment_trackings', [
        'shipment_id' => $shipment->id,
        'status' => ShipmentStatus::Dispatched->value,
    ]);

    Notification::assertSentTo(
        [$staffUser, $shipperOwner],
        ShipmentDispatchedNotification::class
    );
});

it('creates a new driver from shipment show and auto-selects it', function () {
    Notification::fake();

    $user = User::factory()->create();
    $user->givePermissionTo(['shipments.update', 'drivers.create']);
    actingAs($user);

    $staffUser = User::factory()->create();
    Staff::factory()->create(['user_id' => $staffUser->id]);

    $shipment = Shipment::factory()->create([
        'driver_id' => null,
        'shipment_status' => ShipmentStatus::Pending,
    ]);
    $shipperOwner = $shipment->shipper->user;

    $component = Livewire::test('pages::shipments.show', ['shipment' => $shipment])
        ->call('openAssignDriverModal')
        ->call('openCreateDriverModal')
        ->assertSet('showCreateDriverModal', true)
        ->set('new_driver_company', 'Road Runner Logistics')
        ->set('new_driver_phone', '+1 555 222 3333')
        ->set('new_driver_email', 'assign-driver@example.com')
        ->call('createDriver')
        ->assertHasNoErrors()
        ->assertSet('showCreateDriverModal', false);

    $driver = Driver::query()->where('email', 'assign-driver@example.com')->first();

    expect($driver)->not()->toBeNull();

    $component
        ->assertSet('driver_id', $driver->id)
        ->call('assignDriver')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('shipments', [
        'id' => $shipment->id,
        'driver_id' => $driver->id,
        'shipment_status' => ShipmentStatus::Dispatched->value,
    ]);

    $this->assertDatabaseHas('activity_logs', [
        'shipment_id' => $shipment->id,
        'action' => 'driver_assigned',
    ]);

    Notification::assertSentTo(
        [$staffUser, $shipperOwner],
        ShipmentDispatchedNotification::class
    );
});

it('returns async driver options for select component', function () {
    $user = User::factory()->create();
    actingAs($user);

    Driver::factory()->create([
        'company' => 'Danmazari Transport LTD',
        'phone' => '+2348167768410',
        'email' => 'yumitsolutions@gmail.com',
    ]);

    getJson(route('api.drivers.search', ['search' => 'danmazari']))
        ->assertOk()
        ->assertJsonFragment([
            'name' => 'Danmazari Transport LTD',
        ]);
});
