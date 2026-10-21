{{--
    Shown when an admin opens a file from the Immigration Approvals page: the file is a read-only preview.
    The admin approves it or returns it with a reason from here. Expects $reviewKind, $reviewId, $reviewPending.
--}}
<div class="rounded-2xl bg-slate-800 text-white px-5 py-4 space-y-3" x-data="{ mode: null }" role="status">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-2.5">
            <i data-lucide="eye" class="w-5 h-5 shrink-0"></i>
            <div>
                <p class="text-sm font-bold">Preview only</p>
                <p class="text-[11px] text-white/70">This is the agent's file as saved. Nothing here can be changed from this page.</p>
            </div>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('admin.immigration-approvals.index') }}" class="px-4 py-2 rounded-lg bg-white/10 hover:bg-white/20 text-xs font-bold">Back to approvals</a>
            @if ($reviewPending)
                <button type="button" @click="mode = mode === 'approve' ? null : 'approve'" class="px-4 py-2 rounded-lg bg-emerald-500 hover:bg-emerald-600 text-white text-xs font-bold">Approve</button>
                <button type="button" @click="mode = mode === 'return' ? null : 'return'" class="px-4 py-2 rounded-lg bg-white text-slate-800 hover:bg-slate-100 text-xs font-bold">Return</button>
            @endif
        </div>
    </div>

    @if ($reviewPending)
        <form x-show="mode === 'approve'" style="display: none" method="POST" action="{{ route('admin.immigration-approvals.approve', [$reviewKind, $reviewId]) }}" class="flex flex-wrap items-center gap-3 rounded-xl bg-white/10 px-4 py-3">
            @csrf
            <label class="flex items-center gap-2 text-xs font-semibold">
                <input type="checkbox" name="admin_acknowledged" value="1" class="w-4 h-4 rounded">
                I reviewed the dates and details and they are correct.
            </label>
            <button type="submit" class="px-4 py-2 rounded-lg bg-emerald-500 hover:bg-emerald-600 text-white text-xs font-bold">Approve</button>
        </form>
        <form x-show="mode === 'return'" style="display: none" method="POST" action="{{ route('admin.immigration-approvals.reject', [$reviewKind, $reviewId]) }}" class="flex flex-wrap items-center gap-3 rounded-xl bg-white/10 px-4 py-3">
            @csrf
            <label for="preview-note" class="sr-only">What to correct</label>
            <input id="preview-note" type="text" name="review_note" required maxlength="2000" placeholder="What does the agent need to correct?"
                   class="flex-1 min-w-[16rem] px-3 py-2 rounded-lg bg-white text-slate-900 text-xs placeholder:text-slate-400">
            <button type="submit" class="px-4 py-2 rounded-lg bg-white text-slate-800 hover:bg-slate-100 text-xs font-bold">Return to the agent</button>
        </form>
    @endif

    @if ($errors->any())
        <p class="text-xs font-semibold text-rose-200">{{ $errors->first() }}</p>
    @endif
</div>
