<?php

use App\Models\Shipper;
use App\Models\User;
use App\Notifications\ShipperRegisteredInternalNotification;
use Database\Seeders\RolePermissionSeeder;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

test('authenticated user can view notifications index', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('notifications.index'))
        ->assertOk();
});

test('user can mark a notification as read', function () {
    $recipient = User::factory()->create();
    $registered = User::factory()->create();
    $shipper = Shipper::factory()->create(['user_id' => $registered->id]);

    $recipient->notify(new ShipperRegisteredInternalNotification($registered, $shipper));

    $databaseNotification = $recipient->notifications()->first();
    expect($databaseNotification)->not->toBeNull()
        ->read_at->toBeNull();

    $this->actingAs($recipient);

    Livewire::test('pages::notifications.index')
        ->call('markAsRead', $databaseNotification->id)
        ->assertHasNoErrors();

    expect($databaseNotification->fresh()->read_at)->not->toBeNull();
});

test('user cannot mark another user notification as read', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $registered = User::factory()->create();
    $shipper = Shipper::factory()->create(['user_id' => $registered->id]);

    $owner->notify(new ShipperRegisteredInternalNotification($registered, $shipper));

    $databaseNotification = $owner->notifications()->first();
    expect($databaseNotification)->not->toBeNull()
        ->read_at->toBeNull();

    $this->actingAs($intruder);

    Livewire::test('pages::notifications.index')
        ->call('markAsRead', $databaseNotification->id)
        ->assertHasNoErrors();

    expect($databaseNotification->fresh()->read_at)->toBeNull();
});

test('notification dropdown dispatches sound event when unread count increases', function () {
    $recipient = User::factory()->create();
    $registered = User::factory()->create();
    $shipper = Shipper::factory()->create(['user_id' => $registered->id]);

    $this->actingAs($recipient);

    $component = Livewire::test('notification-dropdown')
        ->call('refreshNotifications')
        ->assertNotDispatched('notifications:new-unread');

    $recipient->notify(new ShipperRegisteredInternalNotification($registered, $shipper));

    $component
        ->call('refreshNotifications')
        ->assertDispatched('notifications:new-unread');
});

test('notification dropdown opens when unread count increases', function () {
    $recipient = User::factory()->create();
    $registered = User::factory()->create();
    $shipper = Shipper::factory()->create(['user_id' => $registered->id]);

    $this->actingAs($recipient);

    $component = Livewire::test('notification-dropdown')
        ->assertSet('dropdownOpen', false);

    $recipient->notify(new ShipperRegisteredInternalNotification($registered, $shipper));

    $component
        ->call('refreshNotifications')
        ->assertSet('dropdownOpen', true)
        ->assertHasNoErrors();
});
