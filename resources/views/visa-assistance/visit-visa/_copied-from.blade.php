{{-- A new file opened from an earlier one ("New file from this one"): which file, and the optional copy of its documents. --}}
@php $source = old('copy_from') ? \App\Models\VisaApplication::with('documents')->find((int) old('copy_from')) : null; @endphp
@if ($source)
    @php $copyable = $source->documents->where('document_type', '!=', 'signed_acknowledgement')->count(); @endphp
    <div class="rounded-2xl bg-primary/5 border border-primary/20 p-4 space-y-2">
        <input type="hidden" name="copy_from" value="{{ $source->id }}">
        <p class="text-xs font-bold text-dark flex items-center gap-2"><i data-lucide="copy-plus" class="w-4 h-4 text-primary"></i> Copied from {{ $source->reference }}: change whatever is different.</p>
        <p class="text-[11px] text-dark/60">The client, the service's details and the draft form answers are filled in. Fees, payments, signatures and results start fresh.</p>
        @if ($copyable > 0)
            <label class="flex items-start gap-2 text-xs text-dark cursor-pointer">
                <input type="checkbox" name="copy_documents" value="1" @checked(old('copy_documents')) class="w-4 h-4 mt-0.5 rounded text-primary border-gray-300">
                <span>Also copy the documents ({{ $copyable }} filed), for things that rarely change like the birth certificate.</span>
            </label>
        @endif
    </div>
@endif
