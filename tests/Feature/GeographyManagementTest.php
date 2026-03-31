<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Country;
use App\Models\State;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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

test('countries import csv creates and updates existing rows', function () {
    Livewire::actingAs($this->admin)
        ->test('pages::countries.index')
        ->set('name', 'Temp')
        ->set('iso2', 'TP')
        ->set('iso3', 'TMP')
        ->call('saveNewCountry');

    $csv = "name,iso2,iso3\nUpdated Temp,TP,UTP\nNigeria,NG,NGA\n";
    $file = UploadedFile::fake()->createWithContent('countries.csv', $csv);

    Livewire::actingAs($this->admin)
        ->test('pages::countries.index')
        ->set('importFile', $file)
        ->call('importCsv')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('countries', ['iso2' => 'TP', 'name' => 'Updated Temp']);
    $this->assertDatabaseHas('countries', ['iso2' => 'NG', 'name' => 'Nigeria']);
});

test('states import csv resolves country by iso2 and upserts by code', function () {
    $country = Country::factory()->create(['iso2' => 'US']);
    State::factory()->create(['country_id' => $country->id, 'code' => 'CA', 'name' => 'Old Name']);

    $csv = "country_iso2,name,code\nUS,California,CA\nUS,Texas,TX\n";
    $file = UploadedFile::fake()->createWithContent('states.csv', $csv);

    Livewire::actingAs($this->admin)
        ->test('pages::states.index')
        ->set('importFile', $file)
        ->call('importCsv')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('states', ['country_id' => $country->id, 'code' => 'CA', 'name' => 'California']);
    $this->assertDatabaseHas('states', ['country_id' => $country->id, 'code' => 'TX', 'name' => 'Texas']);
});

test('cities and ports import csv works with country and state code resolution', function () {
    $country = Country::factory()->create(['iso2' => 'US']);
    $state = State::factory()->create(['country_id' => $country->id, 'code' => 'CA']);

    $citiesCsv = "country_iso2,state_code,name\nUS,CA,Los Angeles\nUS,CA,San Diego\n";
    $citiesFile = UploadedFile::fake()->createWithContent('cities.csv', $citiesCsv);

    Livewire::actingAs($this->admin)
        ->test('pages::cities.index')
        ->set('importFile', $citiesFile)
        ->call('importCsv')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('cities', ['state_id' => $state->id, 'name' => 'Los Angeles']);

    $portsCsv = "name,type,country_iso2,state_code\nLong Beach,origin,US,CA\n";
    $portsFile = UploadedFile::fake()->createWithContent('ports.csv', $portsCsv);

    Livewire::actingAs($this->admin)
        ->test('pages::ports.index')
        ->set('importFile', $portsFile)
        ->call('importCsv')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('ports', [
        'name' => 'Long Beach',
        'type' => 'origin',
        'country_id' => $country->id,
        'state_id' => $state->id,
    ]);
});

test('sample csv templates are downloadable', function () {
    $this->actingAs($this->admin)
        ->get(route('import-templates.geo', 'countries'))
        ->assertOk();

    $this->actingAs($this->admin)
        ->get(route('import-templates.geo', 'states'))
        ->assertOk();
});
