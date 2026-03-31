<?php

declare(strict_types=1);

use App\Models\Shipment;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Shipment Details')] class extends Component {
    public Shipment $shipment;

    public function mount(Shipment $shipment): void
    {
        $this->shipment = $shipment->load(['shipper.user', 'consignee', 'vehicle', 'originPort', 'destinationPort', 'carrier']);
    }
}; ?>

<x-crud.page-shell>
    <div class="flex items-center gap-3 mb-6">
        <div class="rounded-lg bg-zinc-100 p-2 dark:bg-zinc-800">
            <flux:icon.document-text class="size-6 text-zinc-600 dark:text-zinc-400" />
        </div>
        <x-crud.page-header 
            :heading="__('Shipment: ') . $shipment->reference_no" 
            :subheading="__('View detailed information and tracking history.')"
        />
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <div class="lg:col-span-2 space-y-6">
            <x-crud.panel class="p-6">
                <flux:heading size="lg" class="mb-4">{{ __('Core Details') }}</flux:heading>
                <flux:text>{{ __('This is a placeholder for the Shipment Show page.') }}</flux:text>
            </x-crud.panel>
        </div>

        <div class="space-y-6">
            <x-crud.panel class="p-6">
                <flux:heading size="lg" class="mb-4">{{ __('Quick Actions') }}</flux:heading>
                <flux:button :href="route('shipments.index')" variant="ghost" class="w-full">
                    {{ __('Back to Shipments') }}
                </flux:button>
            </x-crud.panel>
        </div>
    </div>
</x-crud.page-shell>
