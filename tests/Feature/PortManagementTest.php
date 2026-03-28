<?php

declare(strict_types=1);

use App\Models\City;
use App\Models\Country;
use App\Models\State;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

beforeEach(fn () => $this->seed(RolePermissionSeeder::class));

it('requires authentication to view ports', function () {
    get(route('ports.index'))->assertRedirect(route('login'));
});

it('denies access to users without permissions', function () {
    $user = User::factory()->create();

    actingAs($user)
        ->get(route('ports.index'))
        ->assertForbidden();
});

it('allows users with permission to view ports', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('ports.view');

    actingAs($user)
        ->get(route('ports.index'))
        ->assertOk()
        ->assertSeeLivewire('pages::ports.index');
});

it('can create a new port', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(['ports.view', 'ports.create']);
    actingAs($user);

    $country = Country::factory()->create();
    $state = State::factory()->create(['country_id' => $country->id]);
    $city = City::factory()->create(['state_id' => $state->id]);

    Livewire::test('pages::ports.index')
        ->set('name', 'Port of Test')
        ->set('code', 'usptst')
        ->set('country_id', $country->id)
        ->set('state_id', $state->id)
        ->set('city_id', $city->id)
        ->call('saveNewPort')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('ports', [
        'name' => 'Port of Test',
        'code' => 'USPTST',
        'city_id' => $city->id,
    ]);
});

it('validates geospatial hierarchy on port creation', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(['ports.view', 'ports.create']);
    actingAs($user);

    $country1 = Country::factory()->create();
    $country2 = Country::factory()->create();

    $state = State::factory()->create(['country_id' => $country1->id]);
    $city = City::factory()->create(['state_id' => $state->id]);

    Livewire::test('pages::ports.index')
        ->set('name', 'Invalid Geo Port')
        ->set('code', 'INVLD')
        ->set('country_id', $country2->id) // Mismatch! State belongs to country 1
        ->set('state_id', $state->id)
        ->set('city_id', $city->id)
        ->call('saveNewPort')
        ->assertHasErrors(['state_id']);
});
