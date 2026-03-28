<?php

declare(strict_types=1);

use App\Models\Country;
use App\Models\State;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use WireUi\Traits\WireUiActions;

new #[Title('States')] class extends Component {
    use WithPagination;
    use WireUiActions;

    public bool $showCreateModal = false;
    public bool $showEditModal = false;
    public ?int $statePendingDeleteId = null;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'country')]
    public ?int $filterCountryId = null;

    // Fields for Create/Edit
    public ?int $editingStateId = null;
    public ?int $country_id = null;
    public string $name = '';
    public string $code = '';

    public function mount(): void
    {
        $this->authorize('states.view');
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFilterCountryId(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function countries()
    {
        return Country::select('id', 'name')->orderBy('name')->get();
    }

    #[Computed]
    public function states()
    {
        return State::query()
            ->with('country')
            ->withCount('cities')
            ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%")
                ->orWhere('code', 'like', "%{$this->search}%"))
            ->when($this->filterCountryId, fn ($q) => $q->where('country_id', $this->filterCountryId))
            ->orderBy('name')
            ->paginate(20);
    }

    public function openCreateModal(): void
    {
        $this->authorize('states.create');
        $this->reset(['name', 'code', 'country_id', 'editingStateId']);
        $this->showCreateModal = true;
    }

    public function saveNewState(): void
    {
        $this->authorize('states.create');

        $validated = $this->validate([
            'country_id' => 'required|exists:countries,id',
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:10',
        ]);

        State::create($validated);

        $this->showCreateModal = false;
        $this->notification()->success(__('State created successfully.'));
    }

    public function openEditModal(int $id): void
    {
        $this->authorize('states.update');
        $state = State::findOrFail($id);
        
        $this->editingStateId = $state->id;
        $this->country_id = $state->country_id;
        $this->name = $state->name;
        $this->code = $state->code;

        $this->showEditModal = true;
    }

    public function saveState(): void
    {
        $this->authorize('states.update');

        $validated = $this->validate([
            'country_id' => 'required|exists:countries,id',
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:10',
        ]);

        State::findOrFail($this->editingStateId)->update($validated);

        $this->showEditModal = false;
        $this->notification()->success(__('State updated successfully.'));
    }

    public function openDeleteModal(int $id): void
    {
        $this->authorize('states.delete');
        $this->statePendingDeleteId = $id;
        $this->dispatch('modal-show', name: 'delete-state');
    }

    public function deleteState(): void
    {
        $this->authorize('states.delete');
        
        if ($this->statePendingDeleteId) {
            $state = State::findOrFail($this->statePendingDeleteId);
            
            if ($state->cities()->exists() || $state->ports()->exists()) {
                $this->notification()->warning(__('Cannot delete state with associated cities or ports.'));
            } else {
                $state->delete();
                $this->notification()->success(__('State deleted successfully.'));
            }
        }

        $this->statePendingDeleteId = null;
        $this->dispatch('modal-hide', name: 'delete-state');
    }
}; ?>

<div>
    <x-crud.page-shell>
        <div class="flex items-center justify-between mb-8">
            <x-crud.page-header :heading="__('States')" :subheading="__('Manage regions and states within countries.')" icon="map" class="!mb-0" />
            @can('states.create')
                <flux:button variant="primary" icon="plus" wire:click="openCreateModal">{{ __('Create State') }}</flux:button>
            @endcan
        </div>

        <div class="mb-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Search by name or code...')" clearable />
            <flux:select wire:model.live="filterCountryId" icon="flag">
                <option value="">{{ __('All Countries') }}</option>
                @foreach($this->countries as $country)
                    <option value="{{ $country->id }}">{{ $country->name }}</option>
                @endforeach
            </flux:select>
        </div>

        <x-crud.panel class="p-6">
            <flux:table :paginate="$this->states">
                <flux:table.columns>
                    <flux:table.column icon="map">{{ __('Name') }}</flux:table.column>
                    <flux:table.column>{{ __('Code') }}</flux:table.column>
                    <flux:table.column icon="flag">{{ __('Country') }}</flux:table.column>
                    <flux:table.column>{{ __('Cities') }}</flux:table.column>
                    <flux:table.column align="right">{{ __('Actions') }}</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @foreach ($this->states as $state)
                        <flux:table.row :key="$state->id">
                            <flux:table.cell class="font-medium">{{ $state->name }}</flux:table.cell>
                            <flux:table.cell class="font-mono text-xs uppercase">{{ $state->code }}</flux:table.cell>
                            <flux:table.cell>{{ $state->country->name }}</flux:table.cell>
                            <flux:table.cell>
                                <flux:badge size="sm" color="zinc" inset="left">{{ $state->cities_count }}</flux:badge>
                            </flux:table.cell>
                            <flux:table.cell align="right">
                                <flux:dropdown align="end" variant="ghost">
                                    <flux:button variant="ghost" icon="ellipsis-horizontal" size="sm" />
                                    <flux:menu>
                                        @can('states.update')
                                            <flux:menu.item icon="pencil-square" wire:click="openEditModal({{ $state->id }})">{{ __('Edit') }}</flux:menu.item>
                                        @endcan
                                        @can('states.delete')
                                            <flux:menu.separator />
                                            <flux:menu.item icon="trash" variant="danger" wire:click="openDeleteModal({{ $state->id }})">{{ __('Delete') }}</flux:menu.item>
                                        @endcan
                                    </flux:menu>
                                </flux:dropdown>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </x-crud.panel>
    </x-crud.page-shell>

    {{-- Create Modal --}}
    <flux:modal wire:model="showCreateModal" class="md:max-w-2xl">
        <form wire:submit="saveNewState" class="space-y-6">
            <div class="flex items-center gap-3">
                <flux:icon name="map" class="text-zinc-500" />
                <div>
                    <flux:heading size="lg">{{ __('Create State') }}</flux:heading>
                    <flux:subheading>{{ __('Add a new state/region.') }}</flux:subheading>
                </div>
            </div>

            <div class="space-y-4">
                <flux:select wire:model="country_id" :label="__('Country')" icon="flag" required>
                    <option value="">{{ __('Select Country') }}</option>
                    @foreach ($this->countries as $country)
                        <option value="{{ $country->id }}">{{ $country->name }}</option>
                    @endforeach
                </flux:select>
                <flux:input wire:model="name" :label="__('State Name')" required placeholder="e.g. California" />
                <flux:input wire:model="code" :label="__('State Code')" required placeholder="CA" class="uppercase font-mono" />
            </div>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Save State') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Edit Modal --}}
    <flux:modal wire:model="showEditModal" class="md:max-w-2xl">
        <form wire:submit="saveState" class="space-y-6">
            <div class="flex items-center gap-3">
                <flux:icon name="pencil-square" class="text-zinc-500" />
                <div>
                    <flux:heading size="lg">{{ __('Edit State') }}</flux:heading>
                    <flux:subheading>{{ __('Update state details.') }}</flux:subheading>
                </div>
            </div>

            <div class="space-y-4">
                <flux:select wire:model="country_id" :label="__('Country')" icon="flag" required>
                    <option value="">{{ __('Select Country') }}</option>
                    @foreach ($this->countries as $country)
                        <option value="{{ $country->id }}">{{ $country->name }}</option>
                    @endforeach
                </flux:select>
                <flux:input wire:model="name" :label="__('State Name')" required />
                <flux:input wire:model="code" :label="__('State Code')" required class="uppercase font-mono" />
            </div>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Update State') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <x-modal name="delete-state" :title="__('Delete State')">
        <div class="p-6">
            <p class="text-zinc-600 dark:text-zinc-400">
                {{ __('Are you sure you want to delete this state? This action cannot be undone.') }}
            </p>
            <div class="mt-6 flex justify-end gap-3">
                <flux:button variant="ghost" x-on:click="$dispatch('modal-hide', { name: 'delete-state' })">
                    {{ __('Cancel') }}
                </flux:button>
                <flux:button variant="danger" wire:click="deleteState">
                    {{ __('Delete State') }}
                </flux:button>
            </div>
        </div>
    </x-modal>
</div>
