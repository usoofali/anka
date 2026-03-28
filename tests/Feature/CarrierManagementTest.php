<?php

declare(strict_types=1);

use App\Models\Carrier;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

beforeEach(fn () => $this->seed(RolePermissionSeeder::class));

it('requires authentication to view carriers', function () {
    get(route('carriers.index'))->assertRedirect(route('login'));
});

it('denies access to users without permissions', function () {
    $user = User::factory()->create();

    actingAs($user)
        ->get(route('carriers.index'))
        ->assertForbidden();
});

it('allows users with permission to view carriers', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('carriers.view');

    actingAs($user)
        ->get(route('carriers.index'))
        ->assertOk()
        ->assertSeeLivewire('pages::carriers.index');
});

it('lists carriers on the index page', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('carriers.view');

    Carrier::factory()->create(['name' => 'Test Carrier XYZ']);

    actingAs($user);

    Livewire::test('pages::carriers.index')
        ->assertSee('Test Carrier XYZ');
});

it('can create a new carrier', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(['carriers.view', 'carriers.create']);
    actingAs($user);

    Livewire::test('pages::carriers.index')
        ->set('name', 'New Rapid Transport')
        ->set('description', 'Express delivery')
        ->call('saveNewCarrier')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('carriers', [
        'name' => 'New Rapid Transport',
        'description' => 'Express delivery',
    ]);
});

it('can edit a carrier', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(['carriers.view', 'carriers.update']);
    actingAs($user);

    $carrier = Carrier::factory()->create([
        'name' => 'Old Name',
    ]);

    Livewire::test('pages::carriers.index')
        ->call('openEditModal', $carrier->id)
        ->assertSet('name', 'Old Name')
        ->set('name', 'Updated Name')
        ->call('saveCarrier')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('carriers', [
        'id' => $carrier->id,
        'name' => 'Updated Name',
    ]);
});

it('can delete a carrier', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(['carriers.view', 'carriers.delete']);
    actingAs($user);

    $carrier = Carrier::factory()->create();

    Livewire::test('pages::carriers.index')
        ->call('openDeleteModal', $carrier->id)
        ->assertSet('carrierPendingDeleteId', $carrier->id)
        ->call('deleteCarrier')
        ->assertHasNoErrors();

    $this->assertDatabaseMissing('carriers', [
        'id' => $carrier->id,
    ]);
});
