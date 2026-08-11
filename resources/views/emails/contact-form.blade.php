<x-mail::message>
# New Contact Form Inquiry

**Name:** {{ $data['name'] }}

**Email:** {{ $data['email'] }}

**Phone:** {{ $data['phone'] ?? 'Not provided' }}

**Message:**
{{ $data['message'] }}

<x-mail::button :url="'mailto:' . e($data['email'])">
Reply to {{ $data['name'] }}
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
