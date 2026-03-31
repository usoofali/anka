<?php

declare(strict_types=1);

use App\Models\Shipment;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Enums\InvoiceStatus;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Shipment Details')] class extends Component {
    public Shipment $shipment;

    /** Invoice item form state */
    public ?int $invoiceItemId = null;
    public string $item_description = '';
    public int $item_quantity = 1;
    public string $item_unit_price = '0.00';

    /** Simple status selector binding for invoice */
    public ?string $invoice_status_update = null;

    public function mount(Shipment $shipment): void
    {
        $this->shipment = $shipment->load([
            'shipper.user',
            'consignee',
            'vehicle',
            'originPort',
            'destinationPort',
            'carrier',
            'invoice.items',
            'documents.documentType',
            'documents.files',
            'activityLogs.user',
            'trackings.workshop',
            'trackings' => static fn ($query) => $query->orderByDesc('recorded_at'),
        ]);

        $this->invoice_status_update = (string) ($this->shipment->invoice?->status?->value ?? $this->shipment->invoice_status?->value ?? '');
    }

    protected function getInvoice(): Invoice
    {
        if ($this->shipment->invoice) {
            return $this->shipment->invoice;
        }

        /** @var Invoice $invoice */
        $invoice = $this->shipment->invoice()->create([
            'invoice_number' => 'INV-' . strtoupper(bin2hex(random_bytes(4))),
            'status' => $this->shipment->invoice_status?->value ?? InvoiceStatus::Draft->value,
            'subtotal' => 0,
            'tax_amount' => 0,
            'total_amount' => 0,
            'issued_at' => now(),
            'due_at' => now()->addDays(7),
        ]);

        $this->shipment->setRelation('invoice', $invoice->load('items'));

        return $invoice;
    }

    public function addOrUpdateItem(): void
    {
        $invoice = $this->getInvoice();

        $validated = $this->validate([
            'item_description' => ['required', 'string', 'max:255'],
            'item_quantity' => ['required', 'integer', 'min:1'],
            'item_unit_price' => ['required', 'numeric', 'min:0'],
        ]);

        $amount = (int) $validated['item_quantity'] * (float) $validated['item_unit_price'];

        if ($this->invoiceItemId) {
            /** @var InvoiceItem $item */
            $item = $invoice->items()->whereKey($this->invoiceItemId)->firstOrFail();
            $item->fill([
                'description' => $validated['item_description'],
                'quantity' => $validated['item_quantity'],
                'unit_price' => $validated['item_unit_price'],
                'amount' => $amount,
            ])->save();
        } else {
            $invoice->items()->create([
                'description' => $validated['item_description'],
                'quantity' => $validated['item_quantity'],
                'unit_price' => $validated['item_unit_price'],
                'amount' => $amount,
            ]);
        }

        $this->refreshInvoiceTotals($invoice);

        $this->resetInvoiceItemForm();
    }

    public function editItem(int $itemId): void
    {
        $invoice = $this->getInvoice();

        /** @var InvoiceItem $item */
        $item = $invoice->items()->whereKey($itemId)->firstOrFail();

        $this->invoiceItemId = $item->id;
        $this->item_description = (string) $item->description;
        $this->item_quantity = (int) $item->quantity;
        $this->item_unit_price = (string) $item->unit_price;
    }

    public function deleteItem(int $itemId): void
    {
        $invoice = $this->getInvoice();

        /** @var InvoiceItem|null $item */
        $item = $invoice->items()->whereKey($itemId)->first();

        if ($item) {
            $item->delete();
            $this->refreshInvoiceTotals($invoice);
        }

        if ($this->invoiceItemId === $itemId) {
            $this->resetInvoiceItemForm();
        }

        $this->shipment->load('invoice.items');
    }

    public function updateInvoiceStatus(string $status): void
    {
        $invoice = $this->getInvoice();
        $invoice->status = $status;
        $invoice->save();

        $this->invoice_status_update = $status;
        $this->shipment->load('invoice');
    }

    protected function refreshInvoiceTotals(Invoice $invoice): void
    {
        $subtotal = (float) $invoice->items()->sum('amount');

        $invoice->subtotal = $subtotal;
        $invoice->tax_amount = $invoice->tax_amount ?? 0;
        $invoice->total_amount = $subtotal + (float) $invoice->tax_amount;
        $invoice->save();

        $this->shipment->load('invoice.items');
    }

    protected function resetInvoiceItemForm(): void
    {
        $this->invoiceItemId = null;
        $this->item_description = '';
        $this->item_quantity = 1;
        $this->item_unit_price = '0.00';
    }
}; ?>

<x-crud.page-shell>
    <div class="space-y-6">
        {{-- Header & Summary --}}
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex items-center gap-3">
                <div class="rounded-lg bg-zinc-100 p-2 dark:bg-zinc-800">
                    <flux:icon.document-text class="size-6 text-zinc-600 dark:text-zinc-400" />
                </div>
                <div>
                    <x-crud.page-header 
                        :heading="__('Shipment: ') . $shipment->reference_no" 
                        :subheading="__('View full shipment, tracking, and financial details.')"
                    />
                    <div class="mt-2 flex flex-wrap gap-2">
                        @if($shipment->shipment_status)
                            <flux:badge color="indigo" variant="subtle" size="sm" icon="truck">
                                {{ $shipment->shipment_status->name }}
                            </flux:badge>
                        @endif
                        @if($shipment->invoice_status)
                            <flux:badge color="amber" variant="subtle" size="sm" icon="document-text">
                                {{ $shipment->invoice_status->name }}
                            </flux:badge>
                        @endif
                        @if($shipment->payment_status)
                            <flux:badge color="emerald" variant="subtle" size="sm" icon="banknotes">
                                {{ $shipment->payment_status->name }}
                            </flux:badge>
                        @endif
                        @if($shipment->logistics_service)
                            <flux:badge color="zinc" variant="outline" size="sm" icon="briefcase">
                                {{ $shipment->logistics_service->name ?? $shipment->logistics_service }}
                            </flux:badge>
                        @endif
                        @if($shipment->shipping_mode)
                            <flux:badge color="zinc" variant="outline" size="sm" icon="cube">
                                {{ $shipment->shipping_mode->name ?? $shipment->shipping_mode }}
                            </flux:badge>
                        @endif
                    </div>
                </div>
            </div>

            <div class="flex flex-wrap gap-2">
                <flux:dropdown align="end" position="bottom">
                    <flux:button variant="outline" icon="ellipsis-horizontal">
                        {{ __('Actions') }}
                    </flux:button>
                    <flux:menu>
                        <flux:menu.item icon="arrow-left" :href="route('shipments.index')" wire:navigate>
                            {{ __('Back to Shipments') }}
                        </flux:menu.item>

                        @if(\Illuminate\Support\Facades\Route::has('shipments.edit'))
                            <flux:menu.item icon="pencil-square" :href="route('shipments.edit', $shipment)" wire:navigate>
                                {{ __('Edit Shipment') }}
                            </flux:menu.item>
                        @endif

                        <flux:menu.separator />

                        <flux:menu.item icon="user-plus" disabled>
                            {{ __('Assign Driver') }}
                        </flux:menu.item>
                    </flux:menu>
                </flux:dropdown>
            </div>
        </div>

        {{-- At-a-glance row --}}
        <x-crud.panel class="p-4">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <flux:text size="xs" class="uppercase tracking-widest font-bold text-zinc-400 mb-1">
                        {{ __('Reference') }}
                    </flux:text>
                    <flux:text class="font-mono font-semibold">
                        {{ $shipment->reference_no }}
                    </flux:text>
                </div>
                <div>
                    <flux:text size="xs" class="uppercase tracking-widest font-bold text-zinc-400 mb-1">
                        {{ __('VIN') }}
                    </flux:text>
                    <flux:text class="font-mono">
                        {{ $shipment->vin ?? '—' }}
                    </flux:text>
                </div>
                <div>
                    <flux:text size="xs" class="uppercase tracking-widest font-bold text-zinc-400 mb-1">
                        {{ __('Shipper') }}
                    </flux:text>
                    <flux:text>
                        {{ optional($shipment->shipper?->user)->name ?? '—' }}
                    </flux:text>
                </div>
                <div>
                    <flux:text size="xs" class="uppercase tracking-widest font-bold text-zinc-400 mb-1">
                        {{ __('Consignee') }}
                    </flux:text>
                    <flux:text>
                        {{ $shipment->consignee?->name ?? '—' }}
                    </flux:text>
                </div>
            </div>
        </x-crud.panel>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            {{-- Left rail --}}
            <div class="lg:col-span-2 space-y-6">
                {{-- Logistics & Routing --}}
                <x-crud.panel class="p-6">
                    <flux:heading size="lg" class="mb-4 flex items-center gap-2">
                        <flux:icon.map class="size-5 text-indigo-500" />
                        {{ __('Logistics & Routing') }}
                    </flux:heading>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <flux:text size="xs" class="uppercase tracking-widest font-bold text-zinc-400 mb-1">
                                {{ __('Origin Port') }}
                            </flux:text>
                            <flux:text class="font-medium">
                                @if($shipment->originPort)
                                    {{ $shipment->originPort->name }} ({{ $shipment->originPort->code }})
                                @else
                                    —
                                @endif
                            </flux:text>
                        </div>
                        <div>
                            <flux:text size="xs" class="uppercase tracking-widest font-bold text-zinc-400 mb-1">
                                {{ __('Destination Port') }}
                            </flux:text>
                            <flux:text class="font-medium">
                                @if($shipment->destinationPort)
                                    {{ $shipment->destinationPort->name }} ({{ $shipment->destinationPort->code }})
                                @else
                                    —
                                @endif
                            </flux:text>
                        </div>
                        <div>
                            <flux:text size="xs" class="uppercase tracking-widest font-bold text-zinc-400 mb-1">
                                {{ __('Carrier') }}
                            </flux:text>
                            <flux:text class="font-medium">
                                {{ $shipment->carrier?->name ?? '—' }}
                            </flux:text>
                        </div>
                        <div>
                            <flux:text size="xs" class="uppercase tracking-widest font-bold text-zinc-400 mb-1">
                                {{ __('Gatepass PIN') }}
                            </flux:text>
                            <flux:text class="font-mono text-emerald-600 dark:text-emerald-400 font-semibold">
                                {{ $shipment->gatepass_pin ?? '—' }}
                            </flux:text>
                        </div>
                    </div>
                </x-crud.panel>

                {{-- Vehicle & Photos --}}
                @if($shipment->vehicle)
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                        <div class="lg:col-span-2">
                            <x-crud.panel class="overflow-hidden p-0 h-full min-h-[360px]">
                                @php $photos = $shipment->vehicle->copartCarPhotoUrls(); @endphp
                                @if(count($photos) > 0)
                                    <div 
                                        x-data="{ 
                                            active: 0, 
                                            photos: {{ json_encode($photos) }},
                                            next() { this.active = (this.active + 1) % this.photos.length },
                                            prev() { this.active = (this.active - 1 + this.photos.length) % this.photos.length }
                                        }" 
                                        class="relative h-full w-full group"
                                    >
                                        <img :src="photos[active]" class="h-full w-full object-cover transition-all duration-500 ease-in-out" />

                                        <div class="absolute inset-x-0 bottom-0 bg-linear-to-t from-black/80 to-transparent p-6">
                                            <flux:heading class="text-white! text-2xl!">
                                                {{ $shipment->vehicle->year }} {{ $shipment->vehicle->make }} {{ $shipment->vehicle->model }}
                                            </flux:heading>
                                            <div class="flex items-center gap-4 mt-2">
                                                <flux:badge color="white" variant="solid" size="sm" icon="finger-print" class="text-zinc-900!">
                                                    {{ $shipment->vehicle->vin }}
                                                </flux:badge>
                                                <flux:badge color="indigo" variant="solid" size="sm" icon="ticket">
                                                    {{ $shipment->vehicle->lot_number ?? 'N/A' }}
                                                </flux:badge>
                                            </div>
                                        </div>

                                        @if(count($photos) > 1)
                                            <button 
                                                type="button" 
                                                @click="prev()" 
                                                class="absolute left-4 top-1/2 -translate-y-1/2 p-3 bg-black/30 hover:bg-black/50 rounded-full text-white opacity-0 group-hover:opacity-100 transition-opacity backdrop-blur-subtle"
                                            >
                                                <flux:icon.chevron-left class="size-6" />
                                            </button>
                                            <button 
                                                type="button" 
                                                @click="next()" 
                                                class="absolute right-4 top-1/2 -translate-y-1/2 p-3 bg-black/30 hover:bg-black/50 rounded-full text-white opacity-0 group-hover:opacity-100 transition-opacity backdrop-blur-subtle"
                                            >
                                                <flux:icon.chevron-right class="size-6" />
                                            </button>

                                            <div class="absolute bottom-4 left-1/2 -translate-x-1/2 flex gap-1.5">
                                                <template x-for="(photo, index) in photos" :key="index">
                                                    <div 
                                                        @click="active = index" 
                                                        :class="active === index ? 'bg-white w-6' : 'bg-white/30 hover:bg-white/50 w-2'" 
                                                        class="h-1.5 rounded-full transition-all duration-300 cursor-pointer"
                                                    ></div>
                                                </template>
                                            </div>
                                        @endif
                                    </div>
                                @else
                                    <div class="h-full flex flex-col items-center justify-center p-12 text-zinc-400 bg-zinc-50 dark:bg-zinc-900">
                                        <flux:icon.camera class="size-16 mb-4 opacity-20" />
                                        <flux:text>{{ __('No photos available for this vehicle.') }}</flux:text>
                                    </div>
                                @endif
                            </x-crud.panel>
                        </div>

                        <div class="space-y-6">
                            <x-crud.panel class="p-6 h-full">
                                <flux:heading size="lg" class="mb-4 flex items-center gap-2">
                                    <flux:icon.document-magnifying-glass class="size-5 text-indigo-500" />
                                    {{ __('Vehicle Details') }}
                                </flux:heading>

                                <div class="grid grid-cols-1 gap-y-4 gap-x-4">
                                    <div>
                                        <flux:text size="xs" class="uppercase tracking-widest font-bold text-zinc-400 mb-1">
                                            {{ __('Body') }}
                                        </flux:text>
                                        <flux:text class="font-medium">
                                            {{ $shipment->vehicle->body_style ?? '—' }}
                                        </flux:text>
                                    </div>
                                    <div>
                                        <flux:text size="xs" class="uppercase tracking-widest font-bold text-zinc-400 mb-1">
                                            {{ __('Type') }}
                                        </flux:text>
                                        <flux:text class="font-medium">
                                            {{ $shipment->vehicle->vehicle_type ?? '—' }}
                                        </flux:text>
                                    </div>
                                    <div>
                                        <flux:text size="xs" class="uppercase tracking-widest font-bold text-zinc-400 mb-1">
                                            {{ __('Damage') }}
                                        </flux:text>
                                        <flux:badge color="rose" variant="subtle" size="sm">
                                            {{ $shipment->vehicle->primary_damage ?? 'None' }}
                                        </flux:badge>
                                    </div>
                                    <div>
                                        <flux:text size="xs" class="uppercase tracking-widest font-bold text-zinc-400 mb-1">
                                            {{ __('Location') }}
                                        </flux:text>
                                        <flux:text class="font-medium">
                                            {{ $shipment->vehicle->location ?? '—' }}
                                        </flux:text>
                                    </div>
                                </div>
                            </x-crud.panel>
                        </div>
                    </div>
                @endif

                {{-- Tracking Timeline --}}
                <x-crud.panel class="p-6">
                    <flux:heading size="lg" class="mb-4 flex items-center gap-2">
                        <flux:icon.clock class="size-5 text-indigo-500" />
                        {{ __('Tracking History') }}
                    </flux:heading>

                    @if($shipment->trackings->isEmpty())
                        <flux:text class="text-zinc-500">
                            {{ __('No tracking events have been recorded for this shipment yet.') }}
                        </flux:text>
                    @else
                        <div class="space-y-4">
                            @foreach($shipment->trackings as $index => $tracking)
                                <div class="flex gap-3">
                                    <div class="flex flex-col items-center">
                                        <div class="size-3 rounded-full {{ $index === 0 ? 'bg-indigo-500' : 'bg-zinc-300 dark:bg-zinc-600' }}"></div>
                                        @if(! $loop->last)
                                            <div class="flex-1 w-px bg-zinc-200 dark:bg-zinc-800 mt-1"></div>
                                        @endif
                                    </div>
                                    <div class="flex-1 pb-4">
                                        <div class="flex items-center justify-between gap-2">
                                            <div class="flex items-center gap-2">
                                                <flux:badge 
                                                    :color="$index === 0 ? 'indigo' : 'zinc'" 
                                                    variant="subtle" 
                                                    size="sm"
                                                >
                                                    {{ $tracking->status->name ?? $tracking->status }}
                                                </flux:badge>
                                                @if($tracking->workshop)
                                                    <flux:text size="xs" class="text-zinc-500">
                                                        {{ $tracking->workshop->name }}
                                                    </flux:text>
                                                @endif
                                            </div>
                                            <flux:text size="xs" class="text-zinc-500">
                                                {{ $tracking->recorded_at?->toDayDateTimeString() ?? $tracking->created_at->toDayDateTimeString() }}
                                            </flux:text>
                                        </div>
                                        @if($tracking->note)
                                            <flux:text size="sm" class="mt-1">
                                                {{ $tracking->note }}
                                            </flux:text>
                                        @endif
                                        @php
                                            $trackingMetadata = is_array($tracking->metadata) ? $tracking->metadata : [];
                                        @endphp
                                        @if(($trackingMetadata['source'] ?? null) || ($trackingMetadata['created_by'] ?? null))
                                            <div class="mt-2 flex flex-wrap gap-2">
                                                @if(($trackingMetadata['source'] ?? null))
                                                    <flux:badge size="sm" color="zinc" variant="outline">
                                                        {{ __('Source: :source', ['source' => (string) $trackingMetadata['source']]) }}
                                                    </flux:badge>
                                                @endif
                                                @if(($trackingMetadata['created_by'] ?? null))
                                                    <flux:badge size="sm" color="zinc" variant="outline">
                                                        {{ __('Created by user #:id', ['id' => (string) $trackingMetadata['created_by']]) }}
                                                    </flux:badge>
                                                @endif
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </x-crud.panel>

                {{-- Activity Log --}}
                <x-crud.panel class="p-6">
                    <flux:heading size="lg" class="mb-4 flex items-center gap-2">
                        <flux:icon.list-bullet class="size-5 text-indigo-500" />
                        {{ __('Activity Log') }}
                    </flux:heading>

                    @if($shipment->activityLogs->isEmpty())
                        <flux:text class="text-zinc-500">
                            {{ __('No activity has been recorded for this shipment yet.') }}
                        </flux:text>
                    @else
                        <div class="space-y-3">
                            @foreach($shipment->activityLogs->sortByDesc('created_at') as $log)
                                <div class="flex items-start gap-3 border-b border-zinc-100 dark:border-zinc-800 pb-3 last:border-0 last:pb-0">
                                    <flux:avatar 
                                        :name="$log->user?->name ?? 'System'" 
                                        size="xs" 
                                        class="bg-zinc-100! text-zinc-700!" 
                                    />
                                    <div class="flex-1">
                                        <div class="flex items-center justify-between gap-2">
                                            <flux:text size="sm" class="font-medium">
                                                {{ $log->user?->name ?? __('System') }}
                                            </flux:text>
                                            <flux:text size="xs" class="text-zinc-500">
                                                {{ $log->created_at?->diffForHumans() }}
                                            </flux:text>
                                        </div>
                                        <flux:text size="sm" class="text-zinc-600 dark:text-zinc-300">
                                            {{ ucfirst($log->action) }}
                                        </flux:text>
                                        @php
                                            $properties = is_array($log->properties) ? $log->properties : [];
                                        @endphp
                                        @if(($properties['message'] ?? null))
                                            <flux:text size="sm" class="mt-1">
                                                {{ (string) $properties['message'] }}
                                            </flux:text>
                                        @endif
                                        @if(($properties['source'] ?? null) || (array_key_exists('prealert_id', $properties) && $properties['prealert_id'] !== null))
                                            <div class="mt-2 flex flex-wrap gap-2">
                                                @if(($properties['source'] ?? null))
                                                    <flux:badge size="sm" color="zinc" variant="outline">
                                                        {{ __('Source: :source', ['source' => (string) $properties['source']]) }}
                                                    </flux:badge>
                                                @endif
                                                @if(array_key_exists('prealert_id', $properties) && $properties['prealert_id'] !== null)
                                                    <flux:badge size="sm" color="indigo" variant="subtle">
                                                        {{ __('Prealert #:id', ['id' => (string) $properties['prealert_id']]) }}
                                                    </flux:badge>
                                                @endif
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </x-crud.panel>
            </div>

            {{-- Right rail --}}
            <div class="space-y-6">
                {{-- Invoice & Items --}}
                <x-crud.panel class="p-6 bg-zinc-50 dark:bg-zinc-800/60 border-zinc-200 dark:border-zinc-700">
                    <div class="flex items-start justify-between gap-3 mb-4">
                        <div>
                            <flux:heading size="lg" class="flex items-center gap-2">
                                <flux:icon.receipt-percent class="size-5 text-indigo-500" />
                                {{ __('Invoice') }}
                            </flux:heading>
                            <flux:text size="sm" class="text-zinc-500 mt-1">
                                {{ $shipment->invoice?->invoice_number ?? __('No invoice number assigned yet.') }}
                            </flux:text>
                        </div>
                        <div class="w-40">
                            <flux:select 
                                wire:model="invoice_status_update" 
                                label="{{ __('Status') }}" 
                                icon="document-text"
                                wire:change="updateInvoiceStatus($event.target.value)"
                            >
                                <flux:select.option value="">{{ __('Select') }}</flux:select.option>
                                @foreach(InvoiceStatus::cases() as $status)
                                    <flux:select.option value="{{ $status->value }}">{{ $status->name }}</flux:select.option>
                                @endforeach
                            </flux:select>
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-3 mb-4">
                        <div>
                            <flux:text size="xs" class="uppercase tracking-widest font-bold text-zinc-400 mb-1">
                                {{ __('Subtotal') }}
                            </flux:text>
                            <flux:text class="font-mono font-semibold">
                                {{ number_format((float) ($shipment->invoice?->subtotal ?? 0), 2) }}
                            </flux:text>
                        </div>
                        <div>
                            <flux:text size="xs" class="uppercase tracking-widest font-bold text-zinc-400 mb-1">
                                {{ __('Tax') }}
                            </flux:text>
                            <flux:text class="font-mono font-semibold">
                                {{ number_format((float) ($shipment->invoice?->tax_amount ?? 0), 2) }}
                            </flux:text>
                        </div>
                        <div>
                            <flux:text size="xs" class="uppercase tracking-widest font-bold text-zinc-400 mb-1">
                                {{ __('Total') }}
                            </flux:text>
                            <flux:text class="font-mono font-semibold text-indigo-600 dark:text-indigo-400">
                                {{ number_format((float) ($shipment->invoice?->total_amount ?? 0), 2) }}
                            </flux:text>
                        </div>
                    </div>

                    <div class="border border-zinc-200 dark:border-zinc-700 rounded-xl overflow-hidden mb-4">
                        <div class="bg-zinc-100 dark:bg-zinc-800 px-3 py-2 flex items-center justify-between">
                            <flux:text size="xs" class="uppercase tracking-widest font-bold text-zinc-500">
                                {{ __('Invoice Items') }}
                            </flux:text>
                        </div>
                        <div class="max-h-56 overflow-y-auto divide-y divide-zinc-100 dark:divide-zinc-800">
                            @forelse($shipment->invoice?->items ?? collect() as $item)
                                <div class="px-3 py-2 flex items-center justify-between gap-3">
                                    <div class="flex-1">
                                        <flux:text size="sm" class="font-medium">
                                            {{ $item->description }}
                                        </flux:text>
                                        <flux:text size="xs" class="text-zinc-500">
                                            {{ $item->quantity }} × {{ number_format((float) $item->unit_price, 2) }}
                                        </flux:text>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <flux:text size="sm" class="font-mono font-semibold">
                                            {{ number_format((float) $item->amount, 2) }}
                                        </flux:text>
                                        <flux:button icon="pencil-square" size="xs" variant="ghost" wire:click="editItem({{ $item->id }})" />
                                        <flux:button icon="trash" size="xs" variant="ghost" wire:click="deleteItem({{ $item->id }})" />
                                    </div>
                                </div>
                            @empty
                                <div class="px-3 py-4">
                                    <flux:text size="sm" class="text-zinc-500">
                                        {{ __('No invoice items yet. Add the first charge below.') }}
                                    </flux:text>
                                </div>
                            @endforelse
                        </div>
                    </div>

                    <form wire:submit.prevent="addOrUpdateItem" class="space-y-3">
                        <flux:input 
                            wire:model="item_description" 
                            label="{{ __('Description') }}" 
                            icon="document-text"
                        />
                        <div class="grid grid-cols-2 gap-3">
                            <flux:input 
                                type="number"
                                min="1"
                                wire:model="item_quantity" 
                                label="{{ __('Quantity') }}" 
                                icon="hashtag"
                            />
                            <flux:input 
                                type="number"
                                min="0"
                                step="0.01"
                                wire:model="item_unit_price" 
                                label="{{ __('Unit Price') }}" 
                                icon="currency-dollar"
                            />
                        </div>
                        <div class="flex gap-2">
                            <flux:button type="submit" variant="primary" icon="plus-circle" class="flex-1">
                                {{ $invoiceItemId ? __('Update Item') : __('Add Item') }}
                            </flux:button>
                            @if($invoiceItemId)
                                <flux:button type="button" variant="ghost" class="flex-none" wire:click="$set('invoiceItemId', null)">
                                    {{ __('Cancel') }}
                                </flux:button>
                            @endif
                        </div>
                    </form>
                </x-crud.panel>

                {{-- Shipper & Consignee --}}
                <x-crud.panel class="p-6">
                    <flux:heading size="lg" class="mb-4 flex items-center gap-2">
                        <flux:icon.user-group class="size-5 text-indigo-500" />
                        {{ __('Parties') }}
                    </flux:heading>

                    <div class="space-y-4">
                        @if($shipment->shipper)
                            <div class="flex items-start gap-3">
                                <flux:avatar 
                                    :name="$shipment->shipper->user?->name ?? $shipment->shipper->company_name" 
                                    size="md" 
                                    class="bg-indigo-100! text-indigo-700!" 
                                />
                                <div>
                                    <flux:text size="sm" class="font-semibold">
                                        {{ $shipment->shipper->company_name ?? $shipment->shipper->user?->name }}
                                    </flux:text>
                                    <div class="flex flex-col gap-1 mt-1 text-zinc-500">
                                        @if($shipment->shipper->user?->email)
                                            <div class="flex items-center gap-1.5">
                                                <flux:icon.envelope class="size-3.5" />
                                                <flux:text size="xs">{{ $shipment->shipper->user->email }}</flux:text>
                                            </div>
                                        @endif
                                        @if($shipment->shipper->phone)
                                            <div class="flex items-center gap-1.5">
                                                <flux:icon.phone class="size-3.5" />
                                                <flux:text size="xs">{{ $shipment->shipper->phone }}</flux:text>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endif

                        @if($shipment->consignee)
                            <div class="border-t border-zinc-100 dark:border-zinc-800 pt-3 mt-2">
                                <flux:text size="xs" class="uppercase tracking-widest font-bold text-zinc-400 mb-1">
                                    {{ __('Consignee') }}
                                </flux:text>
                                <flux:text size="sm" class="font-medium">
                                    {{ $shipment->consignee->name }}
                                </flux:text>
                            </div>
                        @endif
                    </div>
                </x-crud.panel>

                {{-- Documents & Auction Receipt --}}
                <x-crud.panel class="p-6">
                    <flux:heading size="lg" class="mb-4 flex items-center gap-2">
                        <flux:icon.paper-clip class="size-5 text-indigo-500" />
                        {{ __('Documents') }}
                    </flux:heading>

                    @php
                        $documents = $shipment->documents;
                    @endphp

                    <div class="space-y-4">
                        @if($shipment->auction_receipt)
                            <div class="flex items-center gap-3 p-3 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800">
                                <div class="p-2 bg-indigo-50 dark:bg-indigo-900/30 rounded-lg text-indigo-600 dark:text-indigo-400">
                                    <flux:icon.document-arrow-down class="size-5" />
                                </div>
                                <div class="flex-1">
                                    <flux:text size="sm" class="font-semibold">
                                        {{ __('Auction Receipt') }}
                                    </flux:text>
                                    <flux:text size="xs" class="text-zinc-500 font-mono">
                                        {{ \Illuminate\Support\Str::limit($shipment->auction_receipt, 40) }}
                                    </flux:text>
                                </div>
                            </div>
                        @endif

                        @if($documents->isEmpty())
                            <flux:text size="sm" class="text-zinc-500">
                                {{ __('No additional documents attached yet.') }}
                            </flux:text>
                        @else
                            <div class="space-y-2">
                                @foreach($documents as $document)
                                    <div class="flex items-center justify-between gap-3 p-2 rounded-lg border border-zinc-100 dark:border-zinc-800">
                                        <div class="flex items-center gap-2">
                                            <flux:icon.document class="size-5 text-zinc-400" />
                                            <div>
                                                <flux:text size="sm" class="font-medium">
                                                    {{ $document->documentType?->name ?? __('Document') }}
                                                </flux:text>
                                                <flux:text size="xs" class="text-zinc-500">
                                                    {{ $document->files->count() }} {{ \Illuminate\Support\Str::plural('file', $document->files->count()) }}
                                                </flux:text>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        <div class="pt-2 border-t border-zinc-100 dark:border-zinc-800 mt-2">
                            <flux:text size="xs" class="uppercase tracking-widest font-bold text-zinc-400 mb-2 block">
                                {{ __('Manage Attachments') }}
                            </flux:text>
                            <flux:button variant="outline" icon="arrow-up-tray" class="w-full" disabled>
                                {{ __('Attach Document (coming soon)') }}
                            </flux:button>
                        </div>
                    </div>
                </x-crud.panel>
            </div>
        </div>
    </div>
</x-crud.page-shell>
