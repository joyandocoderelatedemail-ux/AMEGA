{{-- The second half of a Non-Immigrant file's Documents step: the Acknowledgment of Documents, printed and signed. --}}
<div class="mt-4 pt-4 border-t border-gray-100">
    <p class="{{ $label }}">Acknowledgment of Documents</p>
    <p class="text-xs text-dark/70 mb-3">Once the documents are in, print the Acknowledgment Receipt, have the client sign it, then record it here and upload a scan of the signed copy, so the system keeps a record.</p>
    <div class="flex flex-wrap items-center gap-2">
        @if ($application->isCleared())
        <a href="{{ route('visa.applications.acknowledgement', $application) }}" target="_blank" rel="noopener" class="{{ $secondary }}">
            <i data-lucide="printer" class="w-4 h-4"></i> Print acknowledgment
        </a>
        @endif
        @if ($application->acknowledgement_signed_at)
            <span class="text-xs font-semibold text-emerald-700">Signed {{ $application->acknowledgement_signed_at->format('M d, Y g:i A') }}.</span>
        @else
            <form method="POST" action="{{ route('visa.applications.stage', $application) }}" class="m-0">
                @csrf
                <input type="hidden" name="action" value="acknowledged">
                <button type="submit" class="{{ $button }}"><i data-lucide="pen-line" class="w-4 h-4"></i> Client signed the acknowledgment</button>
            </form>
        @endif
    </div>

    {{-- The scanned signed copy, kept on the file --}}
    @php $signedScans = $application->documents->where('document_type', 'signed_acknowledgement'); @endphp
    <div class="mt-4">
        <p class="{{ $label }}">Signed copy (scan)</p>
        @foreach ($signedScans as $scan)
            <p class="mb-2 flex flex-wrap items-center gap-2 text-xs">
                <i data-lucide="file-check" class="w-4 h-4 text-emerald-600"></i>
                <a href="{{ route('visa.documents.download', [$scan, 'inline' => 1]) }}" target="_blank" rel="noopener" class="font-semibold text-primary hover:underline">{{ $scan->original_name }}</a>
                <span class="text-dark/50">uploaded {{ $scan->created_at?->format('M d, Y g:i A') }}</span>
            </p>
        @endforeach
        <form method="POST" action="{{ route('visa.documents.store', $application) }}" enctype="multipart/form-data" class="m-0 flex flex-wrap items-center gap-2">
            @csrf
            <input type="hidden" name="document_type" value="signed_acknowledgement">
            <label for="signed-acknowledgement-file" class="sr-only">Scan of the signed acknowledgment</label>
            <input id="signed-acknowledgement-file" type="file" name="file" required accept=".jpg,.jpeg,.png,.webp,.pdf"
                   class="text-xs text-dark/70 file:mr-3 file:px-3 file:py-2 file:rounded-xl file:border-0 file:bg-gray-100 file:text-dark/70 file:font-bold file:text-xs">
            <button type="submit" class="{{ $secondary }}"><i data-lucide="upload" class="w-4 h-4"></i> {{ $signedScans->isNotEmpty() ? 'Upload another' : 'Upload scan' }}</button>
        </form>
        @error('file') <p class="mt-1 text-[11px] text-rose-600">{{ $message }}</p> @enderror
    </div>
</div>
