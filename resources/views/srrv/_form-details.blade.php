{{-- The PRA forms' extra fields on an SRRV file or renewal. Expects $details and $forRenewal. --}}
@include('shared._form-details', [
    'sections' => \App\Support\SrrvForms::sections($forRenewal),
    'title' => 'PRA Form Details',
    'intro' => $forRenewal
        ? 'Printed on the Retiree Request Form. Anything left blank is taken from the linked retiree file.'
        : 'Printed on the PRA application form, checklist and request form. Fill in what the retiree gives you; blanks print empty.',
])
