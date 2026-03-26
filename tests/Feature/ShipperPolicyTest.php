<?php

declare(strict_types=1);

use App\Models\Shipper;
use App\Models\Staff;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

it('allows super admin to view any shipper', function () {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');
    $shipper = Shipper::factory()->create();

    expect($admin->can('view', $shipper))->toBeTrue();
});

it('allows staff with shippers.view permission to view any shipper', function () {
    $staffUser = User::factory()->create();
    $staffUser->assignRole('staff_operator');
    Staff::factory()->create(['user_id' => $staffUser->id]);
    $shipper = Shipper::factory()->create();

    expect($staffUser->can('view', $shipper))->toBeTrue();
});

it('denies staff without shippers.view permission from viewing shipper', function () {
    $staffUser = User::factory()->create();
    Staff::factory()->create(['user_id' => $staffUser->id]);
    $shipper = Shipper::factory()->create();

    expect($staffUser->can('view', $shipper))->toBeFalse();
});

it('allows shipper to view only own profile when permission is present', function () {
    $owner = User::factory()->create();
    $owner->assignRole('shipper');
    $ownerShipper = Shipper::factory()->for($owner)->create();

    $otherUser = User::factory()->create();
    $otherUser->assignRole('shipper');
    $otherShipper = Shipper::factory()->for($otherUser)->create();

    expect($owner->can('view', $ownerShipper))->toBeTrue()
        ->and($owner->can('view', $otherShipper))->toBeFalse();
});

it('denies shipper view when shippers.view permission is revoked', function () {
    $owner = User::factory()->create();
    $ownerShipper = Shipper::factory()->for($owner)->create();

    expect($owner->can('view', $ownerShipper))->toBeFalse();
});
