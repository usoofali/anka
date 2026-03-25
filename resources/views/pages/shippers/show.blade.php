<x-layouts::app :title="$shipper->company_name">
    <div class="flex flex-col gap-6">
        <div>
            <flux:heading size="lg">{{ $shipper->company_name }}</flux:heading>
            <flux:subheading class="mt-1">{{ __('Shipper profile') }}</flux:subheading>
        </div>

        <div class="grid max-w-xl gap-4 rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
            <div>
                <flux:text class="text-xs font-medium uppercase text-zinc-500">{{ __('Company') }}</flux:text>
                <flux:text>{{ $shipper->company_name }}</flux:text>
            </div>
            <div>
                <flux:text class="text-xs font-medium uppercase text-zinc-500">{{ __('Owner') }}</flux:text>
                <flux:text>{{ $shipper->user?->name }} ({{ $shipper->user?->email }})</flux:text>
            </div>
            <div>
                <flux:text class="text-xs font-medium uppercase text-zinc-500">{{ __('Phone') }}</flux:text>
                <flux:text>{{ $shipper->phone }}</flux:text>
            </div>
            <div>
                <flux:text class="text-xs font-medium uppercase text-zinc-500">{{ __('Address') }}</flux:text>
                <flux:text>{{ $shipper->address }}</flux:text>
            </div>
            <div class="flex flex-wrap gap-4 text-sm text-zinc-600 dark:text-zinc-400">
                <span>{{ __('Shipper ID') }}: {{ $shipper->id }}</span>
                <span>{{ __('User ID') }}: {{ $shipper->user_id }}</span>
            </div>
        </div>
    </div>
</x-layouts::app>
