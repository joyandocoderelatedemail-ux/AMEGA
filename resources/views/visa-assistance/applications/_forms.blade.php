{{--
    The file's blank forms, as a single row (the same compact style as the Immigration counter's header), to
    fill in by hand. Expects $application.
--}}
@php
    // The application form the applicants fill in, and every form of the country to print blank (Australia's
    // business visa form, Canada's eTA form too).
    $blankForms = \App\Support\VisaWorksheets::formsForCountry($application->destination_country);
    $checklistKey = $application->usesSteps() ? $application->checklist_key : null;
    $button = 'inline-flex items-center gap-1.5 h-9 px-3 whitespace-nowrap bg-white border border-slate-200 text-slate-700 font-bold text-xs rounded-lg hover:border-navy-700/40 hover:text-navy-700 transition-colors';

    $blank = array_filter([
        ['Consent Form', 'shield-check', route('visa.consent-blank')],
        ['Service agreement', 'signature', route('visa.agreement-blank')],
        $checklistKey ? ['Document checklist', 'list-checks', route('visa.checklists.blank', $checklistKey)] : null,
    ]);
    foreach ($blankForms as $form) {
        $blank[] = [\App\Support\VisaWorksheets::TITLES[$form], 'file-text', route('visa.worksheets.blank', $form)];
    }
@endphp
<div class="rounded-xl bg-white border border-gray-200 shadow-card px-5 py-4 mb-6" data-filled-forms>
    <p class="text-[11px] font-bold uppercase tracking-wider text-dark/40 mb-2" title="Empty forms to hand to the client.">Forms</p>
    <div class="flex flex-wrap items-center gap-2">
        @foreach ($blank as [$label, $icon, $url])
            <a href="{{ $url }}" target="_blank" rel="noopener" class="{{ $button }}"><i data-lucide="{{ $icon }}" class="w-4 h-4"></i> {{ $label }}</a>
        @endforeach
        {{-- The client's own checklist, printed from the file once its requirements are cleared --}}
        @if ($application->checklist() && $application->isCleared())
            <a href="{{ route('visa.applications.checklist.print', $application) }}" target="_blank" rel="noopener" class="{{ $button }}"><i data-lucide="printer" class="w-4 h-4"></i> Print the {{ $application->checklist()['label'] }} checklist for the client</a>
        @endif
        {{-- Notice, last: the template for where the file stands, edited and emailed to the client --}}
        @php $visaNotice = \App\Support\VisaNotice::for($application, auth()->user()->name); @endphp
        @include('partials._notify-client', [
            'noticeFor' => 'visa-'.$application->id,
            'toName' => $application->client_name,
            'email' => $application->client_email,
            'canEmail' => \App\Services\ClientNotifier::canReceive($application->client_email),
            'meta' => $application->reference.' · '.$application->status_label,
            'template' => ['label' => $visaNotice['label'], 'body' => $visaNotice['body']],
            'emptyMessage' => '',
            'subject' => $visaNotice['subject'],
            'action' => route('visa.applications.notify', $application),
            'noEmailText' => 'No client email on the file',
        ])
    </div>
</div>
