@props([
    'menuPosition' => 'top',
])

@php
    /** @var \Illuminate\Database\Eloquent\Collection<int, \Illuminate\Notifications\DatabaseNotification> $items */
    $items = auth()->user()->unreadNotifications()->latest()->limit(10)->get();
    $unreadCount = auth()->user()->unreadNotifications()->count();
    $badgeLabel = $unreadCount >= 10 ? '9+' : (string) $unreadCount;
    $badgeColorClasses = match (true) {
        $unreadCount === 0 => 'bg-red-500 text-white ring-red-100 dark:ring-red-900/40',
        $unreadCount >= 10 => 'bg-emerald-500 text-white ring-emerald-100 dark:ring-emerald-900/40',
        default => 'bg-amber-500 text-white ring-amber-100 dark:ring-amber-900/40',
    };
@endphp

<flux:dropdown :position="$menuPosition" align="end" {{ $attributes }}>
    <flux:button
        variant="ghost"
        size="sm"
        :square="true"
        icon="bell"
        class="relative shrink-0"
        data-test="notifications-menu-button"
        aria-label="{{ __('Notifications') }}"
    >
        <span
            @class([
                'absolute -end-1 -top-1 z-10 inline-flex h-4 min-w-4 items-center justify-center rounded-sm px-1 text-[9px] font-bold leading-none ring-1 ring-zinc-50 dark:ring-zinc-900',
                $badgeColorClasses,
            ])
            aria-hidden="true"
        >
            {{ $badgeLabel }}
        </span>
    </flux:button>

    <flux:menu class="max-h-80 min-w-72 overflow-y-auto">
        <div class="flex items-center justify-between gap-2 px-2 py-1.5">
            <flux:heading size="sm" class="px-0">{{ __('Notifications') }}</flux:heading>
            @if ($unreadCount > 0)
                <flux:badge size="sm" color="zinc">{{ $unreadCount }} {{ __('unread') }}</flux:badge>
            @endif
        </div>
        <flux:menu.separator />

        @if ($items->isEmpty())
            <div class="px-3 py-6 text-center">
                <flux:text class="text-sm text-zinc-500">{{ __('No unread notifications.') }}</flux:text>
            </div>
        @else
            <flux:menu.radio.group>
                @foreach ($items as $notification)
                    @php
                        $data = $notification->data;
                        $title = data_get($data, 'title', __('Notification'));
                        $body = data_get($data, 'body', '');
                        $baseUrl = data_get($data, 'url');
                        $href = null;
                        if ($baseUrl) {
                            $sep = str_contains($baseUrl, '?') ? '&' : '?';
                            $href = $baseUrl.$sep.'notification='.urlencode($notification->id);
                        }
                    @endphp
                    @if ($href)
                        <flux:menu.item class="items-start whitespace-normal" :href="$href" wire:navigate>
                            <div class="grid max-w-64 gap-0.5 text-start">
                                <span class="font-medium text-zinc-800 dark:text-zinc-100">{{ $title }}</span>
                                @if ($body !== '')
                                    <flux:text class="text-xs">{{ $body }}</flux:text>
                                @endif
                                <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">
                                    {{ $notification->created_at->diffForHumans() }}
                                </flux:text>
                            </div>
                        </flux:menu.item>
                    @else
                        <flux:menu.item class="items-start whitespace-normal">
                            <div class="grid max-w-64 gap-0.5 text-start">
                                <span class="font-medium text-zinc-800 dark:text-zinc-100">{{ $title }}</span>
                                @if ($body !== '')
                                    <flux:text class="text-xs">{{ $body }}</flux:text>
                                @endif
                                <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">
                                    {{ $notification->created_at->diffForHumans() }}
                                </flux:text>
                            </div>
                        </flux:menu.item>
                    @endif
                @endforeach
            </flux:menu.radio.group>
        @endif

        <flux:menu.separator />
        <flux:menu.radio.group>
            <flux:menu.item icon="arrow-top-right-on-square" :href="route('notifications.index')" wire:navigate>
                {{ __('View all notifications') }}
            </flux:menu.item>
        </flux:menu.radio.group>
    </flux:menu>
</flux:dropdown>
