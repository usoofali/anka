<?php

declare(strict_types=1);

use App\Models\Shipper;
use Illuminate\Http\Request;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Shipper')] class extends Component {
    public Shipper $shipper;

    public function mount(Request $request, Shipper $shipper): void
    {
        $this->authorize('view', $shipper);

        if ($request->filled('notification')) {
            $request->user()
                ?->notifications()
                ->whereKey($request->query('notification'))
                ->first()
                ?->markAsRead();
        }

        $this->shipper = $shipper->load(['user', 'country', 'state', 'city']);
    }
}; ?>

<x-crud.page-shell>
    <x-crud.page-header :heading="$shipper->company_name" :subheading="__('Shipper profile')">
        <x-slot name="actions">
            @can('update', $shipper)
                <flux:button variant="primary" :href="route('shippers.edit', $shipper)" wire:navigate icon="pencil-square">
                    {{ __('Edit') }}
                </flux:button>
            @endcan
        </x-slot>
    </x-crud.page-header>

    <x-crud.detail-panel>
        <div>
            <flux:text class="text-xs font-medium uppercase text-zinc-500">{{ __('Company') }}</flux:text>
            <flux:text>{{ $shipper->company_name }}</flux:text>
        </div>
        <div>
            <flux:text class="text-xs font-medium uppercase text-zinc-500">{{ __('Owner') }}</flux:text>
            <flux:text>{{ $shipper->user?->name }} ({{ $shipper->user?->email }})</flux:text>
        </div>
        <div>
            <flux:text class="text-xs font-medium uppercase text-zinc-500">{{ __('Phone') }}</flux:text>
            <flux:text>{{ $shipper->phone ?: '—' }}</flux:text>
        </div>
        <div>
            <flux:text class="text-xs font-medium uppercase text-zinc-500">{{ __('Address') }}</flux:text>
            <flux:text>{{ $shipper->address ?: '—' }}</flux:text>
        </div>
        <div class="grid gap-2 sm:grid-cols-2">
            <div>
                <flux:text class="text-xs font-medium uppercase text-zinc-500">{{ __('Country') }}</flux:text>
                <flux:text>{{ $shipper->country?->name ?? '—' }}</flux:text>
            </div>
            <div>
                <flux:text class="text-xs font-medium uppercase text-zinc-500">{{ __('State / region') }}</flux:text>
                <flux:text>{{ $shipper->state?->name ?? '—' }}</flux:text>
            </div>
            <div class="sm:col-span-2">
                <flux:text class="text-xs font-medium uppercase text-zinc-500">{{ __('City') }}</flux:text>
                <flux:text>{{ $shipper->city?->name ?? '—' }}</flux:text>
            </div>
        </div>
        <div class="flex flex-wrap gap-4 text-sm text-zinc-600 dark:text-zinc-400">
            <span>{{ __('Shipper ID') }}: {{ $shipper->id }}</span>
            <span>{{ __('User ID') }}: {{ $shipper->user_id }}</span>
        </div>
    </x-crud.detail-panel>
</x-crud.page-shell>
