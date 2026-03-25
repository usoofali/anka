<?php

use App\Models\City;
use App\Models\Country;
use App\Models\State;

test('register geo countries endpoint returns json', function () {
    Country::factory()->count(2)->create();

    $response = $this->getJson(route('register.geo.countries'));

    $response->assertOk()->assertJsonCount(2);
});

test('register geo countries selected returns one row', function () {
    $country = Country::factory()->create();

    $response = $this->getJson(route('register.geo.countries', ['selected' => $country->id]));

    $response->assertOk()
        ->assertJsonCount(1)
        ->assertJsonFragment(['id' => $country->id]);
});

test('register geo states requires country_id', function () {
    $this->getJson(route('register.geo.states'))->assertUnprocessable();
});

test('register geo states returns states for country', function () {
    $country = Country::factory()->create();
    $state = State::factory()->create(['country_id' => $country->id]);

    $response = $this->getJson(route('register.geo.states', ['country_id' => $country->id]));

    $response->assertOk()
        ->assertJsonFragment(['id' => $state->id, 'name' => $state->name]);
});

test('register geo cities requires state_id', function () {
    $this->getJson(route('register.geo.cities'))->assertUnprocessable();
});

test('register geo cities returns cities for state', function () {
    $state = State::factory()->create();
    $city = City::factory()->create(['state_id' => $state->id]);

    $response = $this->getJson(route('register.geo.cities', ['state_id' => $state->id]));

    $response->assertOk()
        ->assertJsonFragment(['id' => $city->id, 'name' => $city->name]);
});
