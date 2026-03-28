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

it('allows super admin viewAny shippers', function () {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');

    expect($admin->can('viewAny', Shipper::class))->toBeTrue();
});

it('allows staff with shippers.view to viewAny shippers', function () {
    $staffUser = User::factory()->create();
    $staffUser->assignRole('staff_operator');
    Staff::factory()->create(['user_id' => $staffUser->id]);

    expect($staffUser->can('viewAny', Shipper::class))->toBeTrue();
});

it('allows shipper with profile to viewAny shippers list', function () {
    $owner = User::factory()->create();
    $owner->assignRole('shipper');
    Shipper::factory()->for($owner)->create();

    expect($owner->can('viewAny', Shipper::class))->toBeTrue();
});

it('denies viewAny when shippers.view is missing', function () {
    $user = User::factory()->create();

    expect($user->can('viewAny', Shipper::class))->toBeFalse();
});

it('denies creating shippers via admin for everyone (registration only)', function () {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');

    $staffUser = User::factory()->create();
    $staffUser->assignRole('staff_operator');
    Staff::factory()->create(['user_id' => $staffUser->id]);

    $owner = User::factory()->create();
    $owner->assignRole('shipper');
    Shipper::factory()->for($owner)->create();

    expect($admin->can('create', Shipper::class))->toBeFalse()
        ->and($staffUser->can('create', Shipper::class))->toBeFalse()
        ->and($owner->can('create', Shipper::class))->toBeFalse();
});

it('allows super admin to update any shipper', function () {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');
    $shipper = Shipper::factory()->create();

    expect($admin->can('update', $shipper))->toBeTrue();
});

it('allows staff to update any shipper', function () {
    $staffUser = User::factory()->create();
    $staffUser->assignRole('staff_operator');
    Staff::factory()->create(['user_id' => $staffUser->id]);
    $shipper = Shipper::factory()->create();

    expect($staffUser->can('update', $shipper))->toBeTrue();
});

it('denies shipper from updating any company (staff and super admin only)', function () {
    $owner = User::factory()->create();
    $owner->assignRole('shipper');
    $ownShipper = Shipper::factory()->for($owner)->create();

    $other = User::factory()->create();
    $other->assignRole('shipper');
    $otherShipper = Shipper::factory()->for($other)->create();

    expect($owner->can('update', $ownShipper))->toBeFalse()
        ->and($owner->can('update', $otherShipper))->toBeFalse();
});

it('allows super admin to delete any shipper', function () {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');
    $shipper = Shipper::factory()->create();

    expect($admin->can('delete', $shipper))->toBeTrue();
});

it('allows staff with shippers.delete to delete any shipper', function () {
    $staffUser = User::factory()->create();
    $staffUser->assignRole('staff_operator');
    Staff::factory()->create(['user_id' => $staffUser->id]);
    $shipper = Shipper::factory()->create();

    expect($staffUser->can('delete', $shipper))->toBeTrue();
});

it('denies shipper from deleting shippers', function () {
    $owner = User::factory()->create();
    $owner->assignRole('shipper');
    $shipper = Shipper::factory()->for($owner)->create();

    expect($owner->can('delete', $shipper))->toBeFalse();
});

it('denies delete when shippers.delete permission is missing', function () {
    $user = User::factory()->create();
    Staff::factory()->create(['user_id' => $user->id]);
    $shipper = Shipper::factory()->create();

    expect($user->can('delete', $shipper))->toBeFalse();
});
