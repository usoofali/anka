<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Country;
use App\Models\State;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('staff_admin');

    $this->operator = User::factory()->create();
    $this->operator->assignRole('staff_operator');

    $this->shipperUser = User::factory()->create();
    $this->shipperUser->assignRole('shipper');
});

/**
 * Country Management
 */
test('admin can view countries index', function () {
    $this->actingAs($this->admin);
    $this->get(route('countries.index'))->assertOk();
});

test('operator can view countries index', function () {
    $this->actingAs($this->operator);
    $this->get(route('countries.index'))->assertOk();
});

test('shipper cannot view countries index', function () {
    $this->actingAs($this->shipperUser);
    $this->get(route('countries.index'))->assertForbidden();
});

test('admin can create a country', function () {
    Livewire::actingAs($this->admin)
        ->test('pages::countries.index')
        ->set('name', 'Test Country')
        ->set('iso2', 'TC')
        ->set('iso3', 'TCO')
        ->call('saveNewCountry')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('countries', ['name' => 'Test Country', 'iso2' => 'TC']);
});

/**
 * State Management
 */
test('admin can create a state', function () {
    $country = Country::factory()->create();

    Livewire::actingAs($this->admin)
        ->test('pages::states.index')
        ->set('country_id', $country->id)
        ->set('name', 'Test State')
        ->set('code', 'TS')
        ->call('saveNewState')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('states', ['name' => 'Test State', 'country_id' => $country->id]);
});

/**
 * City Management
 */
test('admin can create a city', function () {
    $country = Country::factory()->create();
    $state = State::factory()->create(['country_id' => $country->id]);

    Livewire::actingAs($this->admin)
        ->test('pages::cities.index')
        ->set('country_id', $country->id)
        ->set('state_id', $state->id)
        ->set('name', 'Test City')
        ->call('saveNewCity')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('cities', ['name' => 'Test City', 'state_id' => $state->id]);
});

test('city creation requires state_id', function () {
    Livewire::actingAs($this->admin)
        ->test('pages::cities.index')
        ->set('name', 'Test City')
        ->call('saveNewCity')
        ->assertHasErrors(['state_id' => 'required']);
});

test('city creation dependent dropdown logic works', function () {
    $country1 = Country::factory()->create(['name' => 'Country 1']);
    $country2 = Country::factory()->create(['name' => 'Country 2']);

    $state1 = State::factory()->create(['country_id' => $country1->id, 'name' => 'State 1']);
    $state2 = State::factory()->create(['country_id' => $country2->id, 'name' => 'State 2']);

    $component = Livewire::actingAs($this->admin)
        ->test('pages::cities.index')
        ->set('country_id', $country1->id);

    expect($component->get('states'))->toHaveCount(1);
    expect($component->get('states')[0]->id)->toBe($state1->id);

    $component->set('country_id', $country2->id);
    expect($component->get('states'))->toHaveCount(1);
    expect($component->get('states')[0]->id)->toBe($state2->id);
});
