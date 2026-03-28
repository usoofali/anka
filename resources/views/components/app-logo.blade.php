@props([
'sidebar' => false,
])

@php
    $system_logo = \Illuminate\Support\Facades\Cache::rememberForever('system_logo', function () {
        try {
            return \App\Models\SystemSetting::where('id', 1)->value('logo');
        } catch (\Exception $e) {
            return null;
        }
    });
@endphp

@if($sidebar)
    <flux:sidebar.brand name="{{ 'ANKA SHIPPING' }}" {{ $attributes }}>
        <x-slot name="logo"
            class="flex aspect-square size-14 items-center justify-center rounded-md overflow-hidden {{ !$system_logo ? 'bg-accent-content text-accent-foreground' : '' }}">
            <img src="{{ $system_logo }}" class="w-full h-full object-cover">
        </x-slot>
    </flux:sidebar.brand>
@endif