<?php

declare(strict_types=1);

use App\Enums\ShipmentStatus;
use App\Models\Shipment;
use App\Models\Shipper;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Shipments')] class extends Component {
    use WithPagination;

    public string $search = '';
    public string $filterMonth = '';
    public string $filterYear = '';
    public string $filterShipper = '';
    public string $filterShipmentStatus = '';

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'filterMonth', 'filterYear', 'filterShipper', 'filterShipmentStatus'], true)) {
            $this->resetPage();
        }
    }

    public function shipments(): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        return Shipment::query()
            ->with(['shipper.user', 'vehicle', 'driver', 'invoice'])
            ->when($this->search !== '', function ($query): void {
                $query->where(function ($searchQuery): void {
                    $term = '%' . trim($this->search) . '%';
                    $searchQuery->where('vin', 'like', $term)
                        ->orWhere('reference_no', 'like', $term)
                        ->orWhereHas('vehicle', function ($vehicleQuery) use ($term): void {
                            $vehicleQuery->where('make', 'like', $term)
                                ->orWhere('model', 'like', $term)
                                ->orWhere('year', 'like', $term);
                        })
                        ->orWhereHas('shipper', function ($shipperQuery) use ($term): void {
                            $shipperQuery->where('company_name', 'like', $term);
                        })
                        ->orWhereHas('shipper.user', function ($userQuery) use ($term): void {
                            $userQuery->where('name', 'like', $term);
                        });
                });
            })
            ->when($this->filterMonth !== '', function ($query): void {
                $query->whereMonth('created_at', (int) $this->filterMonth);
            })
            ->when($this->filterYear !== '', function ($query): void {
                $query->whereYear('created_at', (int) $this->filterYear);
            })
            ->when($this->filterShipper !== '', function ($query): void {
                $query->where('shipper_id', (int) $this->filterShipper);
            })
            ->when($this->filterShipmentStatus !== '', function ($query): void {
                $query->where('shipment_status', $this->filterShipmentStatus);
            })
            ->latest()
            ->paginate(15);
    }

    public function shippers(): \Illuminate\Database\Eloquent\Collection
    {
        return Shipper::query()
            ->with('user')
            ->orderBy('company_name')
            ->get();
    }

    public function years(): \Illuminate\Support\Collection
    {
        return Shipment::query()
            ->whereNotNull('created_at')
            ->latest('created_at')
            ->get(['created_at'])
            ->map(fn (Shipment $shipment): ?string => $shipment->created_at?->format('Y'))
            ->filter()
            ->unique()
            ->values();
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
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-5 gap-4 mb-6">
        <flux:input
            wire:model.live.debounce.300ms="search"
            label="{{ __('Search') }}"
            icon="magnifying-glass"
            placeholder="{{ __('VIN, Ref, Vehicle, Shipper') }}"
        />

        <flux:select wire:model.live="filterMonth" label="{{ __('Month') }}" icon="calendar-days">
            <flux:select.option value="">{{ __('All Months') }}</flux:select.option>
            @foreach(range(1, 12) as $month)
                <flux:select.option value="{{ $month }}">
                    {{ \Carbon\Carbon::create()->month($month)->format('F') }}
                </flux:select.option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="filterYear" label="{{ __('Year') }}" icon="calendar">
            <flux:select.option value="">{{ __('All Years') }}</flux:select.option>
            @foreach($this->years() as $year)
                <flux:select.option value="{{ $year }}">{{ $year }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="filterShipper" label="{{ __('Shipper') }}" icon="user-group">
            <flux:select.option value="">{{ __('All Shippers') }}</flux:select.option>
            @foreach($this->shippers() as $shipper)
                <flux:select.option value="{{ $shipper->id }}">
                    {{ $shipper->user?->name ?: $shipper->company_name }}
                </flux:select.option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="filterShipmentStatus" label="{{ __('Shipment Status') }}" icon="truck">
            <flux:select.option value="">{{ __('All Statuses') }}</flux:select.option>
            @foreach(ShipmentStatus::cases() as $status)
                <flux:select.option value="{{ $status->value }}">{{ $status->name }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    <x-crud.panel class="p-6">

        <flux:table :paginate="$this->shipments()">
            <flux:table.columns>
                <flux:table.column>{{ __('Ref / VIN / Created') }}</flux:table.column>
                <flux:table.column>{{ __('Vehicle') }}</flux:table.column>
                <flux:table.column>{{ __('Shipper') }}</flux:table.column>
                <flux:table.column>{{ __('Driver') }}</flux:table.column>
                <flux:table.column>{{ __('Invoice Total') }}</flux:table.column>
                <flux:table.column>{{ __('Payment Status') }}</flux:table.column>
                <flux:table.column>{{ __('Shipment Status') }}</flux:table.column>
                <flux:table.column>{{ __('Invoice Status') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($this->shipments() as $shipment)
                    <flux:table.row :key="$shipment->id">
                        <flux:table.cell>
                            <div class="flex flex-col">
                                <a
                                    href="{{ route('shipments.show', $shipment) }}"
                                    wire:navigate
                                    class="font-bold text-indigo-600 hover:text-indigo-700 dark:text-indigo-400"
                                >
                                    {{ $shipment->reference_no }}
                                </a>
                                <span class="text-xs text-zinc-500 font-mono">
                                    {{ $shipment->vin ? substr($shipment->vin, -6) : '—' }}
                                </span>
                                <span class="text-xs text-zinc-500">
                                    {{ $shipment->created_at?->format('d-m-y') ?? '—' }}
                                </span>
                            </div>
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="flex flex-col">
                                <span class="font-semibold">
                                    {{ trim(implode(' ', array_filter([$shipment->vehicle?->year, $shipment->vehicle?->make, $shipment->vehicle?->model]))) ?: '—' }}
                                </span>
                                <span class="text-xs text-zinc-500">
                                    {{ $shipment->vehicle?->color ?? '—' }}
                                </span>
                            </div>
                        </flux:table.cell>
                        <flux:table.cell>{{ $shipment->shipper?->user?->name ?? '—' }}</flux:table.cell>
                        <flux:table.cell>
                            @if($shipment->driver_id && $shipment->driver)
                                <div class="flex flex-col">
                                    <span class="font-semibold">{{ $shipment->driver->company ?: '—' }}</span>
                                    <span class="text-xs text-zinc-500">{{ $shipment->driver->phone ?: '—' }}</span>
                                </div>
                            @else
                                <span class="text-zinc-500">{{ __('Driver Not assigned') }}</span>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell class="font-mono">
                            {{ number_format((float) ($shipment->invoice?->total_amount ?? 0), 2) }}
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" color="emerald" variant="subtle">
                                {{ $shipment->payment_status?->name ?? '—' }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" color="zinc" variant="subtle">
                                {{ $shipment->shipment_status?->name ?? '—' }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" color="amber" variant="subtle">
                                {{ $shipment->invoice_status?->name ?? '—' }}
                            </flux:badge>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    </x-crud.panel>
</x-crud.page-shell>
