{{--
    The admin's read-only preview of a file. When it is waiting for approval (opened from the Immigration Approvals
    page) the admin approves it or returns it with a reason from here; otherwise they can press Edit to change it.
    Expects $service, $reviewKind, $reviewId, $reviewPending.
--}}
<div class="rounded-xl bg-white border border-slate-200 border-l-4 border-l-amber-400 shadow-sm px-5 py-4 space-y-3" x-data="{ mode: null }" role="status">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-2.5">
            <span class="w-9 h-9 shrink-0 rounded-lg bg-slate-100 text-slate-600 flex items-center justify-center"><i data-lucide="eye" class="w-[18px] h-[18px]"></i></span>
            <div>
                @if ($reviewPending)
                    <p class="text-sm font-bold text-slate-900">Preview only</p>
                    <p class="text-xs text-slate-500">This is the agent's file as saved. Nothing here can be changed from this page.</p>
                @else
                    <p class="text-sm font-bold text-slate-900">{{ $client->full_name }} &middot; {{ $service['label'] }}</p>
                    <p class="text-xs text-slate-500">The whole file, as saved. Press Edit to change it.</p>
                @endif
            </div>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            @if ($reviewPending)
                <a href="{{ route('admin.immigration-approvals.index') }}" class="px-3 h-9 inline-flex items-center rounded-lg border border-slate-200 bg-white text-slate-700 hover:bg-slate-50 text-xs font-bold">Back to approvals</a>
            @else
                <a href="{{ route('admin.client-sheets.applications', ['service' => $service['key']]) }}" class="px-3 h-9 inline-flex items-center rounded-lg border border-slate-200 bg-white text-slate-700 hover:bg-slate-50 text-xs font-bold">Back to applications</a>
                {{-- An admin may edit any file that is not waiting for approval; a released one opens with its Reopen control. --}}
                <a href="{{ request()->fullUrlWithoutQuery('preview') }}" class="px-4 h-9 inline-flex items-center rounded-lg bg-navy-700 hover:bg-navy-800 text-white text-xs font-bold">Edit</a>
            @endif
            @if ($reviewPending)
                <button type="button" @click="mode = mode === 'approve' ? null : 'approve'" class="px-4 h-9 inline-flex items-center rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold">Approve</button>
                <button type="button" @click="mode = mode === 'return' ? null : 'return'" class="px-4 h-9 inline-flex items-center rounded-lg border border-rose-200 bg-white text-rose-600 hover:bg-rose-50 text-xs font-bold">Return</button>
            @endif
        </div>
    </div>

    @if ($reviewPending)
        <form x-show="mode === 'approve'" style="display: none" method="POST" action="{{ route('admin.immigration-approvals.approve', [$reviewKind, $reviewId]) }}" class="flex flex-wrap items-center gap-3 rounded-lg bg-slate-50 border border-slate-200 px-4 py-2.5">
            @csrf
            <label class="flex items-center gap-2 text-xs font-semibold">
                <input type="checkbox" name="admin_acknowledged" value="1" class="w-4 h-4 rounded border-slate-300">
                I reviewed the dates and details and they are correct.
            </label>
            <button type="submit" class="px-4 h-9 inline-flex items-center rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold">Approve</button>
        </form>
        <form x-show="mode === 'return'" style="display: none" method="POST" action="{{ route('admin.immigration-approvals.reject', [$reviewKind, $reviewId]) }}" class="flex flex-wrap items-center gap-3 rounded-lg bg-slate-50 border border-slate-200 px-4 py-2.5">
            @csrf
            <label for="preview-note" class="sr-only">What to correct</label>
            <input id="preview-note" type="text" name="review_note" required maxlength="2000" placeholder="What does the agent need to correct?"
                   class="flex-1 min-w-[16rem] px-3 py-2 rounded-lg bg-white border border-slate-300 text-slate-900 text-xs placeholder:text-slate-400">
            <button type="submit" class="px-4 h-9 inline-flex items-center rounded-lg border border-rose-200 bg-white text-rose-600 hover:bg-rose-50 text-xs font-bold">Return to the agent</button>
        </form>
    @endif

    @if ($errors->any())
        <p class="text-xs font-semibold text-rose-600">{{ $errors->first() }}</p>
    @endif
</div>
