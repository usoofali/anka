@props([
    'unreadCount' => 0,
    'menuPosition' => 'top',
])

@php
    /** @var list<array{title: string, body: string, time: string}> $notifications */
    $notifications = [
        [
            'title' => __('Shipment REF-2401 updated'),
            'body' => __('Status changed to Inland'),
            'time' => __('2 hours ago'),
        ],
        [
            'title' => __('Invoice INV-1008 ready'),
            'body' => __('Your invoice is available to view'),
            'time' => __('Yesterday'),
        ],
    ];
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
        @if ($unreadCount > 0)
            <span
                class="absolute end-0 top-0 flex min-h-4 min-w-4 translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full bg-accent-600 px-1 text-[10px] font-semibold leading-none text-white ring-2 ring-zinc-50 dark:ring-zinc-900"
                aria-hidden="true"
            >
                {{ $unreadCount > 9 ? '9+' : $unreadCount }}
            </span>
        @endif
    </flux:button>

    <flux:menu class="max-h-80 min-w-72 overflow-y-auto">
        <div class="flex items-center justify-between gap-2 px-2 py-1.5">
            <flux:heading size="sm" class="px-0">{{ __('Notifications') }}</flux:heading>
            @if ($unreadCount > 0)
                <flux:badge size="sm" color="zinc">{{ $unreadCount }} {{ __('unread') }}</flux:badge>
            @endif
        </div>
        <flux:menu.separator />

        <flux:menu.radio.group>
            @foreach ($notifications as $note)
                <flux:menu.item class="items-start whitespace-normal">
                    <div class="grid max-w-64 gap-0.5 text-start">
                        <span class="font-medium text-zinc-800 dark:text-zinc-100">{{ $note['title'] }}</span>
                        <flux:text class="text-xs">{{ $note['body'] }}</flux:text>
                        <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">{{ $note['time'] }}</flux:text>
                    </div>
                </flux:menu.item>
            @endforeach
        </flux:menu.radio.group>

        <flux:menu.separator />
        <flux:menu.radio.group>
            <flux:menu.item icon="arrow-top-right-on-square" disabled>
                {{ __('View all notifications') }}
            </flux:menu.item>
        </flux:menu.radio.group>
    </flux:menu>
</flux:dropdown>
