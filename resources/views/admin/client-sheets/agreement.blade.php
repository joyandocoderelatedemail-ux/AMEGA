@php
    /**
     * The Immigration Services Booking Agreement (SRRV_IMMIGRATION_SERVICES_AGREEMENT.docx, page 1),
     * filled from the client's folder; the service the transaction is for is ticked.
     * Expects $client, $service, $agreementNo, $agent, $links.
     */
    $tick = fn (bool $on) => $on ? '✓' : '';
    // The agreement's service boxes, and the counter services that tick each one.
    $services = [
        'Visa Updating' => ['visa_extension', 'visa_overstaying'],
        'Visa Downgrading' => ['visa_downgrading'],
        'Visa Re-Stamping' => ['re_stamping_amendment'],
        'Renewal/Re-issuance of Permanent ACR I-CARD' => ['permanent_13a_series'],
        'P.R.A. Card Renewal' => [],
        '13A Spouse Visa' => ['13a_spouse', '13a_permanent'],
        '9G Working Visa' => [],
        'Dual Citizenship Visa (R.A. 9225)' => ['dual_citizenship'],
        'Emigration Clearance Certificate (ECC) Exit Clearance' => ['ecc'],
    ];
    $name = collect([$client->given_name, $client->last_name])->filter()->implode(' ');
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    @include('partials._service-agreement-base', ['title' => 'Immigration Services Booking Agreement - '.$name])
</head>
<body>
@include('partials._service-agreement-top')

    <h1>IMMIGRATION SERVICES BOOKING AGREEMENT</h1>
    <p style="text-align: center; margin: 6px 0 10px;">
        Agreement No.: <span class="fill" contenteditable="true">{{ $agreementNo }}</span>
        &nbsp;&nbsp; Date of Agreement: <span class="fill" contenteditable="true">{{ now()->format('F j, Y') }}</span>
    </p>

    <h2>Client Information</h2>
    <table class="info">
        <tr>
            <td><span class="label">Full Name:</span><span class="cell" contenteditable="true">{{ $name }}</span></td>
            <td><span class="label">Date of Birth:</span><span class="cell" contenteditable="true">{{ $client->date_of_birth?->format('F j, Y') }}</span></td>
        </tr>
        <tr>
            <td><span class="label">Nationality:</span><span class="cell" contenteditable="true">{{ $client->nationality }}</span></td>
            <td><span class="label">Passport Number:</span><span class="cell" contenteditable="true">{{ $client->passport_number }}</span></td>
        </tr>
        <tr>
            <td><span class="label">Contact Number:</span><span class="cell" contenteditable="true">{{ $client->mobile_number }}</span></td>
            <td><span class="label">Email Address:</span><span class="cell" contenteditable="true">{{ $client->email }}</span></td>
        </tr>
        <tr>
            <td colspan="2"><span class="label">Address:</span><span class="cell" contenteditable="true">{{ $client->address }}</span></td>
        </tr>
    </table>

    <h2>Nature of Services</h2>
    <p>The Client engages Amega Travel and Tours Services to provide immigration-related assistance as specified below.</p>
    <p style="margin-top: 8px;">Type of Service Requested (check all that apply):</p>
    <div class="checks">
        @foreach ($services as $label => $keys)
            <span><span class="box">{{ $tick(in_array($service['key'], $keys, true)) }}</span>{{ $label }}</span>
        @endforeach
    </div>

    <p>Destination Country / Government Office: <span class="fill wide" contenteditable="true">Bureau of Immigration, Philippines</span></p>
    <p>Description of Services: <span class="fill wide" contenteditable="true">{{ $service['label'] }}</span></p>

    <p class="disclaimer"><strong>Disclaimer:</strong> As your travel agency, please be advised that our role is strictly to process and submit your application on your behalf. We do not hold the decision-making authority regarding the approval or denial of your visa or travel documents, as this final decision rests solely with the respective embassy, consulate, or governing authority. Subject for additional requirements upon submission of application.</p>

    <div class="keep">
        <h2>Signatures</h2>
        <div class="signatures">
            <div>
                <strong>CLIENT</strong>
                <div style="margin-top: 0.35in; text-align: left;">Full Name:</div>
                <div class="name" contenteditable="true">{{ $name }}</div>
            </div>
            <div>
                <strong>FOR AMEGA TRAVEL AND TOURS</strong>
                <div style="margin-top: 0.35in; text-align: left;">Agent:</div>
                <div class="name" contenteditable="true">{{ $agent }}</div>
            </div>
        </div>
    </div>

@include('partials._service-agreement-bottom')
</body>
</html>
