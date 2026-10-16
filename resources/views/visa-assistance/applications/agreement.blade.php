@php
    /**
     * The Service Engagement Agreement (VISA-AGGREEMENT.docx), filled from the file and the
     * client's profile. Every underlined field can still be edited in the browser before
     * printing, for what the file does not hold (the sponsor, the processing weeks).
     * The letterhead is the agreement's own full-page background, repeated on every page.
     */
    $peso = fn (float $amount) => 'PhP '.number_format($amount, 2);
    $subtotal = (float) $application->service_fee + (float) $application->visa_fee;
    $total = (float) $application->total_amount;
    $paid = (float) $application->amount_paid;
    $isTourist = $application->service_type === 'visit_visa';

    // The tourist visa price list as printed on the agreement; the file's destination is ticked.
    $touristRows = [
        'USA' => ['USA', 21000], 'United Kingdom' => ['United Kingdom', 18500], 'Australia' => ['Australia', 18000],
        'New Zealand' => ['New Zealand', 10000], 'Canada' => ['Canada', 15000], 'Japan' => ['Japan', 3500],
        'Korea' => ['South Korea', 3500], 'China' => ['China', 9900], 'Schengen (Europe)' => ['SCHENGEN Country', 7000],
    ];
    $listed = $isTourist && isset($touristRows[$application->destination_country]);
    $isOther = $isTourist && ! $listed;
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Service Engagement Agreement - {{ $application->reference }}</title>
    <style>
        @page { size: A4 portrait; margin: 0; }
        * { box-sizing: border-box; }
        html, body { margin: 0; }
        body { background: #E9EDF3; font-family: Tahoma, Verdana, Arial, sans-serif; color: #000; font-size: 9.5pt; line-height: 1.45; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .paper { position: relative; width: 210mm; margin: 18px auto; background: #fff; box-shadow: 0 2px 12px rgba(0, 0, 0, .12); }
        /* On screen only the letterhead's logo shows; its footer would land on the text. Printed, each page gets the whole letterhead. */
        .screen-logo { position: absolute; top: 9mm; left: 10mm; height: 13mm; }
        .letterhead { display: none; }
        table.layout { width: 100%; border-collapse: collapse; }
        table.layout > thead td { height: 30mm; padding: 0; }
        table.layout > tfoot td { height: 36mm; padding: 0; }
        table.layout > tbody > tr > td { padding: 0 18mm; }
        h1 { text-align: center; font-size: 15pt; margin: 0 0 8px; letter-spacing: .5px; }
        h2 { font-size: 10pt; margin: 12px 0 4px; text-transform: uppercase; }
        p { margin: 0 0 6px; text-align: justify; }
        ol { margin: 0 0 6px; padding-left: 20px; }
        ol li { text-align: justify; margin-bottom: 3px; }
        .fill { display: inline-block; min-width: 30mm; border-bottom: 1px solid #000; padding: 0 3px; font-weight: bold; text-align: center; outline: none; }
        .fill.wide { min-width: 70mm; }
        .fill.short { min-width: 12mm; }
        .fill:focus { background: #FFF7D6; }
        table.grid { width: 100%; border-collapse: collapse; margin: 4px 0 8px; font-size: 9pt; }
        table.grid tr { break-inside: avoid; }
        h2 { break-after: avoid; }
        .keep { break-inside: avoid; }
        table.grid th, table.grid td { border: 1px solid #000; padding: 2px 5px; vertical-align: top; }
        table.grid th { background: #DCE6F2; text-align: center; }
        table.grid td.num { text-align: right; white-space: nowrap; }
        table.grid td.mid { text-align: center; }
        .box { display: inline-block; width: 10px; height: 10px; border: 1px solid #000; margin-right: 4px; text-align: center; line-height: 9px; font-size: 9px; vertical-align: middle; }
        .group td { background: #F2F2F2; font-weight: bold; }
        .note { font-size: 8pt; font-style: italic; }
        .signatures { display: flex; justify-content: space-between; gap: 20mm; margin-top: 14mm; }
        .signatures div { flex: 1; text-align: center; }
        .signatures .name { font-weight: bold; border-bottom: 1px solid #000; min-height: 18px; outline: none; }
        .toolbar { width: 210mm; margin: 18px auto 0; display: flex; gap: 10px; align-items: center; font: 12px Arial, sans-serif; color: #475569; }
        .toolbar a, .toolbar button { font: bold 12px Arial, sans-serif; padding: 9px 16px; border-radius: 8px; border: 0; background: #003B95; color: #fff; cursor: pointer; text-decoration: none; }
        .toolbar a.ghost { background: #fff; color: #003B95; border: 1px solid #003B95; }
        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .paper { margin: 0; box-shadow: none; background: none; }
            .screen-logo { display: none; }
            .letterhead { display: block; position: fixed; top: 0; left: 0; width: 210mm; height: 297mm; z-index: -1; }
            .fill:focus { background: none; }
        }
    </style>
</head>
<body>

<div class="toolbar">
    <button type="button" onclick="window.print()">Print agreement</button>
    <a class="ghost" href="{{ route('visa.applications.show', $application) }}">Back to file</a>
    <span>Filled from the file. Click any underlined field to change it before printing.</span>
</div>

<img class="letterhead" src="{{ asset('images/visa/agreement-letterhead.png') }}" alt="">

<div class="paper">
<img class="screen-logo" src="{{ asset('newassets/Amega Brand/LOGO/AMEGA LOGO 2_UPDATED.png') }}" alt="AMEGA Travel and Tours Services">
<table class="layout">
    <thead><tr><td></td></tr></thead>
    <tfoot><tr><td></td></tr></tfoot>
    <tbody><tr><td>

        <h1>Service Engagement Agreement</h1>

        <h2>The parties</h2>
        <p>
            <strong>Amega Travel and Tours Services</strong>, a sole proprietorship, duly registered under Philippine laws, with business address at
            G/F Horizon Frontier Hotel, Malabanias Road, corner Plaridel Street, Angeles City 2009, Pampanga, is engaged in the business of
            providing travel and tour services, including but not limited to visa application assistance (tourist, permanent, resident, retirement,
            partner, spouse), shall heretofore referred to as the &ldquo;<strong>FIRST PARTY</strong>&rdquo;.
        </p>
        <p style="text-align: center"><strong>-and-</strong></p>
        <p>
            <span class="fill wide" contenteditable="true">{{ $name }}</span>, of legal age,
            <span class="fill" contenteditable="true">{{ $citizenship }}</span> citizen, with residence address at
            <span class="fill wide" contenteditable="true">{{ $address }}</span>, seeks to apply for a
            <span class="fill" contenteditable="true">{{ $visaType }}</span> to
            <span class="fill" contenteditable="true">{{ $country }}</span>, sponsored by
            <span class="fill wide" contenteditable="true"></span>, of legal age, single/married/divorced, separated, others,
            shall heretofore mutually referred to as the &ldquo;<strong>SECOND PARTY</strong>&rdquo;.
        </p>
        <p><strong>WHEREAS</strong>, the SECOND PARTY wishes to engage the services of the FIRST PARTY for a fee and the latter is willing to provide its services to the former under the following terms and conditions.</p>
        <p>In consideration thereof, the SECOND PARTY, engages the services of the FIRST PARTY to provide the following assistance/services, subject to the terms and conditions stipulated hereunder:</p>

        <h2>Schedule of Fees</h2>
        <p class="note">*Check the services availed by the applicant</p>
        <table class="grid">
            <thead>
                <tr><th style="width: 46%">Services</th><th>Fee</th><th>No. of Applicants</th><th>Total</th></tr>
            </thead>
            <tbody>
                <tr class="group"><td colspan="4">Tourist Visa:</td></tr>
                @foreach ($touristRows as $key => [$label, $fee])
                    @php $ticked = $listed && $application->destination_country === $key; @endphp
                    <tr>
                        <td><span class="box">{{ $ticked ? '✓' : '' }}</span>{{ $label }}</td>
                        <td class="num">Php {{ number_format($fee) }}</td>
                        <td class="mid">{{ $ticked ? $applicants : '' }}</td>
                        <td class="num">{{ $ticked ? $peso($subtotal) : '' }}</td>
                    </tr>
                @endforeach
                <tr>
                    <td><span class="box">{{ $isOther ? '✓' : '' }}</span>Others: <span class="fill" contenteditable="true">{{ $isOther ? $country : '' }}</span></td>
                    <td class="num"></td>
                    <td class="mid">{{ $isOther ? $applicants : '' }}</td>
                    <td class="num">{{ $isOther ? $peso($subtotal) : '' }}</td>
                </tr>
                <tr class="group"><td colspan="4">Immigrant Visa:</td></tr>
                @foreach ([['USA', 'PhP90,000.00'], ['Australia', 'PhP60,000.00'], ['United Kingdom', ''], ['Others', 'PhP60,000.00']] as [$label, $fee])
                    <tr><td><span class="box"></span>{{ $label }}</td><td class="num">{{ $fee }}</td><td></td><td></td></tr>
                @endforeach
                <tr class="group"><td colspan="4">Assumption of US Petition:</td></tr>
                <tr><td><span class="box"></span>NVC Stage</td><td class="num">PhP20,000.00</td><td></td><td></td></tr>
                <tr><td><span class="box"></span>US Embassy Stage</td><td class="num">PhP20,000.00</td><td></td><td></td></tr>
                <tr class="group"><td colspan="4">Briefing:</td></tr>
                <tr><td><span class="box"></span>US Tourist</td><td class="num">PhP1,500.00</td><td></td><td></td></tr>
                <tr><td><span class="box"></span>US Immigrant</td><td class="num">PhP5,000.00</td><td></td><td></td></tr>
                <tr>
                    <td><span class="box">{{ (float) $application->rush_fee > 0 ? '✓' : '' }}</span><strong>Rush Processing Surcharge</strong><br>
                        <span class="note">For processing within one (1) to five (5) business days prior to the intended submission date.</span></td>
                    <td class="num">Php5,000</td><td></td>
                    <td class="num">{{ (float) $application->rush_fee > 0 ? $peso((float) $application->rush_fee) : '' }}</td>
                </tr>
                @if ((float) $application->insurance_fee + (float) $application->etravel_fee > 0)
                    <tr>
                        <td><span class="box">✓</span>Insurance / e-Travel</td><td></td><td></td>
                        <td class="num">{{ $peso((float) $application->insurance_fee + (float) $application->etravel_fee) }}</td>
                    </tr>
                @endif
                <tr><td colspan="3" style="text-align: right"><strong>Total:</strong></td><td class="num"><strong>{{ $peso($total) }}</strong></td></tr>
            </tbody>
        </table>

        <h2>Services Fee</h2>
        <p>
            The SECOND PARTY agrees to pay the FIRST PARTY the total amount of (AMOUNT IN WORDS)
            <span class="fill wide" contenteditable="true">{{ $amountInWords }}</span> Philippine currency,
            <span class="fill" contenteditable="true">{{ $peso($total) }}</span>, in consideration of the services of the FIRST PARTY mentioned above.
            Fifty percent (50%) shall be payable upon signing of this agreement, and the remaining thereof shall be paid upon submission/filing or after
            the First Party secured an interview date to the concerned Embassy/Consulate/Agency/authorized third-party agents.
        </p>
        <p class="note">Unless otherwise prevented by a fortuitous event, natural calamity, or circumstances outside the control of the First Party</p>

        <div class="keep">
        <h2>Payment Details</h2>
        <table class="grid">
            <thead><tr><th>Date Paid</th><th>OR/SOA No.</th><th>Particulars</th><th>Payment</th><th>Balance</th></tr></thead>
            <tbody>
                <tr><td contenteditable="true"></td><td contenteditable="true"></td><td><strong>TOTAL SERVICE FEE</strong></td><td></td><td class="num">{{ $peso($total) }}</td></tr>
                <tr>
                    <td contenteditable="true"></td><td contenteditable="true"></td><td>Deposit</td>
                    <td class="num">{{ $paid > 0 && $paid < $total ? $peso($paid) : '' }}</td>
                    <td class="num">{{ $paid > 0 && $paid < $total ? $peso($total - $paid) : '' }}</td>
                </tr>
                <tr><td contenteditable="true"></td><td contenteditable="true"></td><td>Deposit</td><td contenteditable="true"></td><td contenteditable="true"></td></tr>
                <tr>
                    <td contenteditable="true"></td><td contenteditable="true"></td><td>Full Payment</td>
                    <td class="num">{{ $total > 0 && $paid >= $total ? $peso($paid) : '' }}</td>
                    <td class="num">{{ $total > 0 && $paid >= $total ? $peso(0) : '' }}</td>
                </tr>
            </tbody>
        </table>
        </div>

        <h2>Guaranties</h2>
        <ol>
            <li>The FIRST PARTY does not in any way guarantee the result and or success of each application;</li>
            <li>SECOND PARTY guarantee(s) the genuineness, authenticity, and due execution of every document presented and submitted to the FIRST PARTY relative to each and individual application/s subject of this agreement;</li>
            <li>Any misrepresentation made by the SECOND PARTY in the visa application or in any of the documents submitted in the interest of obtaining and securing a visa shall keep the FIRST PARTY free from any liability, legally or by way of indemnity to any agency or authority concerned;</li>
            <li>The FIRST PARTY does not guarantee the result and outcome of the application and shall be free from any liability arising out of and from the decision of the application by the concerned Embassy/Consulate as a result, all fees paid by the SECOND PARTY to the FIRST PARTY described above and all other fees collected by the former or paid to other agencies shall be non-refundable and non-transferrable including but not limited to visa fees, courier fees, biometric fees;</li>
        </ol>

        <h2>Disclosures</h2>
        <p>The FIRST PARTY shall not act and is understood not to act as an attorney or in any representative capacity in the visa application/ petition or any stage thereof. The FIRST PARTY is not an accredited agent nor a migration agent authorized by any embassy, consulate, or authorized third-party entity. The FIRST PARTY shall be understood to be acting only as an &ldquo;assistance provider&rdquo; to the SECOND PARTY in lodging the visa application/petition.</p>

        <h2>Processing time</h2>
        <ol>
            <li>The FIRST PARTY guarantees the application to be submitted to the proper agency, office, entity, or embassy within (<span class="fill short" contenteditable="true"></span>) weeks from submission of COMPLETE DOCUMENTS AND PERTINENT INFORMATION, AND payment of fifty percent (50%) of the total service fee stipulated above;</li>
            <li>The SECOND PARTY agrees to abide by the processing time observed by the agency, office, entity, or embassy concerned in processing the visa application from submission thereof;</li>
            <li>The application shall be considered submitted to the agency, office, entity, or embassy after acknowledgment of receipt thereof and their usual processing time shall commence from such time;</li>
            <li>In cases of group/family visa applications, processing of the application/s shall commence only after submission of all pertinent documents of all applicants and all the necessary information relative to their visa application;</li>
            <li>It shall be understood that the FIRST PARTY has no control nor influence in the processing times of the concerned embassy/entity after submission. The FIRST PARTY shall have no liability whatsoever including any damage(s) incurred and sustained by the SECOND PARTY on account of such delay.</li>
        </ol>

        <h2>Pre-booked flights and reservations</h2>
        <p>The SECOND PARTY is notified and is advised not to purchase any ticket and accommodation, nor make any reservation to the country of destination specified in this agreement until a visa(s) is/are received by the SECOND PARTY. Any cancellation charges, rebooking charges, and the damages arising therefrom shall not be the responsibility nor shall become the liability of the FIRST PARTY. The SECOND PARTY is hereby notified and advised NOT to process and submit the visa application through the FIRST PARTY in case the former has already purchased an airline ticket, accommodation and other reservation. The execution of this agreement despite notice shall be construed as consent by the SECOND PARTY to proceed with the application with waiver of his/her rights against the FIRST PARTY for any claim of damages arising from non-issuance or delayed issuance of a visa.</p>

        <h2>Rush Processing Surcharge</h2>
        <ol>
            <li>A surcharge will apply for visa applications submitted for processing within ONE (1) to five (5) business days prior to the intended submission date.</li>
            <li>This &ldquo;Rush Processing Surcharge&rdquo; is in addition to the standard service fee and is intended to cover the costs associated with expedited handling.</li>
        </ol>

        <h2>Submission of Pertinent Documents and handling thereof</h2>
        <ol>
            <li>The SECOND PARTY shall submit to the FIRST PARTY the complete documentary requirements listed under the &ldquo;Document Checklist and Time-table&rdquo;, attached hereto as Annex &ldquo;A&rdquo;, in consonance with the Immigration Rules and Regulations and/or applicable Laws of the country of Destination, found on their official website or their guidelines;</li>
            <li>The SECOND PARTY acknowledges the possibility of submitting additional documentary requirements as may be required by the embassy/ consulate office or agency receiving the application;</li>
            <li>The FIRST PARTY shall keep in confidence all the documents submitted by the SECOND PARTY while the visa application is in process or pending result. All documents submitted to the FIRST PARTY will not be returned.</li>
            <li>The SECOND PARTY acknowledges that the embassy/ consulate office or agency concerned may keep some or all of the original documents submitted, in such case will not be returned by the FIRST PARTY to the former; Documents not claimed within two years will be disposed/destroyed permanently.</li>
        </ol>

        <div class="keep">
        <p style="margin-top: 10px">
            <strong>IN WITNESS WHEREOF</strong>, the parties have hereto affixed their signatures on this
            <span class="fill short" contenteditable="true">{{ now()->format('jS') }}</span> day of
            <span class="fill" contenteditable="true">{{ now()->format('F Y') }}</span>, in the municipality of
            <span class="fill" contenteditable="true">Angeles City</span>.
        </p>

        <div class="signatures">
            <div>
                <div class="name" contenteditable="true">{{ $preparedBy }}</div>
                First Party or Authorized Representative
            </div>
            <div>
                <div class="name" contenteditable="true">{{ $name }}</div>
                Second Party
            </div>
        </div>
        </div>

    </td></tr></tbody>
</table>
</div>

</body>
</html>
