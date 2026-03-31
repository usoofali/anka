<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;

use function Pest\Laravel\get;

beforeEach(function (): void {
    File::delete(storage_path('app/setup-complete'));
    $this->seed(RolePermissionSeeder::class);
});

afterEach(function (): void {
    File::delete(storage_path('app/setup-complete'));
});

test('setup page can be rendered when super admin is missing', function () {
    get(route('setup'))
        ->assertOk()
        ->assertSee('System Setup');
});

test('setup page redirects when super admin already exists', function () {
    $user = User::factory()->create();
    $user->assignRole('super_admin');

    get(route('setup'))
        ->assertRedirect(route('login'));
});

test('login and register screens redirect to setup until completion', function () {
    get(route('login'))->assertRedirect(route('setup'));
    get(route('register'))->assertRedirect(route('setup'));
});

test('setup creates super admin and writes completion marker', function () {
    Livewire::test('pages::auth.setup')
        ->set('admin_name', 'System Admin')
        ->set('admin_email', 'setup-admin@example.com')
        ->set('admin_password', 'password123')
        ->set('admin_password_confirmation', 'password123')
        ->call('createAdmin')
        ->assertRedirect(route('login'));

    $user = User::query()->where('email', 'setup-admin@example.com')->first();

    expect($user)->not()->toBeNull()
        ->and($user?->hasRole('super_admin'))->toBeTrue()
        ->and(File::exists(storage_path('app/setup-complete')))->toBeTrue();
});
