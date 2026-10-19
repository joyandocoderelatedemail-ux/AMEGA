@php
    use App\Models\ImmigrationPetition;

    /*
     * A case's requirements step: the checklist with a tick and an upload per item,
     * earlier released cases, and the filing dates. Expects $petition, $client, $label,
     * $field, $section, $heading, $intro, and $show (item => Alpine expression that shows it).
     */
    $type = $petition->type;
    $checked = old('petition.checklist', $petition->checklist ?? []);
    $previous = $client->exists ? $client->petitions->where('type', $type)->where('released_on', '!=', null) : collect();
@endphp

<div class="border-b border-gray-100 pb-2">
    <h3 id="step-{{ $type }}-requirements" class="{{ $section }}">{{ $heading }}</h3>
    <p class="text-[11px] text-dark/50 mt-0.5">{{ $intro }}</p>
</div>

<ul class="rounded-2xl border border-gray-200 divide-y divide-gray-100">
    @foreach (ImmigrationPetition::CHECKLISTS[$type] as $item => $itemLabel)
        @php $file = $petition->documentFor($item); @endphp
        <li class="px-4 py-3 space-y-2" x-show="{{ $show[$item] ?? 'true' }}" x-data="{ fileName: '', removing: false }">
            <div class="flex items-start gap-3">
                <input type="checkbox" name="petition[checklist][]" value="{{ $item }}" id="{{ $type }}-{{ $item }}"
                       class="w-4 h-4 mt-0.5 rounded text-primary" {{ in_array($item, $checked, true) ? 'checked' : '' }}>
                <label for="{{ $type }}-{{ $item }}" class="flex-1 min-w-0 text-xs text-dark cursor-pointer font-semibold">{{ $itemLabel }}</label>
                <label class="shrink-0 inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-gray-200 text-[11px] font-bold text-primary cursor-pointer hover:border-primary">
                    <i data-lucide="upload" class="w-3.5 h-3.5"></i>
                    <span x-text="fileName ? 'Change' : '{{ $file ? 'Replace' : 'Upload' }}'"></span>
                    <input type="file" name="petition[files][{{ $item }}]" accept=".jpg,.jpeg,.png,.webp,.pdf" class="sr-only"
                           @change="fileName = $event.target.files[0]?.name || ''">
                </label>
            </div>

            @if ($file && $client->exists && $petition->exists)
                <div class="pl-7 flex flex-wrap items-center gap-3 text-[11px]" x-show="! removing">
                    <a href="{{ route('admin.client-sheets.petition-file', [$client, $petition, $item]) }}" target="_blank" rel="noopener"
                       class="inline-flex items-center gap-1.5 font-semibold text-dark hover:text-primary min-w-0">
                        <i data-lucide="file-text" class="w-3.5 h-3.5 text-primary shrink-0"></i>
                        <span class="truncate">{{ $file['name'] }}</span>
                    </a>
                    <button type="button" @click="removing = true" class="font-bold text-rose-600 hover:underline">Remove</button>
                </div>
                <template x-if="removing">
                    <p class="pl-7 text-[11px] text-rose-700">
                        <input type="hidden" name="petition[remove_files][]" value="{{ $item }}">
                        The file will be removed when you save. <button type="button" @click="removing = false" class="font-bold underline">Keep it</button>
                    </p>
                </template>
            @endif
            <p x-show="fileName" x-cloak class="pl-7 text-[11px] font-semibold text-dark" x-text="'New file: ' + fileName"></p>
            @error("petition.files.$item") <p class="pl-7 text-[11px] font-semibold text-rose-600">{{ $message }}</p> @enderror
        </li>
    @endforeach
</ul>

@if ($previous->isNotEmpty())
    <div class="rounded-2xl border border-gray-200 p-4">
        <span class="block text-[10px] font-bold uppercase tracking-wider text-dark/50 mb-2">Earlier cases</span>
        <ul class="space-y-1 text-xs text-dark/70">
            @foreach ($previous as $earlier)
                <li>{{ $earlier->reference() }} &middot; filed {{ $earlier->filed_on?->format('M d, Y') ?? 'on an unrecorded date' }} &middot; released {{ $earlier->released_on->format('M d, Y') }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="space-y-4 pt-2">
    <div class="border-b border-gray-100 pb-2">
        <h3 class="{{ $section }}">Filing with the BI</h3>
        <p class="text-[11px] text-dark/50 mt-0.5">A release date closes this case.</p>
    </div>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
            <label class="{{ $label }}">Date filed</label>
            <input type="date" name="petition[filed_on]" value="{{ old('petition.filed_on', $petition->filed_on?->format('Y-m-d')) }}" class="{{ $field }}">
        </div>
        <div>
            <label class="{{ $label }}">Date released</label>
            <input type="date" name="petition[released_on]" value="{{ old('petition.released_on', $petition->released_on?->format('Y-m-d')) }}" class="{{ $field }}">
            @error('petition.released_on') <p class="mt-1 text-[11px] font-semibold text-rose-600">{{ $message }}</p> @enderror
        </div>
    </div>
</div>
