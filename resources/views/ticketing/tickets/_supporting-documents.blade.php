{{--
    Supporting documents kept with the booking, so the office has a copy: the real e-ticket,
    boarding passes, hotel and tour vouchers, insurance policies, receipts. Expects $ticket.
--}}
@php
    $supporting = $ticket->supportingDocuments()->with('uploader')->get();
    $uploadErrors = collect(['document_type', 'note', 'files'])->merge(collect($errors->keys())->filter(fn ($key) => str_starts_with($key, 'files.')))
        ->map(fn ($key) => $errors->first($key))->filter()->unique();
@endphp
<div id="supporting-documents" class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-5 sm:p-6 space-y-4 scroll-mt-24 print:hidden"
     x-data="{ adding: {{ $uploadErrors->isNotEmpty() ? 'true' : 'false' }}, chosen: [] }">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h2 class="font-heading text-base font-bold text-slate-900">Supporting Documents</h2>
            <p class="text-xs text-slate-500 mt-0.5">Keep a copy of the real e-ticket, boarding passes, hotel or tour vouchers and receipts with this booking.</p>
        </div>
        <button type="button" x-show="!adding" @click="adding = true; $nextTick(() => window.lucide && lucide.createIcons())"
                class="inline-flex items-center gap-1.5 h-10 px-4 rounded-lg bg-navy-700 hover:bg-navy-800 text-white text-xs font-semibold transition-colors">
            <i data-lucide="upload" class="w-4 h-4"></i> Upload documents
        </button>
    </div>

    <!-- Upload: one kind of document at a time, several files at once -->
    <form x-show="adding" x-cloak method="POST" action="{{ route('ticketing.tickets.supporting-documents.store', $ticket) }}" enctype="multipart/form-data"
          class="rounded-xl border border-slate-200 bg-slate-50 p-4 space-y-3">
        @csrf
        @if ($uploadErrors->isNotEmpty())
            <div role="alert" class="rounded-lg bg-rose-50 border border-rose-200 px-3 py-2 text-xs text-rose-800">
                @foreach ($uploadErrors as $message)
                    <p>{{ $message }}</p>
                @endforeach
            </div>
        @endif
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
                <label for="supporting_type" class="block text-xs font-semibold text-slate-600 mb-1">Document</label>
                <select id="supporting_type" name="document_type" required
                        class="w-full h-10 px-3 rounded-lg border border-slate-300 bg-white text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-navy-700">
                    @foreach (\App\Models\TicketBookingDocument::TYPES as $value => $label)
                        <option value="{{ $value }}" @selected(old('document_type', 'e_ticket') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="supporting_note" class="block text-xs font-semibold text-slate-600 mb-1">Note <span class="font-normal text-slate-400">(optional)</span></label>
                <input id="supporting_note" type="text" name="note" maxlength="255" value="{{ old('note') }}" placeholder="e.g. Return flight, Hotel in Cebu"
                       class="w-full h-10 px-3 rounded-lg border border-slate-300 bg-white text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-navy-700">
            </div>
        </div>
        <div>
            <label class="flex flex-col items-center justify-center gap-1 rounded-lg border-2 border-dashed border-slate-300 bg-white px-4 py-5 text-center cursor-pointer hover:border-navy-700 focus-within:ring-2 focus-within:ring-navy-700">
                <i data-lucide="file-up" class="w-5 h-5 text-slate-400"></i>
                <span class="text-xs font-semibold text-slate-700" x-text="chosen.length ? chosen.join(', ') : 'Choose files (photo or PDF)'">Choose files (photo or PDF)</span>
                <span class="text-[11px] text-slate-500">JPG, PNG, WEBP or PDF, up to 10 MB each, 10 files at a time.</span>
                <input type="file" name="files[]" multiple required accept=".pdf,.jpg,.jpeg,.png,.webp" class="sr-only"
                       @change="chosen = Array.from($event.target.files).map(file => file.name)">
            </label>
        </div>
        <div class="flex items-center justify-end gap-2">
            <button type="button" @click="adding = false" class="h-10 px-4 rounded-lg border border-slate-300 bg-white text-xs font-semibold text-slate-700 hover:bg-slate-50">Cancel</button>
            <button type="submit" class="h-10 px-4 rounded-lg bg-navy-700 hover:bg-navy-800 text-white text-xs font-semibold">Save documents</button>
        </div>
    </form>

    <!-- What is on file -->
    @if ($supporting->isEmpty())
        <p class="rounded-xl border border-dashed border-slate-200 px-4 py-5 text-center text-xs text-slate-500">No supporting documents yet.</p>
    @else
        <ul class="rounded-xl border border-slate-200 divide-y divide-slate-100">
            @foreach ($supporting as $document)
                <li class="flex flex-wrap items-center justify-between gap-3 px-4 py-3">
                    <div class="flex items-center gap-3 min-w-0">
                        <span class="w-9 h-9 rounded-lg bg-slate-100 text-slate-500 flex items-center justify-center shrink-0">
                            <i data-lucide="{{ str_contains((string) $document->mime_type, 'pdf') ? 'file-text' : 'image' }}" class="w-4 h-4"></i>
                        </span>
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-slate-900 truncate">{{ $document->typeLabel() }}@if ($document->note)<span class="font-normal text-slate-500"> &middot; {{ $document->note }}</span>@endif</p>
                            <p class="text-xs text-slate-500 truncate">{{ $document->original_name }} &middot; {{ $document->created_at->format('M j, Y g:ia') }}@if ($document->uploader) &middot; {{ $document->uploader->name }}@endif</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <a href="{{ route('ticketing.supporting-documents.download', $document) }}"
                           class="inline-flex items-center gap-1 h-8 px-3 rounded-lg border border-slate-300 bg-white text-xs font-semibold text-slate-700 hover:bg-slate-50">
                            <i data-lucide="download" class="w-3.5 h-3.5"></i> Download
                        </a>
                        <form method="POST" action="{{ route('ticketing.tickets.supporting-documents.destroy', [$ticket, $document]) }}"
                              onsubmit="return confirm('Remove {{ e(addslashes($document->original_name)) }} from this booking?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="h-8 w-8 inline-flex items-center justify-center rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50" aria-label="Remove {{ $document->original_name }}">
                                <i data-lucide="trash-2" class="w-4 h-4"></i>
                            </button>
                        </form>
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</div>
