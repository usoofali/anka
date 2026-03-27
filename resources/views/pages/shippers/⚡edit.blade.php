<?php

declare(strict_types=1);

use App\Concerns\HandlesShipperGeoSelects;
use App\Http\Requests\UpdateShipperRequest;
use App\Models\City;
use App\Models\Country;
use App\Models\Shipper;
use App\Models\State;
use App\Support\ShipperGeoValidator;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Edit shipper')] class extends Component {
    use HandlesShipperGeoSelects;

    public Shipper $shipper;

    public string $ownerName = '';

    public string $ownerEmail = '';

    public string $company_name = '';

    public string $phone = '';

    public string $address = '';

    public function mount(Shipper $shipper): void
    {
        $this->authorize('update', $shipper);

        $shipper->loadMissing('user');

        $this->ownerName = $shipper->user?->name ?? '';
        $this->ownerEmail = $shipper->user?->email ?? '';

        $this->company_name = $shipper->company_name ?? '';
        $this->phone = $shipper->phone ?? '';
        $this->address = $shipper->address ?? '';

        $this->country_id = $shipper->country_id !== null ? (int) $shipper->country_id : null;
        $this->state_id = $shipper->state_id !== null ? (int) $shipper->state_id : null;
        $this->city_id = $shipper->city_id !== null ? (int) $shipper->city_id : null;
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
     * @throws ValidationException
     */
    public function save(): void
    {
        $this->authorize('update', $this->shipper);

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

        $this->shipper->update($validated);

        session()->flash('toast', [
            'type' => 'success',
            'message' => __('Shipper updated.'),
        ]);

        $this->redirect(route('shippers.show', $this->shipper), navigate: true);
    }
}; ?>

@php
    $headerSub = $shipper->company_name
        ? $shipper->company_name.' — '.$ownerName
        : $ownerName;
@endphp

<x-crud.page-shell>
    <x-crud.page-header :heading="__('Edit shipper')" :subheading="$headerSub">
        <x-slot name="actions">
            <flux:button variant="ghost" :href="route('shippers.show', $shipper)" wire:navigate icon="eye">
                {{ __('View') }}
            </flux:button>
        </x-slot>
    </x-crud.page-header>

    <x-crud.panel variant="form" class="max-w-3xl">
        <form wire:submit="save" class="space-y-8">
            <div class="space-y-4">
                <flux:heading size="sm" level="3">{{ __('Account') }}</flux:heading>
                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                    {{ __('The sign-in account for this shipper. Contact support to change the email.') }}
                </flux:text>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="min-w-0">
                        <flux:text class="text-xs font-medium uppercase text-zinc-500">{{ __('Shipper name') }}</flux:text>
                        <flux:text class="mt-1 font-medium text-zinc-900 dark:text-zinc-100">{{ $ownerName ?: '—' }}</flux:text>
                    </div>
                    <div class="min-w-0 sm:col-span-1">
                        <flux:text class="text-xs font-medium uppercase text-zinc-500">{{ __('Email') }}</flux:text>
                        <flux:text class="mt-1 break-all font-medium text-zinc-900 dark:text-zinc-100">{{ $ownerEmail ?: '—' }}</flux:text>
                    </div>
                </div>
            </div>

            <flux:separator variant="subtle" />

            <div class="space-y-4">
                <flux:heading size="sm" level="3">{{ __('Company') }}</flux:heading>
                <flux:input wire:model="company_name" :label="__('Company name')" type="text" required autocomplete="organization" />
                <flux:input wire:model="phone" :label="__('Phone')" type="tel" required autocomplete="tel" />
                <flux:input wire:model="address" :label="__('Address')" type="text" required autocomplete="street-address" />
            </div>

            <flux:separator variant="subtle" />

            <div class="space-y-4">
                <flux:heading size="sm" level="3">{{ __('Business location') }}</flux:heading>
                <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                    <flux:select wire:model.live="country_id" :label="__('Country')">
                        <option value="">{{ __('Select country') }}</option>
                        @foreach ($this->countries as $country)
                            <option value="{{ $country->id }}">{{ $country->name }}</option>
                        @endforeach
                    </flux:select>

                    <flux:select wire:model.live="state_id" :label="__('State / region')" :disabled="! $country_id">
                        <option value="">{{ __('Select state') }}</option>
                        @foreach ($this->states as $state)
                            <option value="{{ $state->id }}">{{ $state->name }}</option>
                        @endforeach
                    </flux:select>

                    <flux:select wire:model.live="city_id" :label="__('City')" :disabled="! $state_id">
                        <option value="">{{ __('Select city') }}</option>
                        @foreach ($this->cities as $city)
                            <option value="{{ $city->id }}">{{ $city->name }}</option>
                        @endforeach
                    </flux:select>
                </div>
                <flux:error name="country_id" />
                <flux:error name="state_id" />
                <flux:error name="city_id" />
            </div>

            <div class="flex items-center justify-end gap-4 border-t border-zinc-200 pt-6 dark:border-zinc-700">
                <flux:button variant="ghost" :href="route('shippers.show', $shipper)" wire:navigate type="button">
                    {{ __('Cancel') }}
                </flux:button>
                <flux:button variant="primary" type="submit">
                    {{ __('Save changes') }}
                </flux:button>
            </div>
        </form>
    </x-crud.panel>
</x-crud.page-shell>
