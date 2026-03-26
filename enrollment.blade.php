<x-mail::message>
@if($institutionLogo)
<div style="text-align: center; margin-bottom: 20px;">
<img src="{{ url($institutionLogo) }}" alt="{{ $institutionName }} Logo" style="max-height: 80px; width: auto;">
</div>
@endif

# Welcome to {{ $institutionName }}

Dear {{ $firstName }},

Congratulations! Your enrollment process has been completed successfully.

You are now officially a registered student in the **{{ $programName }}** program.

Below are your student account credentials for the school portal:

<x-mail::panel>
**Matriculation Number:** {{ $matricNumber }}<br>
**Temporary Password:** {{ $defaultPassword }}
</x-mail::panel>

<x-mail::button :url="route('login')" color="primary">
Login to Student Portal
</x-mail::button>

Please log in and update your password and profile as soon as possible.

Thanks,<br>
The {{ $institutionName }} Admissions Office
</x-mail::message>
