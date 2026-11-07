{{-- The Visa Waiver's wording, shared by the on-screen sheet and the PDF so they always agree.
     Plain paragraphs, tables and a list only, which both the browser and dompdf lay out the same way.
     @var \App\Support\VisaWaiver $waiver --}}
<p>
    I, <strong>{{ $waiver->clientName ?: '______________________' }}</strong>, acknowledge that I have booked airline
    tickets and travel arrangements through Amega Travel and Tours Services (Booking Reference
    <strong>{{ $waiver->reference }}</strong>) for travel to <strong>{{ $waiver->destination ?: '______________' }}</strong>
    on or about <strong>{{ $waiver->departureLabel() }}</strong>, for myself and for the passenger/s listed below,
    and that the visa required for entry has <strong>not yet been approved or issued</strong> as of the date of this waiver.
</p>

<table class="passengers">
    <thead>
        <tr>
            <th style="width: 8mm;">No.</th>
            <th>Passenger</th>
            <th style="width: 34mm;">Passport No.</th>
            <th style="width: 40mm;">Visa status</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($waiver->passengers as $passenger)
            <tr>
                <td style="text-align: center;">{{ $passenger->passenger_number }}</td>
                <td>{{ $passenger->full_name }}</td>
                <td>{{ $passenger->passport_number ?: '—' }}</td>
                <td>Visa assistance requested; not yet approved</td>
            </tr>
        @endforeach
    </tbody>
</table>

<p>In consideration of Amega Travel and Tours Services processing my booking before the visa decision, I understand, agree and warrant that:</p>

<ol class="terms">
    <li>The approval, denial, processing time and validity of a visa are decided solely by the embassy, consulate or immigration authority concerned. Amega assists with the application but does not and cannot guarantee that a visa will be granted, or when.</li>
    <li>I am proceeding with the purchase of the tickets and other travel arrangements <strong>at my own risk</strong>, before the visa has been approved.</li>
    <li>If the visa is denied, delayed or not issued in time for travel, the airline's and suppliers' own rules on changes, rebooking, cancellation and refunds apply to my booking. Amounts that cannot be refunded or recovered under those rules, including non-refundable airfare, taxes and charges, are for my account.</li>
    <li>Visa application fees, embassy and service fees, and Amega's visa assistance fee are non-refundable, whatever the outcome of the application.</li>
    <li>I will submit every document and requirement asked of me, complete, true and on time. Any delay, denial or loss arising from incomplete, late or inaccurate documents is not the responsibility of Amega.</li>
    <li>I release and hold Amega Travel and Tours Services free and harmless from any claim, liability, loss or expense arising from the denial, delay or non-issuance of the visa.</li>
</ol>
