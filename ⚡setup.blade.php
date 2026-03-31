<?php

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('System Setup')] #[Layout('layouts.auth')] class extends Component {
    public string $step = 'welcome';

    public bool $db_connected = false;

    public bool $storage_linked = false;

    public string $php_version = '';

    public array $folder_perms = [];

    public string $last_output = '';

    // Admin Form
    public string $admin_name = '';

    public string $admin_email = '';

    public string $admin_password = '';

    public string $admin_password_confirmation = '';

    public array $target_folders = [
        'storage',
        'storage/app',
        'storage/framework',
        'storage/logs',
        'bootstrap/cache',
    ];

    public function mount(): void
    {
        // Security Check: If a Super Admin already exists, redirect to login.
        try {
            $hasSuperAdmin = User::whereHas('roles', fn($q) => $q->where('role_name', 'Super Admin'))->exists();
            if ($hasSuperAdmin) {
                redirect()->route('login');
            }
        } catch (Exception $e) {
            // Database might not even be migrated yet, which is fine for setup.
        }

        $this->refreshStats();
    }

    public function refreshStats(): void
    {
        $this->php_version = PHP_VERSION;

        try {
            DB::connection()->getPdo();
            $this->db_connected = true;
        } catch (Exception $e) {
            $this->db_connected = false;
        }

        $this->storage_linked = File::exists(public_path('storage'));

        foreach ($this->target_folders as $folder) {
            $path = base_path($folder);
            if (File::exists($path)) {
                $this->folder_perms[$folder] = substr(sprintf('%o', fileperms($path)), -4);
            } else {
                $this->folder_perms[$folder] = 'missing';
            }
        }
    }

    public function fixPermission(string $folder): void
    {
        $path = base_path($folder);
        if (File::exists($path)) {
            @chmod($path, 0775);
            $this->refreshStats();
        }
    }

    public function createStorageLink(): void
    {
        try {
            Artisan::call('storage:link');
            $this->refreshStats();
            $this->dispatch('notify', [
                'message' => __('Storage symbolic link created successfully.'),
                'variant' => 'success',
            ]);
        } catch (Exception $e) {
            $this->dispatch('notify', [
                'message' => __('Failed to create storage link: ') . $e->getMessage(),
                'variant' => 'error',
            ]);
        }
    }

    public function initializeDatabase(): void
    {
        // Increase timeout to 5 minutes to accommodate intensive password hashing during seeding
        set_time_limit(300);

        try {
            // Use 'migrate' instead of 'migrate:fresh' to avoid production destructive command restrictions
            $migrateExitCode = Artisan::call('migrate', ['--force' => true]);
            if ($migrateExitCode !== 0) {
                throw new Exception('Migration failed: ' . Artisan::output());
            }

            $seedExitCode = Artisan::call('db:seed', ['--force' => true]);
            if ($seedExitCode !== 0) {
                throw new Exception('Database seeding failed: ' . Artisan::output());
            }

            $this->last_output = Artisan::output();
            $this->step = 'admin';
            session()->flash('status', __('Database initialized successfully.'));

            // Hard redirect to clear out the temporary File session driver
            // and seamlessly start using the newly created Database session driver.
            $this->redirect('/setup', navigate: false);
        } catch (Exception $e) {
            $this->last_output = $e->getMessage();
            $this->dispatch('notify', [
                'message' => __('Database initialization failed.'),
                'variant' => 'error',
            ]);
        }
    }

    public function createAdmin(): void
    {
        $this->validate([
            'admin_name' => 'required|string|max:255',
            'admin_email' => 'required|email|unique:users,email',
            'admin_password' => 'required|string|min:8|confirmed',
        ]);

        try {
            $user = User::create([
                'name' => $this->admin_name,
                'email' => $this->admin_email,
                'password' => Hash::make($this->admin_password),
                'email_verified_at' => now(),
            ]);

            $role = Role::where('role_name', 'Super Admin')->first();
            if ($role) {
                $user->roles()->attach($role->role_id);
            }

            $this->dispatch('notify', [
                'message' => __('Super Admin created successfully.'),
                'variant' => 'success',
            ]);

            // Final redirect
            redirect()->route('login')->with('status', __('Setup complete! You can now log in.'));
        } catch (Exception $e) {
            $this->dispatch('notify', [
                'message' => __('Failed to create admin: ') . $e->getMessage(),
                'variant' => 'error',
            ]);
        }
    }
}; ?>

<div class="flex flex-col gap-6">
    <x-auth-header :title="__('System Setup')" :description="__('Initialize your environment, database, and admin account.')" />

    <!-- Step 1: Environment -->
    <div class="flex flex-col gap-4">
        <div class="flex items-center gap-3">
            <div
                class="flex items-center justify-center size-6 rounded-md bg-stone-100 dark:bg-stone-800 text-xs font-semibold text-stone-600 dark:text-stone-300">
                1</div>
            <flux:heading size="sm">{{ __('Environment Audit') }}</flux:heading>
        </div>

        <div class="flex flex-col gap-3">
            <div class="flex items-center justify-between text-sm">
                <span class="text-stone-500">{{ __('PHP Version') }}</span>
                <flux:badge size="sm" color="zinc">{{ $php_version }}</flux:badge>
            </div>

            <div class="flex items-center justify-between text-sm">
                <span class="text-stone-500">{{ __('Database Connection') }}</span>
                <flux:badge size="sm" :color="$db_connected ? 'green' : 'red'">
                    {{ $db_connected ? __('Connected') : __('Failed') }}
                </flux:badge>
            </div>

            <div class="flex items-center justify-between text-sm">
                <span class="text-stone-500">{{ __('Storage Link') }}</span>
                <div class="flex items-center gap-2">
                    <flux:badge size="sm" :color="$storage_linked ? 'green' : 'amber'">
                        {{ $storage_linked ? __('Linked') : __('Missing') }}
                    </flux:badge>
                    @if(!$storage_linked)
                        <flux:button wire:click="createStorageLink" size="xs" variant="ghost" icon="link" />
                    @endif
                </div>
            </div>

            <div class="flex flex-col gap-2 mt-2">
                @foreach($target_folders as $folder)
                    <div class="flex items-center justify-between text-xs font-mono">
                        <span class="text-stone-500">{{ $folder }}</span>
                        <div class="flex items-center gap-2">
                            <span
                                class="{{ in_array($folder_perms[$folder], ['0775', '0755']) ? 'text-green-600 dark:text-green-400' : 'text-red-500' }}">
                                {{ $folder_perms[$folder] }}
                            </span>
                            @if(!in_array($folder_perms[$folder], ['0775', '0755', 'missing']))
                                <flux:button wire:click="fixPermission('{{ $folder }}')" size="xs" variant="ghost" icon="wrench"
                                    class="h-6 w-6" />
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <flux:separator variant="subtle" />

    <!-- Step 2: Database -->
    <div class="flex flex-col gap-4">
        <div class="flex items-center gap-3">
            <div
                class="flex items-center justify-center size-6 rounded-md bg-stone-100 dark:bg-stone-800 text-xs font-semibold text-stone-600 dark:text-stone-300">
                2</div>
            <flux:heading size="sm">{{ __('Database Initialization') }}</flux:heading>
        </div>

        <div class="flex flex-col gap-4">
            <span class="text-xs text-stone-500 dark:text-stone-400">
                {{ __('This action will create all necessary tables and insert default roles. Ensure your database is empty to avoid conflicts.') }}
            </span>
            <flux:button wire:click="initializeDatabase" variant="primary" class="w-full" :disabled="!$db_connected"
                icon="play">
                {{ __('Initialize Database') }}
            </flux:button>
            @if($last_output)
                <pre
                    class="p-3 bg-stone-950 text-emerald-400 rounded-lg text-xs overflow-auto max-h-40 whitespace-pre-wrap">{{ $last_output }}</pre>
            @endif
        </div>
    </div>

    <flux:separator variant="subtle" />

    <!-- Step 3: Admin -->
    <div class="flex flex-col gap-4">
        <div class="flex items-center gap-3">
            <div
                class="flex items-center justify-center size-6 rounded-md bg-stone-100 dark:bg-stone-800 text-xs font-semibold text-stone-600 dark:text-stone-300">
                3</div>
            <flux:heading size="sm">{{ __('Super Admin') }}</flux:heading>
        </div>

        <form wire:submit="createAdmin" class="flex flex-col gap-4">
            <flux:input wire:model="admin_name" :label="__('Full Name')" placeholder="System Admin" required />
            <flux:input wire:model="admin_email" type="email" :label="__('Email Address')"
                placeholder="admin@example.com" required />
            <flux:input wire:model="admin_password" type="password" :label="__('Password')" required viewable />
            <flux:input wire:model="admin_password_confirmation" type="password" :label="__('Confirm Password')"
                required viewable />

            <div class="pt-2">
                <flux:button type="submit" variant="primary" class="w-full" icon="check-badge">
                    {{ __('Complete Setup') }}
                </flux:button>
            </div>
        </form>
    </div>
</div>