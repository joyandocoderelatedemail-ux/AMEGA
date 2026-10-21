@extends('layouts.visa-assistance')

@section('title', \App\Support\VisaWorksheets::TITLES[$worksheet].' - '.$applicant->full_name.' - AMEGA Travel and Tours')

@php
    use App\Support\VisaWorksheets;

    // A first worksheet starts from what the file and the client's record already know.
    $client = $applicant->registeredClient();
    $details = $applicant->form_details ?? array_filter([
        'civil_status' => $client?->civil_status,
        'phone' => $applicant->is_primary ? $application->client_phone : null,
        'email' => $applicant->is_primary ? $application->client_email : null,
        'passport_issued_by' => $applicant->passport_country,
        'travel_document' => 'ordinary',
        'purpose' => ['tourist' => 'tourism', 'business' => 'business', 'family' => 'family'][$application->purpose] ?? null,
        'main_destination' => null,
    ]);

    $record = [
        'Surname' => $applicant->last_name,
        'First name' => trim($applicant->first_name.' '.$applicant->middle_name),
        'Date of birth' => $applicant->date_of_birth?->format('d-m-Y'),
        'Sex' => $applicant->gender ? ucfirst($applicant->gender) : null,
        'Nationality' => $applicant->nationality,
        'Passport' => $applicant->passport_number,
        'Valid until' => $applicant->passport_expiry_date?->format('d-m-Y'),
    ];
@endphp

@section('content')

    <div class="mb-8">
        <div class="flex items-center gap-2 text-[11px] font-bold uppercase tracking-wider text-primary mb-2">
            <a href="{{ route('visa.applications.show', $application) }}" class="hover:underline">{{ $application->reference }}</a>
            <i data-lucide="chevron-right" class="w-3 h-3 text-dark/25"></i>
            <span>Applicant #{{ $applicant->applicant_number }}</span>
        </div>
        <h1 class="font-heading text-2xl sm:text-3xl font-extrabold text-dark">{{ VisaWorksheets::TITLES[$worksheet] }}</h1>
        <p class="text-sm text-dark/50 mt-1">{{ $applicant->full_name }} &middot; {{ $application->destination_country }}. Enter what the embassy form asks; it prints on AMEGA's worksheet.</p>
    </div>

    <div class="rounded-2xl bg-white border border-gray-200 shadow-card p-6 mb-6">
        <div class="flex flex-wrap items-start justify-between gap-3 mb-4">
            <div>
                <h2 class="font-heading text-sm font-extrabold text-dark">From the applicant record</h2>
                <p class="text-[11px] text-dark/50 mt-0.5">These print as they are on the file. Correct them on the applicant, not here.</p>
            </div>
        </div>
        <dl class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-7 gap-3">
            @foreach ($record as $recordLabel => $recordValue)
                <div class="rounded-lg bg-gray-50 px-3 py-2 min-w-0">
                    <dt class="text-[9px] font-bold uppercase tracking-wider text-dark/40">{{ $recordLabel }}</dt>
                    <dd class="text-xs font-semibold mt-0.5 truncate {{ $recordValue ? 'text-dark' : 'text-amber-700' }}">{{ $recordValue ?: 'Missing' }}</dd>
                </div>
            @endforeach
        </dl>
    </div>

    <form method="POST" action="{{ route('visa.worksheets.update', [$application, $applicant]) }}" class="space-y-6">
        @csrf
        @method('PUT')

        @if ($errors->any())
            <div class="rounded-2xl bg-rose-50 border border-rose-200 p-4 text-xs text-rose-800" role="alert">
                <p class="font-bold text-rose-900">Please correct the fields marked below.</p>
            </div>
        @endif

        @include('shared._form-details', [
            'sections' => VisaWorksheets::sections($worksheet),
            'details' => $details,
            'title' => 'Worksheet Details',
            'intro' => 'Open each section and fill in what applies. Anything left blank prints empty, for the client to fill in by hand.',
        ])

        <div class="flex flex-col-reverse sm:flex-row sm:items-center justify-end gap-3">
            <a href="{{ route('visa.applications.show', $application) }}" class="px-5 py-2.5 bg-gray-100 text-dark/70 font-bold text-xs rounded-xl hover:bg-gray-200 transition-all text-center">Cancel</a>
            <button type="submit" name="then" value="stay"
                    class="px-5 py-2.5 bg-white border border-primary/30 text-primary font-bold text-xs rounded-xl hover:bg-primary/5 transition-all">
                Save
            </button>
            <button type="submit" name="then" value="print"
                    class="px-6 py-2.5 bg-primary text-white font-heading font-bold text-xs rounded-xl hover:bg-primary-light transition-all shadow-sm inline-flex items-center justify-center gap-2">
                <i data-lucide="printer" class="w-4 h-4"></i>
                Save &amp; print
            </button>
        </div>
    </form>

@endsection
