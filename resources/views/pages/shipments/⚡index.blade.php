<?php

declare(strict_types=1);

use App\Models\Shipment;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Shipments')] class extends Component {
    use WithPagination;

    public function shipments()
    {
        return Shipment::with(['shipper.user', 'vehicle', 'originPort', 'destinationPort'])
            ->latest()
            ->paginate(15);
    }
}; ?>

<x-crud.page-shell>
    <div class="flex items-center justify-between mb-6">
        <div class="flex items-center gap-3">
            <div class="rounded-lg bg-zinc-100 p-2 dark:bg-zinc-800">
                <flux:icon.truck class="size-6 text-zinc-600 dark:text-zinc-400" />
            </div>
            <x-crud.page-header :heading="__('Shipments')" :subheading="__('Manage all active shipments and their status.')"
                class="mb-0!" />
        </div>

        <flux:button variant="primary" icon="plus" :href="route('shipments.create')" wire:navigate>
            {{ __('New Shipment') }}
        </flux:button>
    </div>

    <x-crud.panel class="p-6">
        <flux:table :paginate="$this->shipments()">
            <flux:table.columns>
                <flux:table.column>{{ __('Ref / VIN') }}</flux:table.column>
                <flux:table.column>{{ __('Shipper') }}</flux:table.column>
                <flux:table.column>{{ __('Route') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column>{{ __('Created') }}</flux:table.column>
                <flux:table.column>{{ __('Actions') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($this->shipments() as $shipment)
                    <flux:table.row :key="$shipment->id">
                        <flux:table.cell>
                            <div class="flex flex-col">
                                <span class="font-bold text-zinc-900 dark:text-zinc-100">{{ $shipment->reference_no }}</span>
                                <span class="text-xs text-zinc-500 font-mono">{{ $shipment->vin }}</span>
                            </div>
                        </flux:table.cell>
                        <flux:table.cell>{{ $shipment->shipper?->user?->name }}</flux:table.cell>
                        <flux:table.cell>
                            <div class="flex items-center gap-2 text-xs">
                                <span class="font-semibold">{{ $shipment->originPort?->code ?: '—' }}</span>
                                <flux:icon.arrow-right class="size-3 text-zinc-400" />
                                <span class="font-semibold">{{ $shipment->destinationPort?->code ?: '—' }}</span>
                            </div>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" color="zinc" variant="subtle">{{ $shipment->shipment_status->name }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell class="text-zinc-500">{{ $shipment->created_at?->diffForHumans() }}</flux:table.cell>
                        <flux:table.cell align="right">
                           <flux:button variant="ghost" icon="eye" size="sm" />
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    </x-crud.panel>
</x-crud.page-shell>
