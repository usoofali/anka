<x-mail::message>
# {{ $title }}

{{ $body }}

@if ($url)
<x-mail::button :url="$url">
{{ __('View Details') }}
</x-mail::button>
@endif

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
