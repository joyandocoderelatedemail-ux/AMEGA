{{--
    AMEGA's authorized representative, shown when "Filed by a representative" is ticked. The name
    and accreditation number are fixed (ImmigrationServices); the server saves them whatever is sent.
    A null field name shows the values without sending them.
--}}
@php
    $lockedField = $field.' bg-gray-100 text-dark/80 cursor-not-allowed';
@endphp
<div>
    <label class="{{ $label }}">Authorized Representative</label>
    <input type="text" @if ($nameField) name="{{ $nameField }}" @endif value="{{ \App\Support\ImmigrationServices::REPRESENTATIVE_NAME }}" readonly class="{{ $lockedField }}">
</div>
<div>
    <label class="{{ $label }}">BI Accreditation No.</label>
    <input type="text" @if ($accreditationField) name="{{ $accreditationField }}" @endif value="{{ \App\Support\ImmigrationServices::REPRESENTATIVE_ACCREDITATION }}" readonly class="{{ $lockedField }}">
</div>
