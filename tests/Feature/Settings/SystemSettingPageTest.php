<?php

declare(strict_types=1);

use App\Models\SystemSetting;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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

test('super admin can view system config page', function () {
    $user = User::factory()->create();
    $user->assignRole('super_admin');

    $this->actingAs($user)
        ->get(route('system-config.edit'))
        ->assertOk()
        ->assertSee('System Configuration');
});

test('non super admin cannot view system config page', function () {
    $user = User::factory()->create();
    $user->assignRole('staff_operator');

    $this->actingAs($user)
        ->get(route('system-config.edit'))
        ->assertForbidden();
});

test('settings nav shows system config link only for super admin', function () {
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole('super_admin');

    $this->actingAs($superAdmin)
        ->get(route('system-setting.edit'))
        ->assertOk()
        ->assertSee('System Config');

    auth()->logout();

    $staff = User::factory()->create();
    $staff->assignRole('staff_operator');

    $this->actingAs($staff)
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertDontSee('System Config');
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

test('super admin upload persists logo_path without base64 payload', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $user->assignRole('super_admin');
    $this->actingAs($user);

    $logo = UploadedFile::fake()->create('logo.png', 20, 'image/png');

    Livewire::test('pages::settings.system-setting')
        ->set('logo_file', $logo)
        ->call('save')
        ->assertHasNoErrors();

    $setting = SystemSetting::current()->fresh();

    expect($setting)->not->toBeNull()
        ->and($setting->logo_path)->not->toBeNull()
        ->and($setting->logo_path)->toStartWith('system/logo/')
        ->and($setting->logo)->toBeNull();

    Storage::disk('public')->assertExists($setting->logo_path);
});

test('logo source helpers prefer logo_path and email never uses data uri', function () {
    Storage::fake('public');

    Storage::disk('public')->put('system/logo/company.png', 'binary-content');

    $setting = SystemSetting::factory()->create([
        'logo_path' => 'system/logo/company.png',
        'logo' => 'data:image/png;base64,'.base64_encode('fallback-binary'),
    ]);

    expect($setting->logoSrcForWeb())->toContain('/storage/system/logo/company.png')
        ->and($setting->logoSrcForEmail())->toContain('/storage/system/logo/company.png')
        ->and($setting->logoSrcForEmail())->not->toStartWith('data:image/');
});

test('upload replacement deletes previous stored logo file', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $user->assignRole('super_admin');
    $this->actingAs($user);

    $oldPath = 'system/logo/old-logo.png';
    Storage::disk('public')->put($oldPath, 'old-content');
    SystemSetting::current()->update([
        'logo_path' => $oldPath,
        'logo' => 'data:image/png;base64,'.base64_encode('old'),
    ]);

    $newLogo = UploadedFile::fake()->create('new-logo.png', 20, 'image/png');

    Livewire::test('pages::settings.system-setting')
        ->set('logo_file', $newLogo)
        ->call('save')
        ->assertHasNoErrors();

    $current = SystemSetting::current()->fresh();

    expect($current->logo_path)->not->toBeNull()
        ->and($current->logo_path)->not->toBe($oldPath);

    Storage::disk('public')->assertMissing($oldPath);
    Storage::disk('public')->assertExists($current->logo_path);
});
