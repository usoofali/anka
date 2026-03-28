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

test('shipper create route is not registered', function (): void {
    $this->get('/shippers/create')->assertNotFound();
});

test('legacy shipper edit URL is not registered', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');
    $shipper = Shipper::factory()->create();

    $this->actingAs($admin)
        ->get('/shippers/'.$shipper->id.'/edit')
        ->assertNotFound();
});

test('super admin can open shipper edit modal from index', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');
    $shipper = Shipper::factory()->create();

    Livewire::actingAs($admin)
        ->test('pages::shippers.index')
        ->call('openEditModal', $shipper->id)
        ->assertSet('showEditModal', true)
        ->assertSet('shipperEditingId', $shipper->id);
});

test('staff can open shipper edit modal from index', function (): void {
    $staffUser = User::factory()->create();
    $staffUser->assignRole('staff_operator');
    Staff::factory()->create(['user_id' => $staffUser->id]);
    $shipper = Shipper::factory()->create();

    Livewire::actingAs($staffUser)
        ->test('pages::shippers.index')
        ->call('openEditModal', $shipper->id)
        ->assertSet('showEditModal', true)
        ->assertSet('shipperEditingId', $shipper->id);
});

test('shipper cannot open edit modal for own company', function (): void {
    $owner = User::factory()->create();
    $owner->assignRole('shipper');
    $ownShipper = Shipper::factory()->create(['user_id' => $owner->id]);

    Livewire::actingAs($owner)
        ->test('pages::shippers.index')
        ->call('openEditModal', $ownShipper->id)
        ->assertSet('showEditModal', false)
        ->assertSet('shipperEditingId', null);
});

test('shipper cannot open edit modal for another company', function (): void {
    $owner = User::factory()->create();
    $owner->assignRole('shipper');
    Shipper::factory()->create(['user_id' => $owner->id]);

    $other = User::factory()->create();
    $other->assignRole('shipper');
    $otherShipper = Shipper::factory()->create(['user_id' => $other->id]);

    Livewire::actingAs($owner)
        ->test('pages::shippers.index')
        ->call('openEditModal', $otherShipper->id)
        ->assertSet('showEditModal', false)
        ->assertSet('shipperEditingId', null);
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
