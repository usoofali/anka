<?php

declare(strict_types=1);

use Illuminate\Notifications\DatabaseNotification;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Notifications')] class extends Component {
    use WithPagination;

    public function markAsRead(string $id): void
    {
        /** @var DatabaseNotification|null $notification */
        $notification = auth()->user()
            ?->notifications()
            ->whereKey($id)
            ->first();

        if ($notification instanceof DatabaseNotification && $notification->unread()) {
            $notification->markAsRead();
        }
    }

    /**
     * @return array{notifications: \Illuminate\Contracts\Pagination\LengthAwarePaginator<DatabaseNotification>}
     */
    public function with(): array
    {
        $notifications = auth()->user()
            ?->notifications()
            ->latest()
            ->paginate(20);

        return [
            'notifications' => $notifications,
        ];
    }
}; ?>

<x-crud.page-shell>
    <x-crud.page-header
        :heading="__('Notifications')"
        :subheading="__('Stay updated with account activity and shipment events.')"
    />

    @if ($notifications->isEmpty())
        <x-crud.empty-state
            icon="bell"
            :title="__('No notifications yet')"
            :description="__('When there is activity, you will see it here.')"
        />
    @else
        <x-crud.panel class="p-6">
            <flux:table :paginate="$notifications">
                <flux:table.columns>
                    <flux:table.column>{{ __('Status') }}</flux:table.column>
                    <flux:table.column>{{ __('Notification') }}</flux:table.column>
                    <flux:table.column>{{ __('Date') }}</flux:table.column>
                    <flux:table.column align="right">{{ __('Action') }}</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @foreach ($notifications as $notification)
                        @php
                            /** @var DatabaseNotification $notification */
                            $data = $notification->data;
                            $title = data_get($data, 'title', __('Notification'));
                            $body = data_get($data, 'body', '');
                            $url = data_get($data, 'url');
                        @endphp
                        <flux:table.row :key="$notification->id">
                            <flux:table.cell>
                                @if ($notification->unread())
                                    <flux:badge size="sm" color="indigo" inset="left">{{ __('Unread') }}</flux:badge>
                                @else
                                    <flux:badge size="sm" color="zinc" inset="left">{{ __('Read') }}</flux:badge>
                                @endif
                            </flux:table.cell>

                            <flux:table.cell class="min-w-[20rem]">
                                <div class="space-y-1">
                                    @if ($url)
                                        <flux:link :href="$url" wire:navigate class="font-semibold text-zinc-900 dark:text-zinc-100">
                                            {{ $title }}
                                        </flux:link>
                                    @else
                                        <span class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $title }}</span>
                                    @endif

                                    @if ($body !== '')
                                        <p class="line-clamp-2 text-sm text-zinc-600 dark:text-zinc-300">{{ $body }}</p>
                                    @endif
                                </div>
                            </flux:table.cell>

                            <flux:table.cell class="text-xs text-zinc-500">
                                {{ $notification->created_at->diffForHumans() }}
                            </flux:table.cell>

                            <flux:table.cell align="right">
                                @if ($notification->unread())
                                    <flux:button
                                        size="xs"
                                        variant="primary"
                                        wire:click="markAsRead('{{ $notification->id }}')"
                                        wire:loading.attr="disabled"
                                    >
                                        {{ __('Mark as read') }}
                                    </flux:button>
                                @else
                                    <flux:badge size="sm" color="zinc" variant="outline">{{ __('Done') }}</flux:badge>
                                @endif
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </x-crud.panel>
    @endif
</x-crud.page-shell>

