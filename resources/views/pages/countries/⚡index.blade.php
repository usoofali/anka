<?php

declare(strict_types=1);

use App\Models\Country;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use WireUi\Traits\WireUiActions;

new #[Title('Countries')] class extends Component {
    use WithPagination;
    use WireUiActions;

    public bool $showCreateModal = false;
    public bool $showEditModal = false;
    public ?int $countryPendingDeleteId = null;

    #[Url(as: 'q')]
    public string $search = '';

    // Fields for Create/Edit
    public ?int $editingCountryId = null;
    public string $name = '';
    public string $iso2 = '';
    public string $iso3 = '';

    public function mount(): void
    {
        $this->authorize('countries.view');
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function countries()
    {
        return Country::query()
            ->withCount('states')
            ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%")
                ->orWhere('iso2', 'like', "%{$this->search}%")
                ->orWhere('iso3', 'like', "%{$this->search}%"))
            ->orderBy('name')
            ->paginate(20);
    }

    public function openCreateModal(): void
    {
        $this->authorize('countries.create');
        $this->reset(['name', 'iso2', 'iso3', 'editingCountryId']);
        $this->showCreateModal = true;
    }

    public function saveNewCountry(): void
    {
        $this->authorize('countries.create');

        $validated = $this->validate([
            'name' => 'required|string|max:255|unique:countries,name',
            'iso2' => 'required|string|size:2|unique:countries,iso2',
            'iso3' => 'nullable|string|size:3|unique:countries,iso3',
        ]);

        $validated['iso3'] = $validated['iso3'] ?: null;

        Country::create($validated);

        $this->showCreateModal = false;
        $this->notification()->success(__('Country created successfully.'));
    }

    public function openEditModal(int $id): void
    {
        $this->authorize('countries.update');
        $country = Country::findOrFail($id);
        
        $this->editingCountryId = $country->id;
        $this->name = $country->name;
        $this->iso2 = $country->iso2;
        $this->iso3 = $country->iso3 ?? '';

        $this->showEditModal = true;
    }

    public function saveCountry(): void
    {
        $this->authorize('countries.update');

        $validated = $this->validate([
            'name' => 'required|string|max:255|unique:countries,name,' . $this->editingCountryId,
            'iso2' => 'required|string|size:2|unique:countries,iso2,' . $this->editingCountryId,
            'iso3' => 'nullable|string|size:3|unique:countries,iso3,' . $this->editingCountryId,
        ]);

        $validated['iso3'] = $validated['iso3'] ?: null;

        Country::findOrFail($this->editingCountryId)->update($validated);

        $this->showEditModal = false;
        $this->notification()->success(__('Country updated successfully.'));
    }

    public function openDeleteModal(int $id): void
    {
        $this->authorize('countries.delete');
        $this->countryPendingDeleteId = $id;
        $this->dispatch('modal-show', name: 'delete-country');
    }

    public function deleteCountry(): void
    {
        $this->authorize('countries.delete');
        
        if ($this->countryPendingDeleteId) {
            $country = Country::findOrFail($this->countryPendingDeleteId);
            
            if ($country->states()->exists()) {
                $this->notification()->warning(__('Cannot delete country with associated states.'));
            } else {
                $country->delete();
                $this->notification()->success(__('Country deleted successfully.'));
            }
        }

        $this->countryPendingDeleteId = null;
        $this->dispatch('modal-hide', name: 'delete-country');
    }
}; ?>

<div>
    <x-crud.page-shell>
        <div class="flex items-center justify-between mb-8">
            <x-crud.page-header :heading="__('Countries')" :subheading="__('Manage global countries and ISO codes.')" icon="flag" class="!mb-0" />
            @can('countries.create')
                <flux:button variant="primary" icon="plus" wire:click="openCreateModal">{{ __('Create Country') }}</flux:button>
            @endcan
        </div>

        <div class="mb-4">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Search by name or ISO code...')" clearable />
        </div>

        <x-crud.panel class="p-6">
            <flux:table :paginate="$this->countries">
                <flux:table.columns>
                    <flux:table.column icon="flag">{{ __('Name') }}</flux:table.column>
                    <flux:table.column>{{ __('ISO2') }}</flux:table.column>
                    <flux:table.column>{{ __('ISO3') }}</flux:table.column>
                    <flux:table.column>{{ __('States') }}</flux:table.column>
                    <flux:table.column align="right">{{ __('Actions') }}</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @foreach ($this->countries as $country)
                        <flux:table.row :key="$country->id">
                            <flux:table.cell class="font-medium">{{ $country->name }}</flux:table.cell>
                            <flux:table.cell class="font-mono text-xs uppercase">{{ $country->iso2 }}</flux:table.cell>
                            <flux:table.cell class="font-mono text-xs uppercase">{{ $country->iso3 ?: '—' }}</flux:table.cell>
                            <flux:table.cell>
                                <flux:badge size="sm" color="zinc" inset="left">{{ $country->states_count }}</flux:badge>
                            </flux:table.cell>
                            <flux:table.cell align="right">
                                <flux:dropdown align="end" variant="ghost">
                                    <flux:button variant="ghost" icon="ellipsis-horizontal" size="sm" />
                                    <flux:menu>
                                        @can('countries.update')
                                            <flux:menu.item icon="pencil-square" wire:click="openEditModal({{ $country->id }})">{{ __('Edit') }}</flux:menu.item>
                                        @endcan
                                        @can('countries.delete')
                                            <flux:menu.separator />
                                            <flux:menu.item icon="trash" variant="danger" wire:click="openDeleteModal({{ $country->id }})">{{ __('Delete') }}</flux:menu.item>
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
        <form wire:submit="saveNewCountry" class="space-y-6">
            <div class="flex items-center gap-3">
                <flux:icon name="flag" class="text-zinc-500" />
                <div>
                    <flux:heading size="lg">{{ __('Create Country') }}</flux:heading>
                    <flux:subheading>{{ __('Add a new country to the system.') }}</flux:subheading>
                </div>
            </div>

            <div class="space-y-4">
                <flux:input wire:model="name" :label="__('Country Name')" required placeholder="e.g. United States" />
                <div class="grid grid-cols-2 gap-4">
                    <flux:input wire:model="iso2" :label="__('ISO2 Code')" required placeholder="US" maxlength="2" class="uppercase font-mono" />
                    <flux:input wire:model="iso3" :label="__('ISO3 Code')" placeholder="USA" maxlength="3" class="uppercase font-mono" />
                </div>
            </div>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Save Country') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Edit Modal --}}
    <flux:modal wire:model="showEditModal" class="md:max-w-2xl">
        <form wire:submit="saveCountry" class="space-y-6">
            <div class="flex items-center gap-3">
                <flux:icon name="pencil-square" class="text-zinc-500" />
                <div>
                    <flux:heading size="lg">{{ __('Edit Country') }}</flux:heading>
                    <flux:subheading>{{ __('Update country details.') }}</flux:subheading>
                </div>
            </div>

            <div class="space-y-4">
                <flux:input wire:model="name" :label="__('Country Name')" required />
                <div class="grid grid-cols-2 gap-4">
                    <flux:input wire:model="iso2" :label="__('ISO2 Code')" required maxlength="2" class="uppercase font-mono" />
                    <flux:input wire:model="iso3" :label="__('ISO3 Code')" maxlength="3" class="uppercase font-mono" />
                </div>
            </div>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Update Country') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <x-modal name="delete-country" :title="__('Delete Country')">
        <div class="p-6">
            <p class="text-zinc-600 dark:text-zinc-400">
                {{ __('Are you sure you want to delete this country? This action cannot be undone.') }}
            </p>
            <div class="mt-6 flex justify-end gap-3">
                <flux:button variant="ghost" x-on:click="$dispatch('modal-hide', { name: 'delete-country' })">
                    {{ __('Cancel') }}
                </flux:button>
                <flux:button variant="danger" wire:click="deleteCountry">
                    {{ __('Delete Country') }}
                </flux:button>
            </div>
        </div>
    </x-modal>
</div>
