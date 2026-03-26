<?php

use App\Models\Shipper;
use App\Models\Staff;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

test('super admin can view any shipper', function () {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');

    $shipper = Shipper::factory()->create();

    $this->actingAs($admin)
        ->get(route('shippers.show', $shipper))
        ->assertOk()
        ->assertSee($shipper->company_name, escape: false);
});

test('staff user can view any shipper', function () {
    $staffUser = User::factory()->create();
    $staffUser->assignRole('staff_operator');
    Staff::factory()->create(['user_id' => $staffUser->id]);

    $shipper = Shipper::factory()->create();

    $this->actingAs($staffUser)
        ->get(route('shippers.show', $shipper))
        ->assertOk();
});

test('shipper owner can view own shipper', function () {
    $user = User::factory()->create();
    $user->assignRole('shipper');
    $shipper = Shipper::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->get(route('shippers.show', $shipper))
        ->assertOk();
});

test('shipper owner cannot view another users shipper', function () {
    $owner = User::factory()->create();
    $owner->assignRole('shipper');
    Shipper::factory()->create(['user_id' => $owner->id]);

    $intruder = User::factory()->create();
    $intruder->assignRole('shipper');
    $otherShipper = Shipper::factory()->create(['user_id' => $intruder->id]);

    $this->actingAs($owner)
        ->get(route('shippers.show', $otherShipper))
        ->assertForbidden();
});

test('unrelated user cannot view shipper', function () {
    $user = User::factory()->create();
    $shipper = Shipper::factory()->create();

    $this->actingAs($user)
        ->get(route('shippers.show', $shipper))
        ->assertForbidden();
});
