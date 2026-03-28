<?php

declare(strict_types=1);

use App\Concerns\HandlesShipperGeoSelects;
use App\Models\City;
use App\Models\Country;
use App\Models\Port;
use App\Models\State;
use App\Support\ShipperGeoValidator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use WireUi\Traits\WireUiActions;

new #[Title('Ports')] class extends Component {
    use HandlesShipperGeoSelects;
    use WireUiActions;
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    public bool $showCreateModal = false;
    public bool $showEditModal = false;
    public bool $showDeleteModal = false;

    public ?int $portEditingId = null;
    public ?int $portPendingDeleteId = null;
    public string $portPendingDeleteLabel = '';

    public string $name = '';
    public string $code = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function mount(): void
    {
        $this->authorize('ports.view');
    }

    public function openCreateModal(): void
    {
        $this->authorize('ports.create');
        $this->reset('name', 'code', 'country_id', 'state_id', 'city_id', 'portEditingId');
        $this->showCreateModal = true;
    }

    public function saveNewPort(): void
    {
        $this->authorize('ports.create');

        $validator = Validator::make(
            [
                'name' => $this->name,
                'code' => mb_strtoupper($this->code),
                'country_id' => $this->country_id,
                'state_id' => $this->state_id,
                'city_id' => $this->city_id,
            ],
            [
                'name' => ['required', 'string', 'max:255', 'unique:ports,name'],
                'code' => ['required', 'string', 'max:50', 'unique:ports,code'],
                'country_id' => ['required', 'integer', 'exists:countries,id'],
                'state_id' => ['required', 'integer', 'exists:states,id'],
                'city_id' => ['required', 'integer', 'exists:cities,id'],
            ]
        );
        $validator->after(function (\Illuminate\Validation\Validator $v): void {
            ShipperGeoValidator::assertHierarchy($v);
        });
        $validated = $validator->validate();

        Port::create($validated);

        $this->showCreateModal = false;
        $this->notification()->success(__('Port created successfully.'));
    }

    public function openEditModal(int $portId): void
    {
        $this->authorize('ports.update');
        $port = Port::findOrFail($portId);
        $this->portEditingId = $port->id;
        $this->name = $port->name;
        $this->code = $port->code;
        $this->country_id = $port->country_id;
        $this->state_id = $port->state_id;
        $this->city_id = $port->city_id;
        
        $this->showEditModal = true;
    }

    public function savePort(): void
    {
        $this->authorize('ports.update');
        if ($this->portEditingId === null) {
            return;
        }

        $port = Port::findOrFail($this->portEditingId);

        $validator = Validator::make(
            [
                'name' => $this->name,
                'code' => mb_strtoupper($this->code),
                'country_id' => $this->country_id,
                'state_id' => $this->state_id,
                'city_id' => $this->city_id,
            ],
            [
                'name' => ['required', 'string', 'max:255', 'unique:ports,name,' . $port->id],
                'code' => ['required', 'string', 'max:50', 'unique:ports,code,' . $port->id],
                'country_id' => ['required', 'integer', 'exists:countries,id'],
                'state_id' => ['required', 'integer', 'exists:states,id'],
                'city_id' => ['required', 'integer', 'exists:cities,id'],
            ]
        );
        $validator->after(function (\Illuminate\Validation\Validator $v): void {
            ShipperGeoValidator::assertHierarchy($v);
        });
        $validated = $validator->validate();

        $port->update($validated);

        $this->showEditModal = false;
        $this->notification()->success(__('Port updated successfully.'));
    }

    public function openDeleteModal(int $portId): void
    {
        $this->authorize('ports.delete');
        $port = Port::findOrFail($portId);

        $this->portPendingDeleteId = $port->id;
        $this->portPendingDeleteLabel = $port->name . ' (' . $port->code . ')';
        $this->showDeleteModal = true;
    }

    public function deletePort(): void
    {
        $this->authorize('ports.delete');
        if ($this->portPendingDeleteId === null) {
            return;
        }

        $port = Port::findOrFail($this->portPendingDeleteId);
        
        if ($port->originShipments()->exists() || $port->destinationShipments()->exists()) {
            $this->showDeleteModal = false;
            $this->notification()->warning(__('Cannot delete port because it is associated with one or more shipments.'));
            return;
        }

        $port->delete();

        $this->showDeleteModal = false;
        $this->portPendingDeleteId = null;
        $this->portPendingDeleteLabel = '';

        $this->notification()->success(__('Port deleted successfully.'));
    }

    #[Computed]
    public function ports(): LengthAwarePaginator
    {
        return Port::query()
            ->with(['country', 'state'])
            ->withCount(['originShipments', 'destinationShipments'])
            ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%")
                ->orWhere('code', 'like', "%{$this->search}%"))
            ->orderBy('name')
            ->paginate(15);
    }
}; ?>

<div>
    <x-crud.page-shell>
        <div class="flex items-center justify-between mb-8">
            <x-crud.page-header :heading="__('Ports')" :subheading="__('Manage origin and destination ports.')" icon="map-pin" class="!mb-0" />
            @can('ports.create')
                <flux:button variant="primary" icon="plus" wire:click="openCreateModal">{{ __('Create Port') }}</flux:button>
            @endcan
        </div>

        <div class="mb-4">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Search by name or code...')" clearable />
        </div>

        <x-crud.panel class="p-6">
            <flux:table :paginate="$this->ports">
                <flux:table.columns>
                    <flux:table.column icon="qr-code">{{ __('Code') }}</flux:table.column>
                    <flux:table.column icon="map-pin">{{ __('Name') }}</flux:table.column>
                    <flux:table.column icon="globe-alt">{{ __('Location') }}</flux:table.column>
                    <flux:table.column icon="box">{{ __('Shipments') }}</flux:table.column>
                    <flux:table.column align="right">{{ __('Actions') }}</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($this->ports as $port)
                        <flux:table.row :key="$port->id">
                            <flux:table.cell>
                                <flux:badge color="zinc" class="font-mono uppercase tracking-widest">{{ $port->code }}</flux:badge>
                            </flux:table.cell>
                            <flux:table.cell class="font-medium">
                                {{ $port->name }}
                            </flux:table.cell>
                            <flux:table.cell class="text-zinc-500 whitespace-nowrap text-sm">
                                {{ $port->state?->name }}, {{ $port->country?->name }}
                            </flux:table.cell>
                            <flux:table.cell>
                                <span class="text-xs text-zinc-500">{{ $port->origin_shipments_count + $port->destination_shipments_count }} {{ __('Total') }}</span>
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:dropdown align="end" position="bottom">
                                    <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" inset="top bottom" />
                                    <flux:menu>
                                        @can('ports.update')
                                            <flux:menu.item icon="pencil-square" wire:click="openEditModal({{ $port->id }})">{{ __('Edit') }}</flux:menu.item>
                                        @endcan
                                        @can('ports.delete')
                                            <flux:menu.item icon="trash" variant="danger" wire:click="openDeleteModal({{ $port->id }})">{{ __('Delete') }}</flux:menu.item>
                                        @endcan
                                    </flux:menu>
                                </flux:dropdown>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="5" class="text-center text-zinc-500 py-8">
                                {{ __('No ports found.') }}
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </x-crud.panel>
    </x-crud.page-shell>

    {{-- Create Modal --}}
    <flux:modal wire:model="showCreateModal" class="md:max-w-3xl">
        <form wire:submit="saveNewPort" class="space-y-6">
            <div class="flex items-center gap-3">
                <flux:icon name="map-pin" class="text-zinc-500" />
                <div>
                    <flux:heading size="lg">{{ __('Create Port') }}</flux:heading>
                    <flux:subheading>{{ __('Add a new port to the system.') }}</flux:subheading>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="sm:col-span-2">
                    <flux:input wire:model="name" :label="__('Port Name')" icon="map-pin" required />
                </div>
                <div class="sm:col-span-2">
                    <flux:input wire:model="code" :label="__('Port Code (e.g. USNY)')" class="uppercase font-mono" icon="qr-code" maxlength="50" required />
                </div>
                
                <div class="sm:col-span-2">
                    <x-select
                        wire:model.live="country_id"
                        :label="__('Country')"
                        :placeholder="__('Select country')"
                        option-value="id"
                        option-label="name"
                        :async-data="route('register.geo.countries')"
                        searchable
                    />
                </div>

                <div class="sm:col-span-1">
                    <x-select
                        wire:model.live="state_id"
                        :label="__('State / Province')"
                        :placeholder="__('Select state')"
                        option-value="id"
                        option-label="name"
                        :async-data="[
                            'api' => route('register.geo.states'),
                            'params' => ['country_id' => $this->country_id],
                        ]"
                        searchable
                        :disabled="!$this->country_id"
                    />
                </div>

                <div class="sm:col-span-1">
                    <x-select
                        wire:model.live="city_id"
                        :label="__('City')"
                        :placeholder="__('Select city')"
                        option-value="id"
                        option-label="name"
                        :async-data="[
                            'api' => route('register.geo.cities'),
                            'params' => ['state_id' => $this->state_id],
                        ]"
                        searchable
                        :disabled="!$this->state_id"
                    />
                </div>
            </div>

            <div class="flex justify-end gap-2 mt-4">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Create') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Edit Modal --}}
    <flux:modal wire:model="showEditModal" class="md:max-w-3xl">
        <form wire:submit="savePort" class="space-y-6">
            <div class="flex items-center gap-3">
                <flux:icon name="pencil-square" class="text-zinc-500" />
                <div>
                    <flux:heading size="lg">{{ __('Edit Port') }}</flux:heading>
                    <flux:subheading>{{ __('Update port details.') }}</flux:subheading>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="sm:col-span-2">
                    <flux:input wire:model="name" :label="__('Port Name')" icon="map-pin" required />
                </div>
                <div class="sm:col-span-2">
                    <flux:input wire:model="code" :label="__('Port Code')" icon="qr-code" class="uppercase font-mono" maxlength="50" required />
                </div>
                
                <div class="sm:col-span-2">
                    <x-select
                        wire:model.live="country_id"
                        :label="__('Country')"
                        :placeholder="__('Select country')"
                        option-value="id"
                        option-label="name"
                        :async-data="route('register.geo.countries')"
                        searchable
                    />
                </div>

                <div class="sm:col-span-1">
                    <x-select
                        wire:model.live="state_id"
                        :label="__('State / Province')"
                        :placeholder="__('Select state')"
                        option-value="id"
                        option-label="name"
                        :async-data="[
                            'api' => route('register.geo.states'),
                            'params' => ['country_id' => $this->country_id],
                        ]"
                        searchable
                        :disabled="!$this->country_id"
                    />
                </div>

                <div class="sm:col-span-1">
                    <x-select
                        wire:model.live="city_id"
                        :label="__('City')"
                        :placeholder="__('Select city')"
                        option-value="id"
                        option-label="name"
                        :async-data="[
                            'api' => route('register.geo.cities'),
                            'params' => ['state_id' => $this->state_id],
                        ]"
                        searchable
                        :disabled="!$this->state_id"
                    />
                </div>
            </div>

            <div class="flex justify-end gap-2 mt-4">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Save Changes') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Delete Modal --}}
    <flux:modal wire:model="showDeleteModal" class="max-w-md">
        <form wire:submit="deletePort" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Delete Port') }}</flux:heading>
                <flux:subheading>
                    {{ __('Are you sure you want to delete :label? This action cannot be undone.', ['label' => $portPendingDeleteLabel]) }}
                </flux:subheading>
            </div>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="danger">{{ __('Delete') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
