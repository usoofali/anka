<?php

declare(strict_types=1);

use App\Concerns\HandlesShipperGeoSelects;
use App\Http\Requests\StoreShipperRequest;
use App\Models\Shipper;
use App\Models\User;
use App\Support\ShipperGeoValidator;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Add shipper')] class extends Component {
    use HandlesShipperGeoSelects;

    public ?int $user_id = null;

    public string $company_name = '';

    public string $phone = '';

    public string $address = '';

    public function mount(): void
    {
        $this->authorize('create', Shipper::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, User>
     */
    #[Computed]
    public function eligibleUsers()
    {
        return User::query()->whereDoesntHave('shipper')->orderBy('name')->get();
    }

    /**
     * @throws ValidationException
     */
    public function save(): void
    {
        $this->authorize('create', Shipper::class);

        $validator = Validator::make(
            [
                'user_id' => $this->user_id,
                'company_name' => $this->company_name,
                'phone' => $this->phone,
                'address' => $this->address,
                'country_id' => $this->country_id,
                'state_id' => $this->state_id,
                'city_id' => $this->city_id,
            ],
            app(StoreShipperRequest::class)->rules(),
        );
        $validator->after(function (\Illuminate\Validation\Validator $v): void {
            ShipperGeoValidator::assertHierarchy($v);
        });
        $validated = $validator->validate();

        Shipper::query()->create($validated);

        session()->flash('toast', [
            'type' => 'success',
            'message' => __('Shipper created.'),
        ]);

        $this->redirect(route('shippers.index'), navigate: true);
    }
}; ?>

<x-crud.page-shell>
    <x-crud.page-header :heading="__('Add shipper')" :subheading="__('Link a user account to a new company profile.')" />

    <x-crud.panel variant="form" class="max-w-3xl">
        <form wire:submit="save" class="space-y-8">
            <div class="space-y-4">
                <flux:heading size="sm" level="3">{{ __('Account') }}</flux:heading>
                @if ($this->eligibleUsers->isEmpty())
                    <flux:callout variant="warning" icon="exclamation-triangle">
                        {{ __('There are no users without an existing shipper profile. Create a user account first.') }}
                    </flux:callout>
                @else
                    <flux:select wire:model.live="user_id" :label="__('User')" :placeholder="__('Select user')" required>
                        <option value="">{{ __('Select user') }}</option>
                        @foreach ($this->eligibleUsers as $user)
                            <option value="{{ $user->id }}">{{ $user->name }} — {{ $user->email }}</option>
                        @endforeach
                    </flux:select>
                    <flux:error name="user_id" />
                @endif
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
                @include('components.shipper.geo-fields')
            </div>

            <div class="flex items-center justify-end gap-4 border-t border-zinc-200 pt-6 dark:border-zinc-700">
                <flux:button variant="ghost" :href="route('shippers.index')" wire:navigate type="button">
                    {{ __('Cancel') }}
                </flux:button>
                <flux:button variant="primary" type="submit" :disabled="$this->eligibleUsers->isEmpty()">
                    {{ __('Create shipper') }}
                </flux:button>
            </div>
        </form>
    </x-crud.panel>
</x-crud.page-shell>
