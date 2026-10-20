@php
    use App\Support\VisaWorksheets as W;

    /**
     * AMEGA's Application for Schengen Visa worksheet, one A4 page, filled from the
     * applicant record and their worksheet details, or blank for the client to fill in.
     */
    $a = $applicant;
    $d = fn (string $key) => $blank ? null : $a->detail($key);
    $date = fn ($value) => $value ? $value->format('d-m-Y') : '';
    $pick = fn (string $key, array $options) => $options[$d($key)] ?? '';

@endphp
@include('visa-assistance.worksheets._open', ['docTitle' => 'Schengen Visa Worksheet', 'pages' => 1])

<div class="sheet">
    @include('visa-assistance.worksheets._masthead', ['heading' => 'APPLICATION FOR SCHENGEN VISA'])

    <table>
        <colgroup>@for ($i = 0; $i < 12; $i++)<col style="width:8.333%">@endfor</colgroup>
        <tr><td colspan="12" class="bar">PERSONAL INFORMATION</td></tr>
        <tr>
            <td colspan="4">Surname: <span class="v">{{ $blank ? '' : $a->last_name }}</span></td>
            <td colspan="8">Surname at Birth: (former family name, if married) <span class="v">{{ $d('surname_at_birth') }}</span></td>
        </tr>
        <tr><td colspan="12">First name: <span class="v">{{ $blank ? '' : trim($a->first_name.' '.$a->middle_name) }}</span></td></tr>
        <tr>
            <td colspan="3" style="height:29pt;">Date of Birth:(DD-MM-YYYY) <span class="v">{{ $blank ? '' : $date($a->date_of_birth) }}</span></td>
            <td colspan="4">Place of Birth: <span class="v">{{ $d('place_of_birth') }}</span><span style="display:block;margin-top:{{ $d('place_of_birth') ? '0' : '12pt' }};">Country of Birth: <span class="v" style="display:inline;">{{ $d('country_of_birth') }}</span></span></td>
            <td colspan="5">Nationality: <span class="v">{{ $blank ? '' : $a->nationality }}</span></td>
        </tr>
        <tr>
            <td colspan="3">Sex: <span class="v">{{ $blank ? '' : $a->gender }}</span></td>
            <td colspan="4">Status: <span class="v">{{ $pick('civil_status', W::CIVIL_STATUSES) }}</span></td>
            <td colspan="5">Phone Number: <span class="v">{{ $d('phone') }}</span></td>
        </tr>
        <tr>
            <td colspan="7" style="height:41pt;">Applicant’s home address and e-mail address: <span class="v">{{ $d('home_address') }}</span><span class="v lower">{{ $d('email') }}</span></td>
            <td colspan="5">Occupation: <span class="v">{{ $d('occupation') }}</span></td>
        </tr>
        <tr>
            <td colspan="5" style="height:41pt;">Main purpose of the journey: <span class="v">{{ $pick('purpose', W::SCHENGEN_PURPOSES) }}</span></td>
            <td colspan="7">Additional information on purpose of stay: <span class="v">{{ $d('purpose_info') }}</span></td>
        </tr>
        <tr>
            <td colspan="7" style="height:28pt;">Member state(s) of main destination: <span class="v">{{ $d('main_destination') }}</span></td>
            <td colspan="5">Member of State of first entry: <span class="v">{{ $d('first_entry') }}</span></td>
        </tr>
        <tr>
            <td colspan="4" style="height:31pt;">Number of entries requested: <span class="v">{{ $pick('entries', W::SCHENGEN_ENTRIES) }}</span></td>
            <td colspan="3">Intended date of arrival: <span class="v">{{ $date($blank ? null : $a->dateDetail('arrival_date')) }}</span></td>
            <td colspan="5">Intended date of departure: <span class="v">{{ $date($blank ? null : $a->dateDetail('departure_date')) }}</span></td>
        </tr>

        <tr><td colspan="12" class="bar" style="font-weight:normal;font-style:italic;">FOR MINORS: Parent authority/ legal guardian.</td></tr>
        <tr>
            <td colspan="4" style="height:28pt;">Surname: <span class="v">{{ $d('guardian_surname') }}</span></td>
            <td colspan="8">First Name: <span class="v">{{ $d('guardian_first_name') }}</span></td>
        </tr>
        <tr><td colspan="12" style="height:28pt;">Address: <span class="v">{{ $d('guardian_address') }}</span></td></tr>
        <tr>
            <td colspan="4" style="height:24pt;">Phone Number: <span class="v">{{ $d('guardian_phone') }}</span></td>
            <td colspan="4">Email Address: <span class="v lower">{{ $d('guardian_email') }}</span></td>
            <td colspan="4">Nationality: <span class="v">{{ $d('guardian_nationality') }}</span></td>
        </tr>
        <tr><td colspan="12" style="height:28pt;">NATIONAL ID Number: (if applicable) <span class="v">{{ $d('national_id') }}</span></td></tr>
        <tr><td colspan="12" style="height:28pt;">Type of Travel Document: <span class="v">{{ $pick('travel_document', W::TRAVEL_DOCUMENTS) }}</span></td></tr>
        <tr>
            <td colspan="3" style="height:35pt;">Number of Travel Document: <span class="v">{{ $blank ? '' : $a->passport_number }}</span></td>
            <td colspan="3">Date of Issue: <span class="v">{{ $date($blank ? null : $a->dateDetail('passport_issued_on')) }}</span></td>
            <td colspan="3">Valid Until: <span class="v">{{ $blank ? '' : $date($a->passport_expiry_date) }}</span></td>
            <td colspan="3">Issued by (Country): <span class="v">{{ $d('passport_issued_by') }}</span></td>
        </tr>

        <tr><td colspan="12" class="bar">PERSONAL DATA OF THE FAMILY MEMBER WHO IS IN EU, EEA OR CH CITIZEN:</td></tr>
        <tr>
            <td colspan="4">Surname: <span class="v">{{ $d('eu_surname') }}</span></td>
            <td colspan="8">First name: <span class="v">{{ $d('eu_first_name') }}</span></td>
        </tr>
        <tr>
            <td colspan="4" style="height:31pt;">Date of Birth (DD-MM-YYYY): <span class="v">{{ $date($blank ? null : $a->dateDetail('eu_dob')) }}</span></td>
            <td colspan="3">Nationality: <span class="v">{{ $d('eu_nationality') }}</span></td>
            <td colspan="5">Number of Travel Document of ID Card: <span class="v">{{ $d('eu_document') }}</span></td>
        </tr>
        <tr><td colspan="12" style="height:27pt;">Family relationship with an EU, EEA, or CH citizen: <span class="v">{{ $d('eu_relationship') }}</span></td></tr>

        <tr><td colspan="12" class="bar">INFORMATION OF INVITING PERSON in the Member State</td></tr>
        <tr>
            <td colspan="3" style="height:35pt;">Surname: <span class="v">{{ $d('host_surname') }}</span></td>
            <td colspan="4">First name: <span class="v">{{ $d('host_first_name') }}</span></td>
            <td colspan="5" style="font-size:9pt;">Name of hotel or temporary accommodation in the member state: <span class="v">{{ $d('hotel_name') }}</span></td>
        </tr>
        <tr>
            <td colspan="7" style="height:44pt;">Address: <span class="v">{{ $d('host_address') }}</span></td>
            <td colspan="5">Phone Number: <span class="v">{{ $d('host_phone') }}</span></td>
        </tr>
        <tr>
            <td colspan="7" style="height:48pt;">Hotel/Accommodation: <span class="v">{{ $d('hotel_address') }}</span></td>
            <td colspan="5">Cost of travelling and living during the applicant stay is covered: <span class="v">{{ $pick('cost_covered_by', W::COST_COVERED) }}</span></td>
        </tr>
    </table>
</div>
</body>
</html>
