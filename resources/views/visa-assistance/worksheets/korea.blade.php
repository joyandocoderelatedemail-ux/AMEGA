@php
    use App\Support\VisaWorksheets as W;

    /**
     * AMEGA's Visa Application Form worksheet for Korea, one A4 page, filled from the
     * applicant record and their worksheet details, or blank for the client to fill in.
     */
    $a = $applicant;
    $d = fn (string $key) => $blank ? null : $a->detail($key);
    $date = fn ($value) => $value ? $value->format('d-m-Y') : '';
    $dd = fn (string $key) => $blank ? '' : $date($a->dateDetail($key));
    $pick = fn (string $key, array $options) => $options[$d($key)] ?? '';
    $yesNo = fn (string $key) => ['yes' => 'Yes', 'no' => 'No'][$d($key)] ?? '';
@endphp
@include('visa-assistance.worksheets._open', ['docTitle' => 'Korea Visa Worksheet', 'pages' => 1])
<style>.slot { display: block; min-height: 25pt; }</style>

<div class="sheet">
    @include('visa-assistance.worksheets._masthead', ['heading' => 'VISA APPLICATION FORM'])

    <table>
        <colgroup>@for ($i = 0; $i < 12; $i++)<col style="width:8.333%">@endfor</colgroup>
        <tr><td colspan="12" class="bar">PERSONAL INFORMATION</td></tr>
        <tr>
            <td colspan="4">Surname: <span class="v">{{ $blank ? '' : $a->last_name }}</span></td>
            <td colspan="8">Surname at Birth: (former family name, if married) <span class="v">{{ $d('surname_at_birth') }}</span></td>
        </tr>
        <tr><td colspan="12">First name: <span class="v">{{ $blank ? '' : trim($a->first_name.' '.$a->middle_name) }}</span></td></tr>
        <tr>
            <td colspan="3" style="height:38pt;">Date of Birth:(DD-MM-YYYY) <span class="v">{{ $blank ? '' : $date($a->date_of_birth) }}</span></td>
            <td colspan="4">Place of Birth: <span class="v">{{ $d('place_of_birth') }}</span><span style="display:block;margin-top:{{ $d('place_of_birth') ? '0' : '12pt' }};">Country of Birth: <span class="v inline">{{ $d('country_of_birth') }}</span></span></td>
            <td colspan="5">Nationality: <span class="v">{{ $blank ? '' : $a->nationality }}</span></td>
        </tr>
        <tr>
            <td colspan="3">Sex: <span class="v">{{ $blank ? '' : $a->gender }}</span></td>
            <td colspan="4">Status: <span class="v">{{ $pick('civil_status', W::CIVIL_STATUSES) }}</span></td>
            <td colspan="5">Phone Number: <span class="v">{{ $d('phone') }}</span></td>
        </tr>
        <tr>
            <td colspan="7" style="height:52pt;">Applicant’s home address: <span class="v">{{ $d('home_address') }}</span></td>
            <td colspan="5">E-mail address: <span class="v lower">{{ $d('email') }}</span></td>
        </tr>
        <tr>
            <td colspan="5" style="height:44pt;">Main purpose of the journey: <span class="v">{{ $pick('purpose', W::KOREA_PURPOSES) }}</span></td>
            <td colspan="7">Intended date of arrival: <span class="v">{{ $dd('arrival_date') }}</span></td>
        </tr>

        {{-- Work and education are open sections on the original: labels across, no inner lines. --}}
        <tr>
            <td colspan="12" style="padding:3pt 5pt 4pt;">
                <b>WORK INFORMATION</b>
                <div style="display:grid;grid-template-columns:1.1fr 0.9fr 0.8fr;column-gap:10pt;row-gap:6pt;margin-top:6pt;">
                    <span class="slot">Street Address: <span class="v">{{ $d('work_street') }}</span></span>
                    <span class="slot">City: <span class="v">{{ $d('work_city') }}</span></span>
                    <span class="slot">Province: <span class="v">{{ $d('work_province') }}</span></span>
                    <span class="slot">Job Title: <span class="v">{{ $d('job_title') }}</span></span>
                    <span class="slot">Start date: <span class="v">{{ $dd('work_start_date') }}</span></span>
                    <span class="slot">Start from: <span class="v">{{ $d('work_start_from') }}</span></span>
                    <span class="slot">Contact Number: <span class="v">{{ $d('work_phone') }}</span></span>
                    <span class="slot" style="grid-column:span 2;">Email Address: <span class="v lower">{{ $d('work_email') }}</span></span>
                </div>
            </td>
        </tr>
        <tr>
            <td colspan="12" style="padding:3pt 5pt 4pt;">
                <b>EDUCATION INFORMATION</b>
                <div class="slot" style="margin-top:6pt;">Name of Institution: <span class="v">{{ $d('school_name') }}</span></div>
                <div class="slot" style="margin-top:6pt;">Address: <span class="v">{{ $d('school_address') }}</span></div>
                <div style="display:grid;grid-template-columns:1.1fr 0.9fr 0.8fr;column-gap:10pt;margin-top:6pt;">
                    <span class="slot">Course of Study: <span class="v">{{ $d('course') }}</span></span>
                    <span class="slot">Date of Attendance from: <span class="v">{{ $d('school_from') }}</span></span>
                    <span class="slot">Date of Attendance to: <span class="v">{{ $d('school_to') }}</span></span>
                </div>
            </td>
        </tr>

        <tr><td colspan="12" class="bar" style="font-style:italic;height:16pt;vertical-align:middle;">FAMILY INFORMATION</td></tr>
        <tr><td colspan="12" style="height:auto;padding:1pt 5pt;">SPOUSE:</td></tr>
        <tr>
            <td colspan="4" style="height:34pt;">Surname: <span class="v">{{ $d('spouse_surname') }}</span></td>
            <td colspan="8">First Name: <span class="v">{{ $d('spouse_first_name') }}</span></td>
        </tr>
        <tr><td colspan="12" style="height:34pt;">Address: <span class="v">{{ $d('spouse_address') }}</span></td></tr>
        <tr>
            <td colspan="4" style="height:32pt;">Phone Number: <span class="v">{{ $d('spouse_phone') }}</span></td>
            <td colspan="4">Email Address: <span class="v lower">{{ $d('spouse_email') }}</span></td>
            <td colspan="4">Nationality: <span class="v">{{ $d('spouse_nationality') }}</span></td>
        </tr>
        <tr>
            <td colspan="12" style="height:46pt;">
                <div style="display:grid;grid-template-columns:1fr 1.4fr;">
                    <span>Do you have any Children? <span class="v">{{ $yesNo('has_children') }}</span></span>
                    <span>Number of Children: <span class="v">{{ $d('children_count') }}</span></span>
                </div>
            </td>
        </tr>

        <tr><td colspan="12" class="bar" style="font-style:italic;height:16pt;vertical-align:middle;">INVITATION</td></tr>
        <tr>
            <td colspan="12" style="padding:3pt 5pt 6pt;">
                Is there anyone inviting the applicant for the visa? <span class="v inline">{{ $yesNo('invited') }}</span>
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;column-gap:10pt;row-gap:8pt;margin-top:2pt;">
                    <span class="slot" style="grid-column:span 2;">Name of inviting person: <span class="v">{{ $d('host_name') }}</span></span>
                    <span class="slot">Relationship to Applicant: <span class="v">{{ $d('host_relationship') }}</span></span>
                    <span class="slot">Birthday: <span class="v">{{ $dd('host_birthday') }}</span></span>
                    <span class="slot">Phone Number: <span class="v">{{ $d('host_phone') }}</span></span>
                    <span class="slot">Email Address: <span class="v lower">{{ $d('host_email') }}</span></span>
                    <span class="slot" style="grid-column:span 3;">Address: <span class="v">{{ $d('host_address') }}</span></span>
                </div>
            </td>
        </tr>
    </table>
</div>
</body>
</html>
