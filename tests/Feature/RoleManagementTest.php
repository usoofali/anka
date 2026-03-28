<?php

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

test('unauthorized users cannot access roles page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('roles.edit'))
        ->assertForbidden();
});

test('super admin can access roles page', function () {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');

    $this->actingAs($admin)
        ->get(route('roles.edit'))
        ->assertOk();
});

test('super admin can create a role', function () {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');

    Livewire::actingAs($admin)
        ->test('pages::settings.roles')
        ->set('name', 'New Role')
        ->set('selectedPermissions', ['admin.access'])
        ->call('saveRole')
        ->assertHasNoErrors();

    $role = Role::where('name', 'New Role')->first();
    expect($role)->not->toBeNull()
        ->and($role->hasPermissionTo('admin.access'))->toBeTrue();
});

test('super admin can edit a role', function () {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');
    $role = Role::create(['name' => 'Editor', 'guard_name' => 'web']);

    Livewire::actingAs($admin)
        ->test('pages::settings.roles')
        ->call('openEditModal', $role->id)
        ->set('name', 'Updated Editor')
        ->set('selectedPermissions', ['users.manage'])
        ->call('saveRole')
        ->assertHasNoErrors();

    $role->refresh();
    expect($role->name)->toBe('Updated Editor')
        ->and($role->hasPermissionTo('users.manage'))->toBeTrue();
});

test('super admin can delete a role', function () {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');
    $role = Role::create(['name' => 'Temporary', 'guard_name' => 'web']);

    Livewire::actingAs($admin)
        ->test('pages::settings.roles')
        ->call('openDeleteModal', $role->id)
        ->call('deleteRole')
        ->assertHasNoErrors();

    expect(Role::where('name', 'Temporary')->exists())->toBeFalse();
});

test('super_admin role cannot be deleted', function () {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');
    $superAdminRole = Role::where('name', 'super_admin')->first();

    Livewire::actingAs($admin)
        ->test('pages::settings.roles')
        ->assertSee($superAdminRole->name)
        ->assertDontSee("wire:click=\"openDeleteModal({$superAdminRole->id})\"");
});
