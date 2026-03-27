<?php

declare(strict_types=1);

use App\Models\Shipper;
use App\Models\Staff;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

test('shipper index is forbidden without shippers.view', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('shippers.index'))
        ->assertForbidden();
});

test('super admin can open shipper index', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');

    $this->actingAs($admin)
        ->get(route('shippers.index'))
        ->assertOk();
});

test('staff can open shipper create', function (): void {
    $staffUser = User::factory()->create();
    $staffUser->assignRole('staff_operator');
    Staff::factory()->create(['user_id' => $staffUser->id]);

    $this->actingAs($staffUser)
        ->get(route('shippers.create'))
        ->assertOk();
});

test('shipper role cannot open shipper create', function (): void {
    $user = User::factory()->create();
    $user->assignRole('shipper');
    Shipper::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->get(route('shippers.create'))
        ->assertForbidden();
});

test('shipper owner can open edit for own company', function (): void {
    $user = User::factory()->create();
    $user->assignRole('shipper');
    $shipper = Shipper::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->get(route('shippers.edit', $shipper))
        ->assertOk();
});

test('shipper owner cannot open edit for another company', function (): void {
    $owner = User::factory()->create();
    $owner->assignRole('shipper');
    Shipper::factory()->create(['user_id' => $owner->id]);

    $other = User::factory()->create();
    $other->assignRole('shipper');
    $otherShipper = Shipper::factory()->create(['user_id' => $other->id]);

    $this->actingAs($owner)
        ->get(route('shippers.edit', $otherShipper))
        ->assertForbidden();
});

test('staff can delete shipper from index', function (): void {
    $staffUser = User::factory()->create();
    $staffUser->assignRole('staff_operator');
    Staff::factory()->create(['user_id' => $staffUser->id]);
    $shipper = Shipper::factory()->create();
    $ownerId = $shipper->user_id;

    $this->actingAs($staffUser);

    Livewire::test('pages::shippers.index')
        ->call('openDeleteModal', $shipper->id)
        ->assertSet('showDeleteModal', true)
        ->call('deleteShipper');

    expect(Shipper::query()->whereKey($shipper->id)->exists())->toBeFalse();
    expect(User::query()->whereKey($ownerId)->exists())->toBeFalse();
});

test('shipper cannot open delete confirmation', function (): void {
    $user = User::factory()->create();
    $user->assignRole('shipper');
    $shipper = Shipper::factory()->create(['user_id' => $user->id]);

    expect($user->can('delete', $shipper))->toBeFalse();

    $this->actingAs($user);

    Livewire::test('pages::shippers.index')
        ->call('openDeleteModal', $shipper->id)
        ->assertSet('showDeleteModal', false)
        ->assertSet('shipperPendingDeleteId', null);
});
