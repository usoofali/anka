<?php

use App\Models\City;
use App\Models\Consignee;
use App\Models\Country;
use App\Models\Shipper;
use App\Models\Staff;
use App\Models\State;
use App\Models\User;
use App\Notifications\ShipperRegisteredInternalNotification;
use App\Notifications\ShipperWelcomeNotification;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Notification;
use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyFeature(Features::registration());
});

test('registration screen can be rendered', function () {
    $this->seed(RolePermissionSeeder::class);

    $response = $this->get(route('register'));

    $response->assertOk()
        ->assertSee(__('Shipper registration'), escape: false);
});

test('new users can register', function () {
    Notification::fake();
    $this->seed(RolePermissionSeeder::class);

    $country = Country::factory()->create();
    $state = State::factory()->create(['country_id' => $country->id]);
    $city = City::factory()->create(['state_id' => $state->id]);

    $superAdmin = User::factory()->create([
        'email' => 'super-admin-reg-test@example.com',
    ]);
    $superAdmin->assignRole('super_admin');

    $staffUser = User::factory()->create([
        'email' => 'staff-reg-test@example.com',
    ]);
    Staff::factory()->create(['user_id' => $staffUser->id]);

    $response = $this->post(route('register.store'), [
        'name' => 'John Doe',
        'email' => 'new-shipper@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'company_name' => 'Acme Logistics',
        'phone' => '+1 555 0100',
        'address' => '123 Harbor Rd',
        'country_id' => $country->id,
        'state_id' => $state->id,
        'city_id' => $city->id,
        'terms' => '1',
    ]);

    $response->assertSessionHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false))
        ->assertSessionHas('toast', fn (mixed $toast): bool => is_array($toast)
            && ($toast['type'] ?? null) === 'success');

    $this->assertAuthenticated();

    $user = User::query()->where('email', 'new-shipper@example.com')->first();
    expect($user)->not->toBeNull();
    expect($user->hasRole('shipper'))->toBeTrue();

    $shipper = Shipper::query()->where('user_id', $user->id)->first();
    expect($shipper)->not->toBeNull()
        ->company_name->toBe('Acme Logistics')
        ->phone->toBe('+1 555 0100')
        ->address->toBe('123 Harbor Rd')
        ->country_id->toBe($country->id)
        ->state_id->toBe($state->id)
        ->city_id->toBe($city->id);

    $consignee = Consignee::query()->where('shipper_id', $shipper->id)->first();
    expect($consignee)->not->toBeNull()
        ->name->toBe('Acme Logistics')
        ->address->toBe('123 Harbor Rd')
        ->is_default->toBeTrue();

    Notification::assertSentTo($user, ShipperWelcomeNotification::class);
    Notification::assertSentTo($superAdmin, ShipperRegisteredInternalNotification::class);
    Notification::assertSentTo($staffUser, ShipperRegisteredInternalNotification::class);
    Notification::assertNotSentTo($user, ShipperRegisteredInternalNotification::class);

    Notification::assertSentTo(
        $superAdmin,
        ShipperRegisteredInternalNotification::class,
        function (ShipperRegisteredInternalNotification $notification, array $channels) use ($superAdmin, $shipper): bool {
            $data = $notification->toArray($superAdmin);

            return ($data['url'] ?? null) === route('shippers.show', $shipper, absolute: true);
        },
    );
});

test('registration requires terms acceptance', function () {
    $this->seed(RolePermissionSeeder::class);

    $country = Country::factory()->create();
    $state = State::factory()->create(['country_id' => $country->id]);
    $city = City::factory()->create(['state_id' => $state->id]);

    $response = $this->post(route('register.store'), [
        'name' => 'John Doe',
        'email' => 'no-terms@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'company_name' => 'Acme Logistics',
        'phone' => '+1 555 0100',
        'address' => '123 Harbor Rd',
        'country_id' => $country->id,
        'state_id' => $state->id,
        'city_id' => $city->id,
    ]);

    $response->assertSessionHasErrors('terms');
    $this->assertGuest();
});
