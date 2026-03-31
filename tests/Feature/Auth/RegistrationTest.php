<?php

use App\Models\City;
use App\Models\Consignee;
use App\Models\Country;
use App\Models\Shipper;
use App\Models\Staff;
use App\Models\State;
use App\Models\SystemSetting;
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
    SystemSetting::current()->update([
        'company_name' => 'Anka Logistics',
        'logo' => 'https://example.com/logo.png',
    ]);

    $response = $this->get(route('register'));

    $response->assertOk()
        ->assertSee(__('Shipper registration'), escape: false)
        ->assertSee('https://example.com/logo.png', escape: false);
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

    $this->assertAuthenticated();

    $user = User::query()->where('email', 'new-shipper@example.com')->first();
    expect($user)->not->toBeNull();
    expect($user->hasRole('shipper'))->toBeTrue();

    $shipper = Shipper::query()->where('user_id', $user->id)->first();
    expect($shipper)->not->toBeNull();

    $wallet = $shipper->wallet;
    expect($wallet)->not->toBeNull()
        ->balance->toEqual('0.00');

    $response->assertSessionHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false))
        ->assertSessionHas('toast', fn (mixed $toast): bool => is_array($toast)
            && ($toast['type'] ?? null) === 'success');

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

test('registration succeeds without company name and uses user name for default consignee', function () {
    Notification::fake();
    $this->seed(RolePermissionSeeder::class);

    $country = Country::factory()->create();
    $state = State::factory()->create(['country_id' => $country->id]);
    $city = City::factory()->create(['state_id' => $state->id]);

    $response = $this->post(route('register.store'), [
        'name' => 'Jane Shipper',
        'email' => 'no-company@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'company_name' => '',
        'phone' => '+1 555 0200',
        'address' => '456 Dock Ln',
        'country_id' => $country->id,
        'state_id' => $state->id,
        'city_id' => $city->id,
        'terms' => '1',
    ]);

    $response->assertSessionHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));

    $user = User::query()->where('email', 'no-company@example.com')->first();
    expect($user)->not->toBeNull();

    $shipper = Shipper::query()->where('user_id', $user->id)->first();
    expect($shipper)->not->toBeNull()
        ->company_name->toBeNull();

    $consignee = Consignee::query()->where('shipper_id', $shipper->id)->first();
    expect($consignee)->not->toBeNull()
        ->name->toBe('Jane Shipper');

    expect($shipper->wallet)->not->toBeNull();
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
