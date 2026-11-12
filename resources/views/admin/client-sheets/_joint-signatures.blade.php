{{-- The two spouses' signatures, their IDs and the notary's jurat, shared by the 13(A) joint letter and the joint affidavit. Uses the variables of petition-joint-letter. --}}
<div class="sig" style="margin-top:28pt;">Very respectfully,</div>
<div class="sig" style="margin-top:36pt;">{!! $line($petitioner, '200pt') !!}<br><span class="role">{{ $petitionerRole }}-Petitioner</span></div>
<div class="sig" style="margin-top:48pt;">{!! $line($applicant, '200pt') !!}<br><span class="role">{{ $applicantRole ? ucfirst($applicantRole) : 'Spouse' }}/Applicant</span></div>

<p style="margin-top:80pt;text-indent:18pt;text-align:left;">IN WITNESS WHEREOF, we have set our hands this {!! $line($signedOn?->format('d F Y'), '120pt') !!} in the City of {{ $city }}, Philippines.</p>

<div style="display:grid;grid-template-columns:1fr 1fr;column-gap:24pt;line-height:1.2;">
    <div>
        {!! $line($applicant, '200pt') !!}<br>
        Passport No. {!! $line($blankForm ? null : $client->passport_number, '110pt') !!}<br>
        Valid until: {!! $line($date($passportValid), '100pt') !!}
    </div>
    <div>
        {!! $line($petitioner, '200pt') !!}<br>
        {!! $idType ? e($idType).' No.' : 'ID No.' !!} {!! $line($d('petitioner_id_number'), '110pt') !!}<br>
        Valid until: {!! $line($date($idValid), '100pt') !!}
    </div>
</div>

<p class="indent" style="margin-top:22pt;">
    SUBSCRIBED AND SWORN TO before me this
    @if ($signedOn && $permanent)
        {{ $signedOn->format('d F Y') }}
    @elseif ($signedOn)
        {{ $signedOn->format('d') }}<sup>{{ strtoupper(substr($signedOn->format('jS'), -2)) }}</sup> day of {{ $signedOn->format('F Y') }}
    @else
        {!! $line(null, '40pt') !!} day of {!! $line(null, '110pt') !!}
    @endif
    at {{ $city }} City. Affiants presented to me their respective proof of identity as described above.
</p>
