<?php

use App\Models\Shipper;
use App\Models\User;
use App\Notifications\ShipperRegisteredInternalNotification;
use Database\Seeders\RolePermissionSeeder;

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

    $this->actingAs($recipient)
        ->post(route('notifications.read', $databaseNotification->id))
        ->assertRedirect();

    expect($databaseNotification->fresh()->read_at)->not->toBeNull();
});
