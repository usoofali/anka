<?php

declare(strict_types=1);

use App\Models\SystemSetting;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

test('super admin can view system settings page', function () {
    $user = User::factory()->create();
    $user->assignRole('super_admin');

    $this->actingAs($user)
        ->get(route('system-setting.edit'))
        ->assertOk()
        ->assertSee('System settings');
});

test('non super admin cannot view system settings page', function () {
    $user = User::factory()->create();
    $user->assignRole('staff_operator');

    $this->actingAs($user)
        ->get(route('system-setting.edit'))
        ->assertForbidden();
});

test('super admin can update system settings values', function () {
    $user = User::factory()->create();
    $user->assignRole('super_admin');
    $setting = SystemSetting::current();

    $this->actingAs($user);

    Livewire::test('pages::settings.system-setting')
        ->set('company_name', 'Anka Global')
        ->set('tracking_delivery_prefix', 'AGL')
        ->set('tracking_digits', 6)
        ->set('tracking_number_type', 'random')
        ->set('tracking_random_digits', 9)
        ->call('save')
        ->assertHasNoErrors();

    expect($setting->fresh()->company_name)->toBe('Anka Global')
        ->and($setting->fresh()->tracking_delivery_prefix)->toBe('AGL')
        ->and($setting->fresh()->tracking_digits)->toBe(6)
        ->and($setting->fresh()->tracking_number_type)->toBe('random')
        ->and($setting->fresh()->tracking_random_digits)->toBe(9);
});
