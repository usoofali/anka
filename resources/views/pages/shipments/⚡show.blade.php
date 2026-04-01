<?php

declare(strict_types=1);

use App\Enums\InvoiceStatus;
use App\Enums\ShipmentStatus;
use App\Models\ActivityLog;
use App\Models\Driver;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Shipment;
use App\Models\ShipmentTracking;
use App\Models\User;
use App\Notifications\InvoiceStatusChangedNotification;
use App\Notifications\ShipmentDispatchedNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;
use Spatie\Permission\Models\Role;
use WireUi\Traits\WireUiActions;

new #[Title('Shipment Details')] class extends Component {
    use WireUiActions;

    public Shipment $shipment;

    /** Invoice item form state */
    public ?int $invoiceItemId = null;
    public string $item_description = '';
    public string $item_amount = '0.00';

    public bool $showInvoiceStatusConfirmModal = false;

    public ?string $pendingInvoiceStatus = null;

    /** Driver assignment state */
    public bool $showAssignDriverModal = false;
    public bool $showCreateDriverModal = false;
    public ?int $driver_id = null;
    public string $new_driver_company = '';
    public string $new_driver_phone = '';
    public string $new_driver_email = '';

    public function mount(Shipment $shipment): void
    {
        $this->shipment = $shipment->load([
            'shipper.user',
            'consignee',
            'vehicle',
            'originPort.state',
            'originPort.country',
            'destinationPort.state',
            'destinationPort.country',
            'carrier',
            'paymentMethod',
            'driver',
            'invoice.items',
            'documents.files',
            'activityLogs.user',
            'trackings.workshop',
            'trackings' => static fn ($query) => $query->orderByDesc('recorded_at'),
        ]);
    }

    public function updatedShowInvoiceStatusConfirmModal(bool $value): void
    {
        if (! $value) {
            $this->pendingInvoiceStatus = null;
        }
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
            'item_description' => ['required', 'string', 'max:255', Rule::exists('charge_items', 'item')],
            'item_amount' => ['required', 'numeric', 'min:0'],
        ]);

        $amount = (float) $validated['item_amount'];

        $wasUpdating = (bool) $this->invoiceItemId;

        if ($wasUpdating) {
            /** @var InvoiceItem $item */
            $item = $invoice->items()->whereKey($this->invoiceItemId)->firstOrFail();
            $fromDescription = (string) $item->description;
            $fromAmount = (float) $item->amount;
            $item->fill([
                'description' => $validated['item_description'],
                'amount' => $amount,
            ])->save();

            ActivityLog::query()->create([
                'shipment_id' => $this->shipment->id,
                'user_id' => Auth::id(),
                'action' => 'invoice_item_updated',
                'properties' => [
                    'invoice_id' => $invoice->id,
                    'invoice_item_id' => $item->id,
                    'reference_no' => $this->shipment->reference_no,
                    'source' => 'shipment_show',
                    'from_description' => $fromDescription,
                    'to_description' => $validated['item_description'],
                    'from_amount' => $fromAmount,
                    'to_amount' => $amount,
                ],
            ]);
        } else {
            /** @var InvoiceItem $item */
            $item = $invoice->items()->create([
                'description' => $validated['item_description'],
                'amount' => $amount,
            ]);

            ActivityLog::query()->create([
                'shipment_id' => $this->shipment->id,
                'user_id' => Auth::id(),
                'action' => 'invoice_item_added',
                'properties' => [
                    'invoice_id' => $invoice->id,
                    'invoice_item_id' => $item->id,
                    'reference_no' => $this->shipment->reference_no,
                    'source' => 'shipment_show',
                    'description' => $validated['item_description'],
                    'amount' => $amount,
                ],
            ]);
        }

        $this->refreshInvoiceTotals($invoice);

        $this->shipment->load('activityLogs.user');

        $this->resetInvoiceItemForm();

        $this->notification()->success(
            $wasUpdating ? __('Invoice item updated.') : __('Invoice item added.')
        );
    }

    public function editItem(int $itemId): void
    {
        $invoice = $this->getInvoice();

        /** @var InvoiceItem $item */
        $item = $invoice->items()->whereKey($itemId)->firstOrFail();

        $this->invoiceItemId = $item->id;
        $this->item_description = (string) $item->description;
        $this->item_amount = (string) $item->amount;
    }

    public function deleteItem(int $itemId): void
    {
        $invoice = $this->getInvoice();

        /** @var InvoiceItem|null $item */
        $item = $invoice->items()->whereKey($itemId)->first();

        if ($item) {
            $properties = [
                'invoice_id' => $invoice->id,
                'invoice_item_id' => $item->id,
                'reference_no' => $this->shipment->reference_no,
                'source' => 'shipment_show',
                'description' => (string) $item->description,
                'amount' => (float) $item->amount,
            ];

            $item->delete();

            ActivityLog::query()->create([
                'shipment_id' => $this->shipment->id,
                'user_id' => Auth::id(),
                'action' => 'invoice_item_removed',
                'properties' => $properties,
            ]);

            $this->refreshInvoiceTotals($invoice);
            $this->notification()->success(__('Invoice item removed.'));
        }

        if ($this->invoiceItemId === $itemId) {
            $this->resetInvoiceItemForm();
        }

        $this->shipment->load(['invoice.items', 'activityLogs.user']);
    }

    public function openInvoiceStatusConfirm(string $value): void
    {
        $this->authorize('invoices.manage');

        $currentValue = $this->shipment->invoice?->status?->value ?? $this->shipment->invoice_status?->value;

        if ($currentValue === $value) {
            $this->notification()->info(__('No change'), __('The invoice is already in this status.'));

            return;
        }

        $this->pendingInvoiceStatus = $value;
        $this->showInvoiceStatusConfirmModal = true;
    }

    public function confirmInvoiceStatusChange(): void
    {
        $this->authorize('invoices.manage');

        $validated = $this->validate([
            'pendingInvoiceStatus' => ['required', 'string', Rule::enum(InvoiceStatus::class)],
        ]);

        $invoice = $this->getInvoice();
        $newStatus = InvoiceStatus::from($validated['pendingInvoiceStatus']);

        if ($invoice->status === $newStatus) {
            $this->showInvoiceStatusConfirmModal = false;
            $this->pendingInvoiceStatus = null;

            return;
        }

        $fromStatus = $invoice->status;

        DB::transaction(function () use ($invoice, $newStatus, $fromStatus): void {
            $invoice->status = $newStatus;
            $invoice->save();

            $this->shipment->invoice_status = $newStatus;
            $this->shipment->save();

            ActivityLog::query()->create([
                'shipment_id' => $this->shipment->id,
                'user_id' => Auth::id(),
                'action' => 'invoice_status_changed',
                'properties' => [
                    'from' => $fromStatus->value,
                    'to' => $newStatus->value,
                    'from_label' => $fromStatus->name,
                    'to_label' => $newStatus->name,
                    'invoice_id' => $invoice->id,
                    'reference_no' => $this->shipment->reference_no,
                    'source' => 'shipment_show',
                ],
            ]);
        });

        $recipientIds = $this->staffAndAdminNotificationRecipientIds();
        $recipients = User::query()->whereIn('id', $recipientIds)->get();

        if ($recipients->isNotEmpty()) {
            $invoice->refresh();
            $this->shipment->refresh();
            Notification::send(
                $recipients,
                new InvoiceStatusChangedNotification($this->shipment, $invoice, $fromStatus, $newStatus)
            );
        }

        $this->shipment->refresh()->load([
            'shipper.user',
            'consignee',
            'vehicle',
            'originPort.state',
            'originPort.country',
            'destinationPort.state',
            'destinationPort.country',
            'carrier',
            'paymentMethod',
            'driver',
            'invoice.items',
            'documents.files',
            'activityLogs.user',
            'trackings.workshop',
            'trackings' => static fn ($query) => $query->orderByDesc('recorded_at'),
        ]);

        $this->showInvoiceStatusConfirmModal = false;
        $this->pendingInvoiceStatus = null;

        $this->notification()->success(__('Invoice status updated.'));
    }

    /**
     * @return Collection<int, int>
     */
    protected function staffAndAdminNotificationRecipientIds(): Collection
    {
        $adminRoleNames = Role::query()
            ->where('name', '!=', 'shipper')
            ->pluck('name');

        return User::query()
            ->role($adminRoleNames)
            ->pluck('id')
            ->merge(User::query()->whereHas('staff')->pluck('id'))
            ->merge(User::query()->whereHas('roles', fn ($q) => $q->where('name', 'super_admin'))->pluck('id'))
            ->unique()
            ->values();
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
        $this->item_amount = '0.00';
    }

    public function openAssignDriverModal(): void
    {
        $this->authorize('shipments.update');
        $this->driver_id = $this->shipment->driver_id;
        $this->showAssignDriverModal = true;
    }

    public function assignDriver(): void
    {
        $this->authorize('shipments.update');

        $validated = $this->validate([
            'driver_id' => ['required', 'integer', 'exists:drivers,id'],
        ]);

        $driverId = (int) $validated['driver_id'];
        $driver = Driver::query()->findOrFail($driverId);

        $this->shipment->loadMissing('shipper');

        DB::transaction(function () use ($driverId, $driver): void {
            $this->shipment->update([
                'driver_id' => $driverId,
                'shipment_status' => ShipmentStatus::Dispatched,
            ]);

            ShipmentTracking::query()->create([
                'shipment_id' => $this->shipment->id,
                'status' => ShipmentStatus::Dispatched,
                'note' => __('Driver assigned; shipment dispatched.'),
                'metadata' => [
                    'source' => 'shipment_show_assign_driver',
                    'driver_id' => $driverId,
                    'created_by' => Auth::id(),
                ],
                'recorded_at' => now(),
            ]);

            $driverLabel = filled($driver->company)
                ? (string) $driver->company
                : (filled($driver->phone) ? (string) $driver->phone : (string) $driver->id);

            ActivityLog::query()->create([
                'shipment_id' => $this->shipment->id,
                'user_id' => Auth::id(),
                'action' => 'driver_assigned',
                'properties' => [
                    'driver_id' => $driverId,
                    'driver_label' => $driverLabel,
                    'reference_no' => $this->shipment->reference_no,
                    'source' => 'shipment_show',
                ],
            ]);

            $adminRoleNames = Role::query()
                ->where('name', '!=', 'shipper')
                ->pluck('name');

            $recipientIds = User::query()
                ->role($adminRoleNames)
                ->pluck('id')
                ->merge(User::query()->whereHas('staff')->pluck('id'))
                ->merge(User::query()->whereHas('roles', fn ($q) => $q->where('name', 'super_admin'))->pluck('id'))
                ->when($this->shipment->shipper?->user_id, fn ($q) => $q->push($this->shipment->shipper->user_id))
                ->unique()
                ->values();

            $recipients = User::query()
                ->whereIn('id', $recipientIds)
                ->get();

            if ($recipients->isNotEmpty()) {
                Notification::send($recipients, new ShipmentDispatchedNotification($this->shipment));
            }
        });

        $this->shipment->refresh()->load([
            'shipper.user',
            'consignee',
            'vehicle',
            'originPort.state',
            'originPort.country',
            'destinationPort.state',
            'destinationPort.country',
            'carrier',
            'paymentMethod',
            'driver',
            'invoice.items',
            'documents.files',
            'activityLogs.user',
            'trackings.workshop',
            'trackings' => static fn ($query) => $query->orderByDesc('recorded_at'),
        ]);

        $this->showAssignDriverModal = false;

        $this->notification()->success(__('Driver assigned successfully.'));
    }

    public function openCreateDriverModal(): void
    {
        $this->authorize('drivers.create');
        $this->new_driver_company = '';
        $this->new_driver_phone = '';
        $this->new_driver_email = '';
        $this->showCreateDriverModal = true;
    }

    public function createDriver(): void
    {
        $this->authorize('drivers.create');

        $validated = $this->validate([
            'new_driver_company' => ['nullable', 'string', 'max:255'],
            'new_driver_phone' => ['required', 'string', 'max:50'],
            'new_driver_email' => ['nullable', 'email', 'max:255'],
        ]);

        $driver = Driver::query()->create([
            'company' => $validated['new_driver_company'] ?: null,
            'phone' => $validated['new_driver_phone'],
            'email' => $validated['new_driver_email'] ?: null,
        ]);

        $this->driver_id = $driver->id;
        $this->showCreateDriverModal = false;

        $this->notification()->success(__('Driver created. You can now assign it to this shipment.'));
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
                        @if($shipment->paymentMethod)
                            <flux:badge color="zinc" variant="outline" size="sm" icon="credit-card">
                                {{ $shipment->paymentMethod->name }}
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

                        <flux:menu.item icon="user-plus" wire:click="openAssignDriverModal">
                            {{ __('Assign Driver') }}
                        </flux:menu.item>

                        @can('invoices.manage')
                            <flux:menu.separator />
                            <flux:menu.submenu :heading="__('Invoice status')" icon="document-text">
                                @foreach(InvoiceStatus::cases() as $status)
                                    @if(($shipment->invoice?->status?->value ?? $shipment->invoice_status?->value) !== $status->value)
                                        <flux:menu.item wire:click="openInvoiceStatusConfirm('{{ $status->value }}')">
                                            {{ $status->name }}
                                        </flux:menu.item>
                                    @endif
                                @endforeach
                            </flux:menu.submenu>
                        @endcan
                    </flux:menu>
                </flux:dropdown>
            </div>
        </div>

        {{-- At-a-glance row --}}
        <x-crud.panel class="p-4">
            <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
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
                <div>
                    <flux:text size="xs" class="uppercase tracking-widest font-bold text-zinc-400 mb-1">
                        {{ __('Driver') }}
                    </flux:text>
                    <flux:text>
                        {{ $shipment->driver?->company ?? '—' }}
                        @if($shipment->driver?->phone)
                            <span class="text-zinc-500">({{ $shipment->driver->phone }})</span>
                        @endif
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
                                    {{ $shipment->originPort->name }}
                                    ({{ $shipment->originPort->state?->code ?? '—' }} - {{ $shipment->originPort->country?->iso2 ?? '—' }})
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
                                    {{ $shipment->destinationPort->name }}
                                    ({{ $shipment->destinationPort->state?->code ?? '—' }} - {{ $shipment->destinationPort->country?->iso2 ?? '—' }})
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
                                            $invoiceItemActions = ['invoice_item_added', 'invoice_item_updated', 'invoice_item_removed'];
                                            $isInvoiceItemLog = in_array($log->action, $invoiceItemActions, true);
                                            $showActivityMetaRow = ($properties['source'] ?? null)
                                                || (array_key_exists('prealert_id', $properties) && $properties['prealert_id'] !== null)
                                                || $isInvoiceItemLog;
                                        @endphp
                                        @if(($properties['message'] ?? null))
                                            <flux:text size="sm" class="mt-1">
                                                {{ (string) $properties['message'] }}
                                            </flux:text>
                                        @endif
                                        @if($showActivityMetaRow)
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
                                                @if($log->action === 'invoice_item_updated' || $log->action === 'invoice_item_added' || $log->action === 'invoice_item_removed')
                                                    @if(filled($properties['from_description'] ?? null) || filled($properties['to_description'] ?? null))
                                                        <flux:badge size="sm" color="amber" variant="subtle">
                                                            {{ __('Item: :from → :to', [
                                                                'from' => (string) ($properties['from_description'] ?? '—'),
                                                                'to' => (string) ($properties['to_description'] ?? '—'),
                                                            ]) }}
                                                        </flux:badge>
                                                    @endif
                                                    @if(array_key_exists('from_amount', $properties) || array_key_exists('to_amount', $properties))
                                                        <flux:badge size="sm" color="amber" variant="subtle">
                                                            {{ __('Amount: :from → :to', [
                                                                'from' => '$'.number_format((float) ($properties['from_amount'] ?? 0), 2),
                                                                'to' => '$'.number_format((float) ($properties['to_amount'] ?? 0), 2),
                                                            ]) }}
                                                        </flux:badge>
                                                    @endif
                                                @elseif($isInvoiceItemLog)
                                                    @if(filled($properties['description'] ?? null))
                                                        <flux:badge size="sm" color="amber" variant="subtle">
                                                            {{ __('Item: :item', ['item' => (string) $properties['description']]) }}
                                                        </flux:badge>
                                                    @endif
                                                    @if(array_key_exists('amount', $properties))
                                                        <flux:badge size="sm" color="amber" variant="subtle">
                                                            {{ __('Amount: :amount', ['amount' => '$'.number_format((float) $properties['amount'], 2)]) }}
                                                        </flux:badge>
                                                    @endif
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
                        <div class="shrink-0 text-right">
                            @php
                                $effectiveInvoiceStatus = $shipment->invoice?->status ?? $shipment->invoice_status;
                            @endphp
                            @if($effectiveInvoiceStatus)
                                <flux:text size="xs" class="uppercase tracking-widest font-bold text-zinc-400 mb-1 block">
                                    {{ __('Status') }}
                                </flux:text>
                                <flux:badge color="amber" variant="subtle" size="sm" icon="document-text">
                                    {{ $effectiveInvoiceStatus->name }}
                                </flux:badge>
                            @else
                                <flux:text size="xs" class="text-zinc-500">
                                    {{ __('No invoice status') }}
                                </flux:text>
                            @endif
                        </div>
                    </div>

                    <div class="mb-4">
                        <flux:text size="xs" class="uppercase tracking-widest font-bold text-zinc-400 mb-1">
                            {{ __('Total') }}
                        </flux:text>
                        <flux:text class="font-mono font-semibold text-indigo-600 dark:text-indigo-400">
                            {{ '$'.number_format((float) ($shipment->invoice?->total_amount ?? 0), 2) }}
                        </flux:text>
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
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <flux:text size="sm" class="font-mono font-semibold">
                                            {{ '$'.number_format((float) $item->amount, 2) }}
                                        </flux:text>
                                        <flux:button icon="pencil-square" size="xs" variant="ghost" wire:click="editItem({{ $item->id }})" />
                                        <flux:button
                                            icon="trash"
                                            size="xs"
                                            variant="ghost"
                                            class="text-red-600 hover:text-red-700 hover:bg-red-50 dark:text-red-400 dark:hover:text-red-300 dark:hover:bg-red-950/40"
                                            wire:click="deleteItem({{ $item->id }})"
                                        />
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
                        <flux:select wire:model="item_description" label="{{ __('Invoice item') }}" icon="document-text">
                            <flux:select.option value="">{{ __('Select invoice item') }}</flux:select.option>
                            @foreach(\App\Models\ChargeItem::query()->whereNotNull('item')->orderBy('item')->get() as $chargeItem)
                                <flux:select.option :value="$chargeItem->item">
                                    {{ $chargeItem->item }}
                                </flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:input 
                            type="number"
                            min="0"
                            step="0.01"
                            wire:model="item_amount" 
                            label="{{ __('Amount') }}" 
                            icon="currency-dollar"
                        />
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
                        {{ __('Shipper & Consignee') }}
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
                                                    {{ $document->document_type?->label() ?? __('Document') }}
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

    <flux:modal wire:model="showInvoiceStatusConfirmModal" class="max-w-md">
        <div class="space-y-4">
            <div>
                <flux:heading size="lg">{{ __('Change invoice status') }}</flux:heading>
                <flux:subheading>
                    @if($pendingInvoiceStatus)
                        @php
                            $pendingToStatus = InvoiceStatus::from($pendingInvoiceStatus);
                            $fromStatusLabel = ($shipment->invoice?->status ?? $shipment->invoice_status)?->name ?? __('None');
                        @endphp
                        {{ __('Change from :from to :to?', [
                            'from' => $fromStatusLabel,
                            'to' => $pendingToStatus->name,
                        ]) }}
                    @else
                        {{ __('Confirm the new invoice status for this shipment.') }}
                    @endif
                </flux:subheading>
            </div>
            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="primary" wire:click="confirmInvoiceStatusChange" wire:loading.attr="disabled">
                    {{ __('Confirm') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>

    <flux:modal wire:model="showAssignDriverModal" class="max-w-xl min-h-[40vh] max-h-[65vh]">
        <form wire:submit="assignDriver" class="space-auto-y">
            <div class="mb-4">
                <flux:heading size="lg">{{ __('Assign Driver') }}</flux:heading>
                <flux:subheading>{{ __('Select an existing driver or add a new one, then assign to this shipment.') }}</flux:subheading>
            </div>
            @can('drivers.create')
            <div class="flex justify-end">
                <flux:button type="button" variant="ghost" icon="plus" wire:click="openCreateDriverModal">
                    {{ __('Add New Driver') }}
                </flux:button>
            </div>
        @endcan
            <div class="space-y-3">
                <x-select
                    wire:model.live="driver_id"
                    name="driver_id"
                    :label="__('Driver')"
                    :placeholder="__('Search and select driver')"
                    option-value="id"
                    option-label="name"
                    :async-data="route('api.drivers.search')"
                    searchable
                    required
                />
                <flux:error name="driver_id" />
                
            </div>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Assign Driver') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal wire:model="showCreateDriverModal" class="md:max-w-2xl">
        <form wire:submit="createDriver" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Create Driver') }}</flux:heading>
                <flux:subheading>{{ __('Add a driver and auto-select it for assignment.') }}</flux:subheading>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <flux:input wire:model="new_driver_company" :label="__('Company')" icon="building-office" placeholder="e.g. Danmazari Transport LTD" />
                <flux:input wire:model="new_driver_phone" :label="__('Phone')" icon="phone" required placeholder="+2348167768410" />
                <flux:input wire:model="new_driver_email" :label="__('Email')" icon="envelope" type="email" placeholder="driver@example.com" />
            </div>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Save Driver') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</x-crud.page-shell>
