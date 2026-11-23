@php
    /**
     * The SRRV Assistance Booking Agreement on one page: the parties, the service, and the fees as saved on the
     * file (from SRRV_IMMIGRATION_SERVICES_AGREEMENT.docx, page 2, without its always-blank fields, tick-box lists
     * and document checklist, which the PRA checklist and the Acknowledgment of Documents cover). Filled from a
     * retiree file, an ID renewal or a service request. Expects $agreement (SrrvAgreementController::data) and $links.
     */
    $a = $agreement;
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    @include('partials._service-agreement-base', ['title' => 'SRRV Assistance Booking Agreement - '.$a['reference']])
</head>
<body>
@include('partials._service-agreement-top')

    <h1>SRRV ASSISTANCE BOOKING AGREEMENT</h1>
    <p class="subtitle">Special Resident Retiree&rsquo;s Visa (SRRV) &mdash; Philippines</p>
    <p style="text-align: center;">
        Agreement No.: <span class="fill" contenteditable="true">{{ $a['reference'] }}</span>
        &nbsp;&nbsp; Date: <span class="fill" contenteditable="true">{{ now()->format('F j, Y') }}</span>
    </p>

    <h2>1. Parties</h2>
    <p style="margin: 0 0 4px;"><strong>AMEGA TRAVEL AND TOURS SERVICES</strong>, Unit 1, Astrofield Building, Mitchell Avenue, Balibago, Angeles City, Pampanga 2009 &middot; marketing@amegatravelandtours.com</p>
    <table class="info">
        @foreach (array_chunk($a['client'], 2, true) as $pair)
            <tr>
                @foreach ($pair as $label => $value)
                    <td><span class="label">{{ $label }}:</span><span class="cell" contenteditable="true">{{ $value }}</span></td>
                @endforeach
            </tr>
        @endforeach
        <tr><td colspan="2"><span class="label">Dependents:</span><span class="cell" contenteditable="true">{{ $a['dependents'] }}</span></td></tr>
    </table>

    <h2>2. Service</h2>
    <p style="margin: 0;">Service: <span class="fill wide" contenteditable="true">{{ $a['service'] }}</span></p>
    <p style="margin: 0;">SRRV option: <span class="fill wide" contenteditable="true">{{ $a['option'] }}</span></p>
    <p>Applicants (principal and dependents): <span class="fill" style="min-width: 0.9in;" contenteditable="true">{{ $a['applicants'] }}</span></p>
    <p style="margin-bottom: 2px;">AMEGA will provide:</p>
    <ul style="margin: 0 0 6px 18px; padding: 0;">
        <li>Initial consultation and the requirements checklist</li>
        <li>Assistance organizing the application documents</li>
        <li>Application form completion based on the information supplied by the Client</li>
        <li>Appointment and submission coordination, where available and authorized</li>
        <li>Follow-up coordination with the relevant office, where available</li>
    </ul>

    <div class="keep">
        <h2>3. Fees</h2>
        <table class="grid">
            <thead><tr><th>Paid to AMEGA</th><th style="text-align: right;">Amount</th></tr></thead>
            <tbody>
                @foreach ($a['amega'] as [$label, $amount, $bold])
                    <tr><td>@if ($bold)<strong>{{ $label }}</strong>@else{{ $label }}@endif</td><td class="num" contenteditable="true">@if ($bold)<strong>{{ $amount }}</strong>@else{{ $amount }}@endif</td></tr>
                @endforeach
            </tbody>
            @if ($a['pra'])
                <thead><tr><th>Payable to the PRA (not part of the AMEGA fee)</th><th style="text-align: right;">Amount</th></tr></thead>
                <tbody>
                    @foreach ($a['pra'] as [$label, $amount])
                        <tr><td>{{ $label }}</td><td class="num" contenteditable="true">{{ $amount }}</td></tr>
                    @endforeach
                </tbody>
            @endif
        </table>
    </div>

    <p class="disclaimer"><strong>Disclaimer:</strong> As your travel agency, please be advised that our role is strictly to process and submit your application on your behalf. We do not hold the decision-making authority regarding the approval or denial of your visa or travel documents, as this final decision rests solely with the respective embassy, consulate, or governing authority. Subject for additional requirements upon submission of application.</p>

    <div class="keep">
        <h2>Signatures</h2>
        <div class="signatures">
            <div>
                <div class="name" contenteditable="true">{{ $a['name'] }}</div>
                Client
            </div>
            <div>
                <div class="name" contenteditable="true">{{ $a['agent'] }}</div>
                Agent
            </div>
        </div>
    </div>

@include('partials._service-agreement-bottom')
</body>
</html>
