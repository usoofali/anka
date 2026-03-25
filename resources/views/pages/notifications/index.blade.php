<x-layouts::app :title="__('Notifications')">
    <div class="flex flex-col gap-6">
        <flux:heading size="lg">{{ __('Notifications') }}</flux:heading>

        @if ($notifications->isEmpty())
            <flux:text>{{ __('No notifications yet.') }}</flux:text>
        @else
            <ul class="divide-y divide-zinc-200 rounded-xl border border-zinc-200 dark:divide-zinc-700 dark:border-zinc-700">
                @foreach ($notifications as $notification)
                    @php
                        /** @var \Illuminate\Notifications\DatabaseNotification $notification */
                        $data = $notification->data;
                        $title = data_get($data, 'title', __('Notification'));
                        $body = data_get($data, 'body', '');
                        $url = data_get($data, 'url');
                    @endphp
                    <li class="flex flex-col gap-2 px-4 py-4 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0 flex-1">
                            @if ($url)
                                <flux:link :href="$url" wire:navigate class="font-medium">{{ $title }}</flux:link>
                            @else
                                <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ $title }}</span>
                            @endif
                            @if ($body !== '')
                                <flux:text class="mt-1 block text-sm">{{ $body }}</flux:text>
                            @endif
                            <flux:text class="mt-1 text-xs text-zinc-500">{{ $notification->created_at->diffForHumans() }}</flux:text>
                        </div>
                        @if ($notification->unread())
                            <form method="POST" action="{{ route('notifications.read', $notification->id) }}" class="shrink-0">
                                @csrf
                                <flux:button size="sm" variant="ghost" type="submit">{{ __('Mark read') }}</flux:button>
                            </form>
                        @endif
                    </li>
                @endforeach
            </ul>

            <div>
                {{ $notifications->links() }}
            </div>
        @endif
    </div>
</x-layouts::app>
