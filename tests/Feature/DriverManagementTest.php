<?php

declare(strict_types=1);

use App\Models\Driver;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

beforeEach(fn () => $this->seed(RolePermissionSeeder::class));

it('requires authentication to view drivers', function () {
    get(route('drivers.index'))->assertRedirect(route('login'));
});

it('denies access to users without permissions', function () {
    $user = User::factory()->create();

    actingAs($user)
        ->get(route('drivers.index'))
        ->assertForbidden();
});

it('allows users with permission to view drivers', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('drivers.view');

    actingAs($user)
        ->get(route('drivers.index'))
        ->assertOk()
        ->assertSeeLivewire('pages::drivers.index');
});

it('can create a new driver without name field', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(['drivers.view', 'drivers.create']);
    actingAs($user);

    Livewire::test('pages::drivers.index')
        ->set('phone', '+1 555 111 2222')
        ->set('email', 'driver@example.com')
        ->set('company', 'Road Runner Logistics')
        ->call('saveNewDriver')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('drivers', [
        'phone' => '+1 555 111 2222',
        'email' => 'driver@example.com',
        'company' => 'Road Runner Logistics',
    ]);
});

it('can edit an existing driver', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(['drivers.view', 'drivers.update']);
    actingAs($user);

    $driver = Driver::factory()->create([
        'phone' => '+1 555 000 0000',
        'email' => 'old@example.com',
        'company' => 'Old Co',
    ]);

    Livewire::test('pages::drivers.index')
        ->call('openEditModal', $driver->id)
        ->assertSet('phone', '+1 555 000 0000')
        ->set('phone', '+1 555 999 8888')
        ->set('email', 'new@example.com')
        ->set('company', 'New Co')
        ->call('saveDriver')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('drivers', [
        'id' => $driver->id,
        'phone' => '+1 555 999 8888',
        'email' => 'new@example.com',
        'company' => 'New Co',
    ]);
});

it('can delete a driver', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(['drivers.view', 'drivers.delete']);
    actingAs($user);

    $driver = Driver::factory()->create();

    Livewire::test('pages::drivers.index')
        ->call('openDeleteModal', $driver->id)
        ->assertSet('driverPendingDeleteId', $driver->id)
        ->call('deleteDriver')
        ->assertHasNoErrors();

    $this->assertDatabaseMissing('drivers', [
        'id' => $driver->id,
    ]);
});
