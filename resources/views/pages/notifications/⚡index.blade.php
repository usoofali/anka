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

<div class="mx-auto flex w-full max-w-5xl flex-col gap-6">
    <div class="flex flex-col gap-1">
        <flux:heading size="xl">{{ __('Notifications') }}</flux:heading>
        <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
            {{ __('Stay updated with account activity and shipment events.') }}
        </flux:text>
    </div>

    @if ($notifications->isEmpty())
        <div class="rounded-2xl border border-dashed border-zinc-300 bg-white px-6 py-12 text-center shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <flux:icon name="bell" class="mx-auto mb-3 h-10 w-10 text-zinc-400 dark:text-zinc-500" />
            <flux:heading size="md">{{ __('No notifications yet') }}</flux:heading>
            <flux:text class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                {{ __('When there is activity, you will see it here.') }}
            </flux:text>
        </div>
    @else
        <ul class="space-y-3">
            @foreach ($notifications as $notification)
                @php
                    /** @var DatabaseNotification $notification */
                    $data = $notification->data;
                    $title = data_get($data, 'title', __('Notification'));
                    $body = data_get($data, 'body', '');
                    $url = data_get($data, 'url');
                @endphp
                <li
                    class="rounded-2xl border bg-white p-4 shadow-sm transition hover:shadow-md dark:bg-zinc-900"
                    @class([
                        'border-zinc-200 dark:border-zinc-700' => ! $notification->unread(),
                        'border-indigo-200 dark:border-indigo-700/60' => $notification->unread(),
                    ])
                    wire:key="notification-{{ $notification->id }}"
                >
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2">
                                @if ($notification->unread())
                                    <span class="inline-flex h-2.5 w-2.5 rounded-full bg-indigo-500"></span>
                                    <flux:badge size="sm" color="indigo">{{ __('Unread') }}</flux:badge>
                                @endif
                            </div>

                            <div class="mt-2">
                                @if ($url)
                                    <flux:link :href="$url" wire:navigate class="text-base font-semibold text-zinc-900 dark:text-zinc-100">{{ $title }}</flux:link>
                                @else
                                    <span class="text-base font-semibold text-zinc-900 dark:text-zinc-100">{{ $title }}</span>
                                @endif
                            </div>

                            @if ($body !== '')
                                <flux:text class="mt-1.5 block text-sm leading-6 text-zinc-600 dark:text-zinc-300">{{ $body }}</flux:text>
                            @endif

                            <flux:text class="mt-2 text-xs text-zinc-500">{{ $notification->created_at->diffForHumans() }}</flux:text>
                        </div>

                        <div class="shrink-0">
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
                                <flux:text class="text-xs text-zinc-500">{{ __('Read') }}</flux:text>
                            @endif
                        </div>
                    </div>
                </li>
            @endforeach
        </ul>

        <div class="rounded-xl border border-zinc-200 bg-white px-4 py-3 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            {{ $notifications->links() }}
        </div>
    @endif
</div>
