<?php

declare(strict_types=1);

use App\Concerns\HandlesShipperGeoSelects;
use App\Http\Requests\UpdateShipperRequest;
use App\Models\City;
use App\Models\Country;
use App\Models\Shipper;
use App\Models\State;
use App\Support\ShipperGeoValidator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
use WireUi\Traits\WireUiActions;

new #[Title('Shippers')] class extends Component {
    use HandlesShipperGeoSelects;
    use WireUiActions;
    use WithPagination;

    public bool $showDeleteModal = false;

    public ?int $shipperPendingDeleteId = null;

    public string $shipperPendingDeleteLabel = '';

    public bool $showEditModal = false;

    public ?int $shipperEditingId = null;

    public string $ownerName = '';

    public string $ownerEmail = '';

    public string $company_name = '';

    public string $phone = '';

    public string $address = '';

    public function mount(Request $request): void
    {
        $this->authorize('viewAny', Shipper::class);

        $editQuery = $request->query('edit');
        if ($editQuery !== null && $editQuery !== '' && ctype_digit((string) $editQuery)) {
            $shipper = Shipper::query()->with('user')->whereKey((int) $editQuery)->first();
            if ($shipper !== null && auth()->user()?->can('update', $shipper)) {
                $this->populateEditFormFromShipper($shipper);
                $this->showEditModal = true;
            }
        }
    }

    public function updatedShowDeleteModal(bool $value): void
    {
        if (! $value) {
            $this->shipperPendingDeleteId = null;
            $this->shipperPendingDeleteLabel = '';
        }
    }

    public function updatedShowEditModal(bool $value): void
    {
        if (! $value) {
            $this->resetEditForm();
        }
    }

    public function openEditModal(int $shipperId): void
    {
        $shipper = Shipper::query()->with('user')->whereKey($shipperId)->firstOrFail();
        $this->authorize('update', $shipper);
        $this->populateEditFormFromShipper($shipper);
        $this->showEditModal = true;
    }

    /**
     * @throws ValidationException
     */
    public function saveShipper(): void
    {
        if ($this->shipperEditingId === null) {
            return;
        }

        $shipper = Shipper::query()->whereKey($this->shipperEditingId)->firstOrFail();
        $this->authorize('update', $shipper);

        $validator = Validator::make(
            [
                'company_name' => $this->company_name,
                'phone' => $this->phone,
                'address' => $this->address,
                'country_id' => $this->country_id,
                'state_id' => $this->state_id,
                'city_id' => $this->city_id,
            ],
            app(UpdateShipperRequest::class)->rules(),
        );
        $validator->after(function (\Illuminate\Validation\Validator $v): void {
            ShipperGeoValidator::assertHierarchy($v);
        });
        $validated = $validator->validate();

        $shipper->update($validated);

        $this->showEditModal = false;
        $this->resetEditForm();

        $this->notification()->success(__('Shipper updated.'));
    }

    public function openDeleteModal(int $shipperId): void
    {
        $shipper = Shipper::query()->with('user')->whereKey($shipperId)->firstOrFail();
        $this->authorize('delete', $shipper);

        $this->shipperPendingDeleteId = $shipper->id;
        $this->shipperPendingDeleteLabel = filled($shipper->company_name)
            ? (string) $shipper->company_name
            : (string) ($shipper->user?->name ?? __('Shipper #:id', ['id' => $shipper->id]));
        $this->showDeleteModal = true;
    }

    public function deleteShipper(): void
    {
        if ($this->shipperPendingDeleteId === null) {
            return;
        }

        $shipper = Shipper::query()->with('user')->whereKey($this->shipperPendingDeleteId)->firstOrFail();
        $this->authorize('delete', $shipper);

        $owner = $shipper->user;
        if ($owner === null) {
            $shipper->delete();

            $this->showDeleteModal = false;
            $this->shipperPendingDeleteId = null;
            $this->shipperPendingDeleteLabel = '';

            $this->notification()->success(__('Shipper removed.'));

            $this->resetPage();

            return;
        }

        DB::transaction(function () use ($owner): void {
            // Deleting the user cascades to shippers (and related shipper data) per migrations.
            $owner->delete();
        });

        $this->showDeleteModal = false;
        $this->shipperPendingDeleteId = null;
        $this->shipperPendingDeleteLabel = '';

        $this->notification()->success(__('Shipper and sign-in account removed.'));

        $this->resetPage();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Country>
     */
    #[Computed]
    public function countries()
    {
        return Country::query()->orderBy('name')->get();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, State>
     */
    #[Computed]
    public function states()
    {
        return State::query()
            ->when($this->country_id, fn ($query) => $query->where('country_id', $this->country_id))
            ->orderBy('name')
            ->get();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, City>
     */
    #[Computed]
    public function cities()
    {
        return City::query()
            ->when($this->state_id, fn ($query) => $query->where('state_id', $this->state_id))
            ->orderBy('name')
            ->get();
    }

    /**
     * @return array{shippers: LengthAwarePaginator<int, Shipper>}
     */
    public function with(): array
    {
        $user = auth()->user();
        $query = Shipper::query()->with(['user'])->latest();

        if ($user?->hasRole('super_admin') || $user?->staff()->exists()) {
            $shippers = $query->paginate(15);
        } elseif ($user?->shipper) {
            $shippers = $query->whereKey($user->shipper->id)->paginate(15);
        } else {
            abort(403);
        }

        return [
            'shippers' => $shippers,
        ];
    }

    private function populateEditFormFromShipper(Shipper $shipper): void
    {
        $shipper->loadMissing('user');

        $this->shipperEditingId = $shipper->id;
        $this->ownerName = $shipper->user?->name ?? '';
        $this->ownerEmail = $shipper->user?->email ?? '';
        $this->company_name = $shipper->company_name ?? '';
        $this->phone = $shipper->phone ?? '';
        $this->address = $shipper->address ?? '';
        $this->country_id = $shipper->country_id !== null ? (int) $shipper->country_id : null;
        $this->state_id = $shipper->state_id !== null ? (int) $shipper->state_id : null;
        $this->city_id = $shipper->city_id !== null ? (int) $shipper->city_id : null;
    }

    private function resetEditForm(): void
    {
        $this->shipperEditingId = null;
        $this->ownerName = '';
        $this->ownerEmail = '';
        $this->company_name = '';
        $this->phone = '';
        $this->address = '';
        $this->country_id = null;
        $this->state_id = null;
        $this->city_id = null;
    }
}; ?>

<x-crud.page-shell>
    <div class="flex items-center gap-3 mb-6">
        <div class="rounded-lg bg-zinc-100 p-2 dark:bg-zinc-800">
            <flux:icon.building-office-2 class="size-6 text-zinc-600 dark:text-zinc-400" />
        </div>
        <x-crud.page-header :heading="__('Shippers')" :subheading="__('Companies registered on the platform.')" class="!mb-0" />
    </div>

    @if ($shippers->isEmpty())
        <x-crud.empty-state
            icon="building-office-2"
            :title="__('No shippers yet')"
            :description="__('When companies are registered, they will appear here.')"
        />
    @else
        <x-crud.panel>
            <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                <thead class="bg-zinc-50 dark:bg-zinc-800/60">
                    <tr>
                        <th scope="col" class="whitespace-nowrap px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500">{{ __('Shipper') }}</th>
                        <th scope="col" class="whitespace-nowrap px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500">{{ __('Company') }}</th>
                        <th scope="col" class="whitespace-nowrap px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500">{{ __('Phone') }}</th>
                        <th scope="col" class="whitespace-nowrap px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-zinc-500">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @foreach ($shippers as $shipper)
                        <tr wire:key="shipper-row-{{ $shipper->id }}" class="bg-white dark:bg-zinc-900 hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition-colors">
                            <td class="whitespace-nowrap px-4 py-4 align-middle">
                                <div class="flex items-center gap-3">
                                    <div class="flex size-9 items-center justify-center rounded-lg bg-primary-50 text-primary-600 dark:bg-primary-950/30 dark:text-primary-400">
                                        <flux:icon.user variant="mini" />
                                    </div>
                                    <div class="flex flex-col">
                                        <span class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $shipper->user?->name }}</span>
                                        @if (filled($shipper->user?->email))
                                            <span class="text-xs text-zinc-500 dark:text-zinc-400">{{ $shipper->user?->email }}</span>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td class="whitespace-nowrap px-4 py-4 align-middle">
                                <div class="flex items-center gap-2">
                                    <flux:icon.building-office variant="mini" class="text-zinc-400" />
                                    <span class="font-medium text-zinc-700 dark:text-zinc-300">{{ $shipper->company_name ?: '—' }}</span>
                                </div>
                            </td>
                            <td class="whitespace-nowrap px-4 py-4 align-middle">
                                <div class="flex items-center gap-2">
                                    <flux:icon.phone variant="mini" class="text-zinc-400" />
                                    <span class="text-zinc-600 dark:text-zinc-400">{{ $shipper->phone ?: '—' }}</span>
                                </div>
                            </td>
                            <td class="whitespace-nowrap px-4 py-4 text-end align-middle">
                                <div class="inline-flex items-center justify-end gap-1">
                                    @can('view', $shipper)
                                        <flux:button
                                            size="sm"
                                            variant="ghost"
                                            icon="eye"
                                            :href="route('shippers.show', $shipper)"
                                            wire:navigate
                                            :tooltip="__('View')"
                                        />
                                    @endcan
                                    @can('update', $shipper)
                                        <flux:button
                                            size="sm"
                                            variant="ghost"
                                            icon="pencil-square"
                                            wire:click="openEditModal({{ $shipper->id }})"
                                            wire:key="edit-open-{{ $shipper->id }}"
                                            :tooltip="__('Edit')"
                                        />
                                    @endcan
                                    @can('delete', $shipper)
                                        <flux:button
                                            size="sm"
                                            variant="ghost"
                                            icon="trash"
                                            color="red"
                                            wire:click="openDeleteModal({{ $shipper->id }})"
                                            wire:key="delete-open-{{ $shipper->id }}"
                                            :tooltip="__('Delete')"
                                        />
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </x-crud.panel>

        <x-crud.pagination-shell class="mt-6">
            {{ $shippers->links() }}
        </x-crud.pagination-shell>
    @endif

    <flux:modal wire:model.self="showEditModal" class="max-w-4xl">
        <div class="max-h-[85vh] space-y-8 overflow-y-auto px-1 pb-4">
            <div class="flex items-start gap-4 border-b border-zinc-100 pb-6 dark:border-zinc-800">
                <div class="rounded-xl bg-primary-50 p-3 text-primary-600 dark:bg-primary-950/20 dark:text-primary-400">
                    <flux:icon.pencil-square class="size-8" />
                </div>
                <div>
                    <flux:heading size="xl" weight="bold">{{ __('Edit Shipper Profile') }}</flux:heading>
                    @if ($ownerName !== '' || $ownerEmail !== '')
                        <flux:subheading class="mt-1 flex items-center gap-2">
                            <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ $company_name ?: $ownerName }}</span>
                            <span class="text-zinc-400 dark:text-zinc-500">•</span>
                            <span>{{ $ownerEmail }}</span>
                        </flux:subheading>
                    @endif
                </div>
            </div>

            <form wire:submit="saveShipper" class="space-y-8">
                {{-- Account Section --}}
                <div class="space-y-4">
                    <div class="flex items-center gap-2">
                        <flux:icon.user-circle variant="mini" class="text-zinc-400" />
                        <flux:heading size="sm" weight="semibold" class="uppercase tracking-wider text-zinc-500">{{ __('Account Information') }}</flux:heading>
                    </div>

                    <flux:card class="bg-zinc-50/50 dark:bg-zinc-900/50 border-zinc-100 dark:border-zinc-800">
                        <div class="grid gap-6 sm:grid-cols-2">
                            <div class="space-y-1">
                                <flux:text size="xs" weight="medium" class="uppercase tracking-widest text-zinc-400">{{ __('Primary Contact') }}</flux:text>
                                <flux:text class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $ownerName ?: '—' }}</flux:text>
                            </div>
                            <div class="space-y-1">
                                <flux:text size="xs" weight="medium" class="uppercase tracking-widest text-zinc-400">{{ __('Email Address') }}</flux:text>
                                <flux:text class="break-all font-semibold text-zinc-900 dark:text-zinc-100">{{ $ownerEmail ?: '—' }}</flux:text>
                            </div>
                        </div>
                        <flux:text size="xs" class="mt-4 italic text-zinc-400">
                            {{ __('Note: Account details are managed by administrators.') }}
                        </flux:text>
                    </flux:card>
                </div>

                {{-- Company Section --}}
                <div class="space-y-4">
                    <div class="flex items-center gap-2">
                        <flux:icon.building-office variant="mini" class="text-zinc-400" />
                        <flux:heading size="sm" weight="semibold" class="uppercase tracking-wider text-zinc-500">{{ __('Company Details') }}</flux:heading>
                    </div>

                    <flux:card class="space-y-6 border-zinc-100 dark:border-zinc-800">
                        <div class="grid gap-6 sm:grid-cols-2">
                            <flux:input 
                                wire:model="company_name" 
                                :label="__('Display Name')" 
                                type="text" 
                                required 
                                autocomplete="organization" 
                                icon="building-office-2"
                                placeholder="{{ __('Enter company name') }}"
                            />
                            <flux:input 
                                wire:model="phone" 
                                :label="__('Contact Phone')" 
                                type="tel" 
                                required 
                                autocomplete="tel" 
                                icon="phone"
                                placeholder="+1 (555) 000-0000"
                            />
                        </div>
                        <flux:input 
                            wire:model="address" 
                            :label="__('Street Address')" 
                            type="text" 
                            required 
                            autocomplete="street-address" 
                            icon="map-pin"
                            placeholder="{{ __('123 Business Way, Suite 100') }}"
                        />
                    </flux:card>
                </div>

                {{-- Business Location Section --}}
                <div class="space-y-4">
                    <div class="flex items-center gap-2">
                        <flux:icon.globe-alt variant="mini" class="text-zinc-400" />
                        <flux:heading size="sm" weight="semibold" class="uppercase tracking-wider text-zinc-500">{{ __('Geographic Location') }}</flux:heading>
                    </div>

                    <flux:card class="bg-zinc-50/20 dark:bg-zinc-900/10 border-zinc-100 dark:border-zinc-800">
                        <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
                            <flux:select wire:model.live="country_id" :label="__('Country')" icon="flag">
                                <option value="">{{ __('Select country') }}</option>
                                @foreach ($this->countries as $country)
                                    <option value="{{ $country->id }}">{{ $country->name }}</option>
                                @endforeach
                            </flux:select>

                            <flux:select wire:model.live="state_id" :label="__('State / Region')" :disabled="! $country_id" icon="map">
                                <option value="">{{ __('Select state') }}</option>
                                @foreach ($this->states as $state)
                                    <option value="{{ $state->id }}">{{ $state->name }}</option>
                                @endforeach
                            </flux:select>

                            <flux:select wire:model.live="city_id" :label="__('City / Town')" :disabled="! $state_id" icon="building-library">
                                <option value="">{{ __('Select city') }}</option>
                                @foreach ($this->cities as $city)
                                    <option value="{{ $city->id }}">{{ $city->name }}</option>
                                @endforeach
                            </flux:select>
                        </div>
                        <div class="mt-2 space-y-1">
                            <flux:error name="country_id" />
                            <flux:error name="state_id" />
                            <flux:error name="city_id" />
                        </div>
                    </flux:card>
                </div>

                <div class="flex items-center justify-end gap-3 border-t border-zinc-100 pt-8 dark:border-zinc-800">
                    <flux:modal.close>
                        <flux:button variant="ghost" type="button" icon="x-mark">{{ __('Cancel') }}</flux:button>
                    </flux:modal.close>
                    <flux:button variant="primary" type="submit" icon="check" wire:loading.attr="disabled" class="px-8">
                        {{ __('Save Changes') }}
                    </flux:button>
                </div>
            </form>
        </div>
    </flux:modal>

    <flux:modal wire:model.self="showDeleteModal" class="max-w-md">
        <div class="space-y-4">
            <flux:heading size="lg">{{ __('Delete shipper?') }}</flux:heading>
            <flux:subheading>
                {{ __('This will permanently delete the shipper profile, the owner’s sign-in account, and related data. This cannot be undone.') }}
            </flux:subheading>
            @if ($shipperPendingDeleteLabel !== '')
                <flux:text class="font-medium text-zinc-900 dark:text-zinc-100">
                    {{ $shipperPendingDeleteLabel }}
                </flux:text>
            @endif
        </div>

        <div class="mt-6 flex justify-end gap-2">
            <flux:modal.close>
                <flux:button variant="filled" type="button">{{ __('Cancel') }}</flux:button>
            </flux:modal.close>
            <flux:button variant="danger" type="button" wire:click="deleteShipper" wire:loading.attr="disabled">
                {{ __('Delete') }}
            </flux:button>
        </div>
    </flux:modal>
</x-crud.page-shell>
