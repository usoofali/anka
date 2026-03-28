<?php

declare(strict_types=1);

use App\Models\Prealert;
use App\Models\Shipper;
use App\Models\Staff;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

it('allows super admin and staff to view any prealert', function () {
    $prealert = Prealert::factory()->create();

    $admin = User::factory()->create();
    $admin->assignRole('super_admin');
    expect($admin->can('view', $prealert))->toBeTrue();

    $staffUser = User::factory()->create();
    $staffUser->assignRole('staff_operator');
    Staff::factory()->create(['user_id' => $staffUser->id]);
    expect($staffUser->can('view', $prealert))->toBeTrue();
});

it('allows a shipper to view only their own prealerts', function () {
    $ownerUser = User::factory()->create();
    $ownerUser->assignRole('shipper');
    $ownerShipper = Shipper::factory()->for($ownerUser)->create();
    $prealert = Prealert::factory()->for($ownerShipper)->create();

    $otherUser = User::factory()->create();
    $otherUser->assignRole('shipper');
    Shipper::factory()->for($otherUser)->create();

    expect($ownerUser->can('view', $prealert))->toBeTrue()
        ->and($otherUser->can('view', $prealert))->toBeFalse();
});

it('allows shippers to create prealerts', function () {
    $user = User::factory()->create();
    $user->assignRole('shipper');
    Shipper::factory()->for($user)->create();

    expect($user->can('create', Prealert::class))->toBeTrue();
});

it('allows authorized staff to create prealerts', function () {
    $user = User::factory()->create();
    $user->assignRole('staff_admin');
    Staff::factory()->create(['user_id' => $user->id]);

    expect($user->can('create', Prealert::class))->toBeTrue();
});

it('denies prealert creation without prealerts.create permission', function () {
    $user = User::factory()->create();
    Shipper::factory()->for($user)->create();

    expect($user->can('create', Prealert::class))->toBeFalse();
});
