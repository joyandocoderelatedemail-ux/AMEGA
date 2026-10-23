{{-- The second half of a Non-Immigrant file's Documents step: the Acknowledgment of Documents, printed and signed. --}}
<div class="mt-4 pt-4 border-t border-gray-100">
    <p class="{{ $label }}">Acknowledgment of Documents</p>
    <p class="text-xs text-dark/70 mb-3">Once the documents are in, print the Acknowledgment Receipt, have the client sign it, then record it here.</p>
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
</div>
