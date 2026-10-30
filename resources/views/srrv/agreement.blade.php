@php
    /**
     * The SRRV Assistance Booking Agreement (SRRV_IMMIGRATION_SERVICES_AGREEMENT.docx, page 2),
     * filled from a retiree file, an ID renewal or a service request (and the retiree file it
     * belongs to). Expects $agreement (from SrrvAgreementController::data) and $links.
     */
    $a = $agreement;
    $tick = fn (bool $on) => $on ? '✓' : '';
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
        Agreement / Reference No.: <span class="fill" contenteditable="true">{{ $a['reference'] }}</span>
        &nbsp;&nbsp; Date: <span class="fill" contenteditable="true">{{ now()->format('F j, Y') }}</span>
    </p>

    <h2>1. Service Provider Details</h2>
    <p style="margin: 0;"><strong>AMEGA TRAVEL AND TOURS SERVICES</strong></p>
    <p style="margin: 0;">Address: Unit 1 &amp; 2, Astrofield Building, Mitchell Avenue, Balibago, Angeles City, Pampanga 2009</p>
    <p>Email: marketing@amegatravelandtours.com</p>

    <h2>2. Client / Applicant Information</h2>
    <table class="info">
        @foreach (array_chunk($a['client'], 2, true) as $pair)
            <tr>
                @foreach ($pair as $label => $value)
                    <td><span class="label">{{ $label }}:</span><span class="cell" contenteditable="true">{{ $value }}</span></td>
                @endforeach
            </tr>
        @endforeach
    </table>

    <h2>3. SRRV Service Request</h2>
    <p style="margin-bottom: 2px;">Requested service (check all that apply):</p>
    <div class="checks">
        @foreach ($a['requested'] as $label => $on)
            <span><span class="box">{{ $tick($on) }}</span>{{ $label }}</span>
        @endforeach
        <span><span class="box">{{ $tick($a['other'] !== null) }}</span>Other: <span class="fill" contenteditable="true">{{ $a['other'] }}</span></span>
    </div>
    <p>Preferred SRRV option/category (if known): <span class="fill wide" contenteditable="true">{{ $a['option'] }}</span></p>
    <p>Target submission / appointment date (if known): <span class="fill wide" contenteditable="true"></span></p>
    <p>Number of applicants / dependents included: <span class="fill" style="min-width: 0.9in;" contenteditable="true">{{ $a['applicants'] }}</span></p>
    <p style="margin-bottom: 2px;">Specific assistance requested and agreed scope of work:</p>
    <p><span class="fill block" contenteditable="true">{{ $a['scope'] }}</span></p>

    <h2>4. Services Included / Excluded</h2>
    <p style="margin-bottom: 2px;">Services included in the agreed package (check or specify):</p>
    <div class="checks stack">
        @foreach (['Initial consultation / requirements checklist', 'Assistance organizing application documents', 'Application form completion assistance based on information supplied by Client', 'Appointment / submission coordination, where available and authorized', 'Follow-up coordination with relevant office, where available'] as $included)
            <span><span class="box"></span>{{ $included }}</span>
        @endforeach
        <span><span class="box"></span>Other: <span class="fill wide" contenteditable="true"></span></span>
    </div>

    <div class="keep">
        <h2>5. Fees and Payment Schedule</h2>
        <table class="grid">
            <thead><tr><th>Fee / Charge</th><th style="text-align: right;">Amount ({{ $a['currency'] }})</th></tr></thead>
            <tbody>
                <tr><td>Amega service / assistance fee</td><td class="num" contenteditable="true">{{ $a['service_fee'] }}</td></tr>
                <tr><td>PRA / government fees (estimate, if applicable)</td><td class="num" contenteditable="true">{{ $a['pra_fee'] }}</td></tr>
                <tr><td>Third-party costs (specify) <span class="fill" contenteditable="true"></span></td><td class="num" contenteditable="true"></td></tr>
                <tr><td>Other approved charges</td><td class="num" contenteditable="true"></td></tr>
                <tr><td><strong>TOTAL ESTIMATED AMOUNT</strong></td><td class="num" contenteditable="true"><strong>{{ $a['total'] }}</strong></td></tr>
            </tbody>
        </table>
    </div>

    <h2>6. Client Responsibilities and Documents</h2>
    <p style="margin-bottom: 2px;">Initial checklist (requirements depend on the applicant&rsquo;s circumstances and current PRA rules):</p>
    <div class="checks stack">
        @foreach ($a['documents'] as $label => $on)
            <span><span class="box">{{ $tick($on) }}</span>{{ $label }}</span>
        @endforeach
        <span><span class="box"></span>Other documents advised by PRA: <span class="fill wide" contenteditable="true"></span></span>
    </div>
    <p>Documents received from Client: <span class="fill wide" contenteditable="true">{{ $a['received'] }}</span></p>
    <p>Outstanding documents / due date: <span class="fill wide" contenteditable="true">{{ $a['outstanding'] }}</span></p>

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
