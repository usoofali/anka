<?php

declare(strict_types=1);

use App\Models\Prealert;
use App\Models\Shipper;
use App\Enums\PrealertStatus;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Prealerts')] class extends Component {
    use WithPagination;

    #[Url(as: 'status')]
    public string $statusFilter = '';

    #[Url(as: 'shipper')]
    public string $shipperFilter = '';

    public function mount(): void
    {
        // Access control: Shippers see theirs, Staff see all.
        // Handled in the query.
    }

    #[Computed]
    public function prealerts()
    {
        $user = Auth::user();
        $query = Prealert::query()
            ->with(['shipper.user', 'vehicle', 'carrier', 'destinationPort'])
            ->latest();

        if ($user?->staff()->exists() || $user?->hasRole('super_admin')) {
            if ($this->shipperFilter) {
                $query->where('shipper_id', $this->shipperFilter);
            }
        } else {
            // It's a shipper
            $shipperId = $user?->shipper?->id;
            if ($shipperId) {
                $query->where('shipper_id', $shipperId);
            } else {
                return collect(); // Or handle error
            }
        }

        if ($this->statusFilter) {
            $query->where('status', $this->statusFilter);
        }

        return $query->paginate(15);
    }

    #[Computed]
    public function shippers()
    {
        if (!Auth::user()?->hasRole('super_admin') && !Auth::user()?->staff()->exists()) {
            return collect();
        }

        return Shipper::query()->with('user:id,name')->get()->map(fn($s) => [
            'id' => $s->id,
            'name' => $s->company_name ?: $s->user?->name,
        ]);
    }

    public ?Prealert $selectedPrealert = null;
    public string $rejectionReason = '';

    public function openReviewModal(int $id): void
    {
        $this->selectedPrealert = Prealert::with(['shipper.user', 'vehicle', 'carrier', 'destinationPort'])->findOrFail($id);

        if ($this->selectedPrealert->status === PrealertStatus::Submitted) {
            $this->selectedPrealert->update([
                'status' => PrealertStatus::UnderReview,
                'reviewed_by' => Auth::id(),
            ]);
        }

        $this->rejectionReason = '';
        $this->dispatch('modal-show', name: 'review-prealert');
    }

    public function approvePrealert(): void
    {
        if (!$this->selectedPrealert)
            return;

        $this->selectedPrealert->update([
            'status' => PrealertStatus::Approved,
            'reviewed_by' => Auth::id(),
        ]);

        $this->dispatch('notify', [
            'title' => __('Approved'),
            'description' => __('Prealert has been approved.'),
            'icon' => 'check-circle',
            'iconColor' => 'text-green-500',
        ]);

        $this->dispatch('modal-hide', name: 'review-prealert');
        $this->selectedPrealert = null;
    }

    public function rejectPrealert(): void
    {
        if (!$this->selectedPrealert)
            return;

        $this->validate([
            'rejectionReason' => ['required', 'string', 'min:5'],
        ]);

        $this->selectedPrealert->update([
            'status' => PrealertStatus::Rejected,
            'rejection_reason' => $this->rejectionReason,
            'reviewed_by' => Auth::id(),
        ]);

        $this->dispatch('notify', [
            'title' => __('Rejected'),
            'description' => __('Prealert has been rejected.'),
            'icon' => 'x-circle',
            'iconColor' => 'text-red-500',
        ]);

        $this->dispatch('modal-hide', name: 'review-prealert');
        $this->selectedPrealert = null;
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedShipperFilter(): void
    {
        $this->resetPage();
    }
}; ?>

<x-crud.page-shell>
    <div class="flex items-center justify-between mb-6">
        <div class="flex items-center gap-3">
            <div class="rounded-lg bg-zinc-100 p-2 dark:bg-zinc-800">
                <flux:icon.bell class="size-6 text-zinc-600 dark:text-zinc-400" />
            </div>
            <x-crud.page-header :heading="__('Prealerts')" :subheading="__('Incoming vehicle alerts and submissions.')"
                class="!mb-0" />
        </div>

        <flux:button variant="primary" icon="plus" :href="route('prealerts.create')" wire:navigate>
            {{ __('New Prealert') }}
        </flux:button>
    </div>

    <div class="mb-6 flex flex-wrap items-center gap-4">
        <div class="flex-1 min-w-[200px]">
            <flux:select wire:model.live="statusFilter" :label="__('Filter by Status')"
                placeholder="{{ __('All Statuses') }}">
                <flux:select.option value="">{{ __('All Statuses') }}</flux:select.option>
                @foreach (\App\Enums\PrealertStatus::cases() as $status)
                    <flux:select.option value="{{ $status->value }}">{{ str($status->value)->headline() }}
                    </flux:select.option>
                @endforeach
            </flux:select>
        </div>

        @if (Auth::user()?->hasRole('super_admin') || Auth::user()?->staff()->exists())
            <div class="flex-1 min-w-[200px]">
                <flux:select wire:model.live="shipperFilter" :label="__('Filter by Shipper')"
                    placeholder="{{ __('All Shippers') }}">
                    <flux:select.option value="">{{ __('All Shippers') }}</flux:select.option>
                    @foreach ($this->shippers as $shipper)
                        <flux:select.option value="{{ $shipper['id'] }}">{{ $shipper['name'] }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>
        @endif
    </div>

    @if ($this->prealerts->isEmpty())
        <x-crud.empty-state icon="bell-slash" :title="__('No prealerts found')" :description="__('Try adjusting your filters or create a new prealert.')" />
    @else
        <x-crud.panel>
            <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                <thead class="bg-zinc-50 dark:bg-zinc-800/60">
                    <tr>
                        <th scope="col"
                            class="whitespace-nowrap px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500">
                            {{ __('VIN') }}</th>
                        <th scope="col"
                            class="whitespace-nowrap px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500">
                            {{ __('Shipper') }}</th>
                        <th scope="col"
                            class="whitespace-nowrap px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500">
                            {{ __('Vehicle') }}</th>
                        <th scope="col"
                            class="whitespace-nowrap px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500">
                            {{ __('Status') }}</th>
                        <th scope="col"
                            class="whitespace-nowrap px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500">
                            {{ __('Submitted') }}</th>
                        <th scope="col"
                            class="whitespace-nowrap px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-zinc-500">
                            {{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @foreach ($this->prealerts as $prealert)
                        <tr wire:key="prealert-row-{{ $prealert->id }}"
                            class="bg-white dark:bg-zinc-900 hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition-colors">
                            <td class="whitespace-nowrap px-4 py-4 align-middle font-mono text-xs font-semibold">
                                {{ $prealert->vin }}
                            </td>
                            <td class="whitespace-nowrap px-4 py-4 align-middle">
                                <span class="font-medium text-zinc-700 dark:text-zinc-300">
                                    {{ $prealert->shipper?->company_name ?: $prealert->shipper?->user?->name }}
                                </span>
                            </td>
                            <td class="whitespace-nowrap px-4 py-4 align-middle">
                                @if ($prealert->vehicle)
                                    <span class="text-zinc-600 dark:text-zinc-400">
                                        {{ $prealert->vehicle->year }} {{ $prealert->vehicle->make }}
                                        {{ $prealert->vehicle->model }}
                                    </span>
                                @else
                                    <span class="text-zinc-400 italic">{{ __('N/A') }}</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-4 py-4 align-middle">
                                @php
                                    $color = match ($prealert->status) {
                                        \App\Enums\PrealertStatus::Approved => 'green',
                                        \App\Enums\PrealertStatus::Rejected => 'red',
                                        \App\Enums\PrealertStatus::Draft => 'zinc',
                                        \App\Enums\PrealertStatus::Submitted => 'blue',
                                        default => 'yellow',
                                    };
                                @endphp
                                <flux:badge size="sm" :color="$color" variant="subtle">
                                    {{ str($prealert->status->value)->headline() }}
                                </flux:badge>
                            </td>
                            <td class="whitespace-nowrap px-4 py-4 align-middle text-zinc-500">
                                {{ $prealert->submitted_at?->diffForHumans() ?: '—' }}
                            </td>
                            <td class="whitespace-nowrap px-4 py-4 text-end align-middle">
                                <div class="inline-flex items-center justify-end gap-1">
                                    <flux:button size="sm" variant="ghost" icon="eye" :tooltip="__('View Details')" />
                                    @if (Auth::user()?->hasRole('super_admin') || Auth::user()?->staff()->exists())
                                        @if ($prealert->status !== \App\Enums\PrealertStatus::Approved && $prealert->status !== \App\Enums\PrealertStatus::Rejected)
                                            <flux:button size="sm" variant="ghost" icon="check-badge"
                                                wire:click="openReviewModal({{ $prealert->id }})" :tooltip="__('Review Prealert')" />
                                        @endif
                                    @endif
                                    @if ($prealert->status === \App\Enums\PrealertStatus::Draft || $prealert->status === \App\Enums\PrealertStatus::Rejected)
                                        <flux:button size="sm" variant="ghost" icon="pencil-square"
                                            :href="route('prealerts.edit', $prealert)" wire:navigate
                                            :tooltip="__('Edit Prealert')" />
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </x-crud.panel>

        <x-crud.pagination-shell class="mt-6">
            {{ $this->prealerts->links() }}
        </x-crud.pagination-shell>
    @endif

    {{-- Review Modal --}}
    <flux:modal name="review-prealert" variant="large" class="space-y-6">
        @if ($selectedPrealert)
            <div>
                <flux:heading size="lg">{{ __('Review Prealert') }}</flux:heading>
                <flux:subheading>{{ __('Carefully review the vehicle and documentation before approving.') }}
                </flux:subheading>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="space-y-4">
                    <x-crud.panel class="p-4 bg-zinc-50 dark:bg-zinc-800/50">
                        <flux:heading size="sm" class="mb-2 uppercase tracking-wider text-zinc-500">
                            {{ __('Vehicle Information') }}</flux:heading>
                        @if ($selectedPrealert->vehicle)
                            <div class="font-bold text-lg">{{ $selectedPrealert->vehicle->year }}
                                {{ $selectedPrealert->vehicle->make }} {{ $selectedPrealert->vehicle->model }}</div>
                            <div class="text-sm text-zinc-600 dark:text-zinc-400 font-mono mt-1">{{ __('VIN') }}:
                                {{ $selectedPrealert->vin }}</div>
                        @else
                            <div class="text-sm text-zinc-600 dark:text-zinc-400">{{ __('VIN') }}: {{ $selectedPrealert->vin }}
                            </div>
                            <div class="text-xs text-red-500 mt-1 italic">{{ __('Vehicle data not fetched.') }}</div>
                        @endif
                    </x-crud.panel>

                    <div class="space-y-2">
                        <flux:label size="sm" class="uppercase tracking-wider text-zinc-500">{{ __('Logistics') }}
                        </flux:label>
                        <div class="text-sm">
                            <span class="font-semibold">{{ __('Carrier') }}:</span>
                            {{ $selectedPrealert->carrier?->name ?: '—' }}
                        </div>
                        <div class="text-sm">
                            <span class="font-semibold">{{ __('Destination') }}:</span>
                            {{ $selectedPrealert->destinationPort?->name ?: '—' }}
                            ({{ $selectedPrealert->destinationPort?->code ?: '—' }})
                        </div>
                        @if ($selectedPrealert->gatepass_pin)
                            <div class="text-sm">
                                <span class="font-semibold">{{ __('Gatepass PIN') }}:</span> <span
                                    class="font-mono">{{ $selectedPrealert->gatepass_pin }}</span>
                            </div>
                        @endif
                    </div>
                </div>

                <div class="space-y-4">
                    <flux:label size="sm" class="uppercase tracking-wider text-zinc-500">{{ __('Action Receipt') }}
                    </flux:label>
                    @if ($selectedPrealert->auction_receipt)
                        <div
                            class="rounded-xl overflow-hidden border border-zinc-200 dark:border-zinc-700 shadow-sm transition-transform hover:scale-[1.02]">
                            <img src="{{ Storage::url($selectedPrealert->auction_receipt) }}"
                                class="w-full h-auto max-h-64 object-cover" alt="Action Receipt">
                            <div
                                class="p-2 bg-white dark:bg-zinc-900 border-t border-zinc-200 dark:border-zinc-700 text-center">
                                <flux:link :href="Storage::url($selectedPrealert->auction_receipt)" target="_blank" size="xs"
                                    icon="magnifying-glass-plus">{{ __('View Full Size') }}</flux:link>
                            </div>
                        </div>
                    @else
                        <div
                            class="h-40 flex items-center justify-center bg-zinc-100 dark:bg-zinc-800 rounded-xl border-2 border-dashed border-zinc-300 dark:border-zinc-700">
                            <span class="text-zinc-400 italic text-sm">{{ __('No receipt uploaded') }}</span>
                        </div>
                    @endif
                </div>

                <div class="md:col-span-2">
                    <flux:label size="sm" class="uppercase tracking-wider text-zinc-500">{{ __('Shipper Notes') }}
                    </flux:label>
                    <div
                        class="mt-1 p-3 bg-white dark:bg-zinc-900 rounded-lg border border-zinc-200 dark:border-zinc-700 text-sm text-zinc-700 dark:text-zinc-300 min-h-[60px]">
                        {{ $selectedPrealert->notes ?: __('No notes provided.') }}
                    </div>
                </div>

                {{-- Rejection Form --}}
                <div x-data="{ showRejection: false }" class="md:col-span-2 mt-4 space-y-4">
                    <div x-show="!showRejection" class="flex justify-end gap-3">
                        <flux:button variant="ghost" x-on:click="$dispatch('modal-hide', { name: 'review-prealert' })">
                            {{ __('Close') }}</flux:button>
                        <flux:button variant="danger" icon="x-circle" x-on:click="showRejection = true">{{ __('Reject') }}
                        </flux:button>
                        <flux:button variant="primary" icon="check-circle" wire:click="approvePrealert">
                            {{ __('Approve Prealert') }}</flux:button>
                    </div>

                    <div x-show="showRejection" class="animate-in fade-in slide-in-from-bottom-2 space-y-4">
                        <flux:textarea wire:model="rejectionReason" :label="__('Rejection Reason')"
                            placeholder="{{ __('Explain why this prealert is being rejected...') }}" rows="3" required />
                        <div class="flex justify-end gap-2">
                            <flux:button size="sm" variant="ghost" x-on:click="showRejection = false">{{ __('Back') }}
                            </flux:button>
                            <flux:button size="sm" variant="danger" icon="x-circle" wire:click="rejectPrealert">
                                {{ __('Confirm Rejection') }}</flux:button>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </flux:modal>
</x-crud.page-shell>