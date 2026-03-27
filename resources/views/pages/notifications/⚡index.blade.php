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
        <x-crud.panel>
            <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                <thead class="bg-zinc-50 dark:bg-zinc-800/60">
                    <tr>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500">{{ __('Status') }}</th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500">{{ __('Notification') }}</th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500">{{ __('Date') }}</th>
                        <th scope="col" class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-zinc-500">{{ __('Action') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @foreach ($notifications as $notification)
                        @php
                            /** @var DatabaseNotification $notification */
                            $data = $notification->data;
                            $title = data_get($data, 'title', __('Notification'));
                            $body = data_get($data, 'body', '');
                            $url = data_get($data, 'url');
                        @endphp
                        <tr
                            wire:key="notification-{{ $notification->id }}"
                            @class([
                                'bg-indigo-50/50 dark:bg-indigo-950/20' => $notification->unread(),
                                'bg-white dark:bg-zinc-900' => ! $notification->unread(),
                            ])
                        >
                            <td class="whitespace-nowrap px-4 py-4 align-top">
                                @if ($notification->unread())
                                    <span class="inline-flex items-center gap-2 rounded-full bg-indigo-100 px-2.5 py-1 text-xs font-medium text-indigo-700 dark:bg-indigo-900/60 dark:text-indigo-200">
                                        <span class="h-2 w-2 rounded-full bg-indigo-500"></span>
                                        {{ __('Unread') }}
                                    </span>
                                @else
                                    <span class="inline-flex rounded-full bg-zinc-100 px-2.5 py-1 text-xs font-medium text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">
                                        {{ __('Read') }}
                                    </span>
                                @endif
                            </td>
                            <td class="min-w-[20rem] px-4 py-4 align-top">
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
                            </td>
                            <td class="whitespace-nowrap px-4 py-4 align-top text-xs text-zinc-500">
                                {{ $notification->created_at->diffForHumans() }}
                            </td>
                            <td class="whitespace-nowrap px-4 py-4 text-right align-top">
                                @if ($notification->unread())
                                    <flux:button
                                        size="sm"
                                        variant="primary"
                                        wire:click="markAsRead('{{ $notification->id }}')"
                                        wire:loading.attr="disabled"
                                    >
                                        {{ __('Mark as read') }}
                                    </flux:button>
                                @else
                                    <span class="text-xs text-zinc-500">{{ __('Done') }}</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </x-crud.panel>

        <x-crud.pagination-shell>
            {{ $notifications->links() }}
        </x-crud.pagination-shell>
    @endif
</x-crud.page-shell>
