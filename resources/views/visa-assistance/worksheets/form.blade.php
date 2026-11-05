@php
    use App\Support\VisaWorksheets;
    use App\Support\VisaWorksheetSpecs;

    /**
     * AMEGA's country application forms (China, New Zealand, Australia, Canada, the UK, the US), printed on one shared
     * layout: the AMEGA logo and the form's heading, then a bar per section in the form's own colour, with the answers
     * under each label. Filled from the applicant record and their worksheet details, or blank for the client.
     * Expects $worksheet, $applicant, $blank, $links.
     */
    [$docTitle, $heading, $bar] = VisaWorksheetSpecs::META[$worksheet] ?? [VisaWorksheets::TITLES[$worksheet] ?? 'Application form', 'APPLICATION FORM', '#d9d9d9'];
    $sections = VisaWorksheets::sections($worksheet);
    $a = $applicant;
    $date = fn ($value) => $value ? $value->format('d-m-Y') : '';
    $box = fn (bool $on) => $on ? '☑' : '☐';
    $value = function (array $field) use ($a, $blank, $date, $box): string {
        [$key, , $type] = $field;
        $options = $type === 'yesno' ? ['yes' => 'Yes', 'no' => 'No'] : ($field[3] ?? []);
        $answer = $blank ? null : $a->detail($key);

        return match (true) {
            // A short choice prints as its boxes, the one picked ticked, so the client can tick a blank one by hand.
            in_array($type, ['yesno', 'select'], true) && count($options) <= 4 => collect($options)->map(fn ($label, $code) => $box($answer === $code).' '.$label)->implode('   '),
            $type === 'select' => $options[$answer] ?? '',
            $type === 'date' => $blank ? '' : $date($a->dateDetail($key)),
            default => (string) $answer,
        };
    };
    // A long answer (an address, the details of a visit, a long question, a choice of three or more) takes the whole row.
    $wide = fn (array $field) => preg_match('/address|details|reason|dates|duties|activit|visited|places|explain|list|plans/', $field[0]) === 1
        || mb_strlen($field[1]) > 40 || ($field[2] === 'select' && count($field[3] ?? []) >= 3);
    // The rows, in the form's order: up to three short answers a row (a short row is padded with empty boxes), a long one alone.
    $rows = function (array $fields) use ($wide): array {
        $rows = [];
        $row = [];
        foreach ($fields as $field) {
            if ($wide($field)) {
                if ($row) { $rows[] = $row; $row = []; }
                $rows[] = [$field];
                continue;
            }
            $row[] = $field;
            if (count($row) === 3) { $rows[] = $row; $row = []; }
        }
        if ($row) { $rows[] = $row; }

        return $rows;
    };
    $colon = fn (string $label) => str_ends_with($label, '?') ? $label : $label.':';
    $record = [
        'Surname / family name' => $blank ? '' : $a->last_name,
        'Given names' => $blank ? '' : trim($a->first_name.' '.$a->middle_name),
        'Date of birth' => $blank ? '' : $date($a->date_of_birth),
        'Sex' => $blank ? '' : ucfirst((string) $a->gender),
        'Nationality' => $blank ? '' : $a->nationality,
        'Passport number' => $blank ? '' : $a->passport_number,
        'Passport valid until' => $blank ? '' : $date($a->passport_expiry_date),
    ];
    $count = collect($sections)->flatten(1)->count();
@endphp
@include('visa-assistance.worksheets._open', ['docTitle' => $docTitle, 'pages' => max(1, (int) ceil($count / 38))])
<style>
    .sheet { height: auto; min-height: 297mm; padding-bottom: 16pt; }
    .bar { background: {{ $bar }}; font-weight: bold; padding: 2pt 5pt; border: 1.2px solid #000; text-transform: uppercase; font-size: 9.6pt; }
    /* Every cell draws its right and bottom line, the grid its left one: right whatever the row a wide answer breaks. */
    .grid { display: grid; grid-template-columns: repeat(3, 1fr); border-left: 1.2px solid #000; }
    .grid > div { border-right: 1px solid #000; border-bottom: 1px solid #000; padding: 2pt 5pt 3pt; min-height: 24pt; }
    .grid > div.wide { grid-column: span 3; }
    .section { break-inside: avoid; page-break-inside: avoid; margin-top: -1px; }
    .label { font-size: 9pt; }
</style>

<div class="sheet">
    @include('visa-assistance.worksheets._masthead', ['heading' => $heading])

    <div class="section">
        <div class="bar">Applicant</div>
        <div class="grid">
            @foreach ($record as $recordLabel => $recordValue)
                <div><span class="label">{{ $recordLabel }}:</span> <span class="v">{{ $recordValue }}</span></div>
            @endforeach
            <div></div><div></div>
        </div>
    </div>

    @foreach ($sections as $sectionTitle => $fields)
        <div class="section">
            <div class="bar">{{ $sectionTitle }}</div>
            <div class="grid">
                @foreach ($rows($fields) as $row)
                    @foreach ($row as $field)
                        <div class="{{ count($row) === 1 && $wide($field) ? 'wide' : '' }}">
                            <span class="label">{{ $colon($field[1]) }}</span>
                            <span class="v {{ in_array($field[2], ['yesno', 'select'], true) ? 'inline' : '' }} {{ str_contains($field[0], 'email') ? 'lower' : '' }}">{{ $value($field) }}</span>
                        </div>
                    @endforeach
                    @unless (count($row) === 1 && $wide($row[0]))
                        @for ($i = count($row); $i < 3; $i++)<div></div>@endfor
                    @endunless
                @endforeach
            </div>
        </div>
    @endforeach
</div>
</body>
</html>
