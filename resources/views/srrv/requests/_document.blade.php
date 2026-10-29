{{-- One filed scan on an SRRV request: download it, or take it off if it was uploaded by mistake. --}}
<span class="ml-6 mt-1 flex items-center gap-2">
    <a href="{{ route('srrv.requests.documents.download', $document) }}" class="inline-flex items-center gap-1 text-[11px] font-semibold text-primary hover:underline max-w-[18rem] truncate">
        <i data-lucide="paperclip" class="w-3.5 h-3.5 shrink-0"></i><span class="truncate">{{ $document->original_name }}</span>
    </a>
    <form method="POST" action="{{ route('srrv.requests.documents.destroy', $document) }}" onsubmit="return confirm('Take off this file?');" class="m-0">
        @csrf
        @method('DELETE')
        <button type="submit" class="text-[11px] text-rose-600 hover:underline">Remove</button>
    </form>
</span>
