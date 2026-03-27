<?php

declare(strict_types=1);

use App\Models\Shipper;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Shippers')] class extends Component {
    use WithPagination;

    public bool $showDeleteModal = false;

    public ?int $shipperPendingDeleteId = null;

    public string $shipperPendingDeleteLabel = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Shipper::class);
    }

    public function updatedShowDeleteModal(bool $value): void
    {
        if (! $value) {
            $this->shipperPendingDeleteId = null;
            $this->shipperPendingDeleteLabel = '';
        }
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

            session()->flash('toast', [
                'type' => 'success',
                'message' => __('Shipper removed.'),
            ]);

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

        session()->flash('toast', [
            'type' => 'success',
            'message' => __('Shipper and sign-in account removed.'),
        ]);

        $this->resetPage();
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
}; ?>

<x-crud.page-shell>
    <x-crud.page-header :heading="__('Shippers')" :subheading="__('Companies registered on the platform.')">
        <x-slot name="actions">
            @can('create', App\Models\Shipper::class)
                <flux:button variant="primary" :href="route('shippers.create')" wire:navigate icon="plus">
                    {{ __('Add shipper') }}
                </flux:button>
            @endcan
        </x-slot>
    </x-crud.page-header>

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
                        <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500">{{ __('Shipper') }}</th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500">{{ __('Company') }}</th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500">{{ __('Phone') }}</th>
                        <th scope="col" class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-zinc-500">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @foreach ($shippers as $shipper)
                        <tr wire:key="shipper-row-{{ $shipper->id }}" class="bg-white dark:bg-zinc-900">
                            <td class="px-4 py-4 align-top font-medium text-zinc-900 dark:text-zinc-100">
                                {{ $shipper->user?->name }}
                                <span class="block text-xs font-normal text-zinc-500">{{ $shipper->user?->email }}</span>
                            </td>
                            <td class="px-4 py-4 align-top text-zinc-600 dark:text-zinc-300">
                                {{ $shipper->company_name ?: '—' }}
                            </td>
                            <td class="px-4 py-4 align-top text-zinc-600 dark:text-zinc-300">
                                {{ $shipper->phone ?: '—' }}
                            </td>
                            <td class="whitespace-nowrap px-4 py-4 text-end align-top">
                                <div class="flex flex-wrap justify-end gap-2">
                                    @can('view', $shipper)
                                        <flux:button size="sm" variant="ghost" :href="route('shippers.show', $shipper)" wire:navigate>
                                            {{ __('View') }}
                                        </flux:button>
                                    @endcan
                                    @can('update', $shipper)
                                        <flux:button size="sm" variant="primary" :href="route('shippers.edit', $shipper)" wire:navigate>
                                            {{ __('Edit') }}
                                        </flux:button>
                                    @endcan
                                    @can('delete', $shipper)
                                        <flux:button
                                            size="sm"
                                            variant="danger"
                                            type="button"
                                            icon="trash"
                                            wire:click="openDeleteModal({{ $shipper->id }})"
                                            wire:key="delete-open-{{ $shipper->id }}"
                                        >
                                        </flux:button>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </x-crud.panel>

        <x-crud.pagination-shell>
            {{ $shippers->links() }}
        </x-crud.pagination-shell>
    @endif

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
