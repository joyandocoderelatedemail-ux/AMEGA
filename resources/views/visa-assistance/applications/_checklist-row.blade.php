@php
    $ticked = $progress[$owner][$row['id']] ?? [];
    $uploaded = isset($row['doc']) && str_starts_with($owner, 'applicant:')
        && $application->documents->contains(fn ($document) => $document->document_type === $row['doc'] && $document->visa_applicant_id === (int) substr($owner, strlen('applicant:')));
@endphp
<tr>
    @foreach (['orig', 'copy'] as $column)
        <td class="{{ $cell }}">
            <input type="checkbox" class="{{ $box }}" @checked(! empty($ticked[$column]))
                   aria-label="{{ ucfirst($column) }}: {{ $row['label'] }}"
                   @change="toggle({{ Js::from($owner) }}, {{ Js::from($row['id']) }}, '{{ $column }}', $event.target)">
        </td>
    @endforeach
    <td class="px-2 py-1.5 text-dark/80 {{ $indent ? 'pl-6' : '' }}">
        {{ $row['label'] }}
        @if (! empty($row['optional']))<span class="text-[10px] font-semibold text-dark/40">(optional)</span>@endif
        @if ($uploaded)<span class="ml-1 text-[10px] font-bold text-emerald-700">Uploaded</span>@endif
    </td>
</tr>
