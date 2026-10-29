{{--
    Shown when an SRRV file is opened from its service's list or the approvals page: the whole file, read-only,
    on one page. Admins approve or return it from here; the agent who owns it can go back, edit (while it can
    still change) or start the next one. Expects $file, $kind ('application' or 'renewal') and $label.
--}}
@php
    $isAdmin = auth()->user()->isAdmin();
    $hold = \App\Support\CaseProgress::approvalHold($file->approval_status);
    $canEdit = $isAdmin || $file->approval_status === null || $file->isReturned();
    // Each file belongs to its visa type's service: SRRV Classic, SRRV Courtesy, or their renewals.
    $service = \App\Http\Controllers\Srrv\SrrvClientLookupController::serviceFor($kind, $kind === 'request' ? $file->type : $file->visa_class);
    $label = \App\Http\Controllers\Srrv\SrrvClientLookupController::SERVICES[$service]['label'];
    $backUrl = route('srrv.services', $service);
    $showUrl = route(match ($kind) { 'application' => 'srrv.applications.show', 'request' => 'srrv.requests.show', default => 'srrv.renewals.show' }, $file);
    $editUrl = request()->fullUrlWithoutQuery('preview');
    // The retiree's client account (matched the way the file was saved), so New starts with them filled in.
    $client = \App\Models\User::where('role', 'client')
        ->where(fn ($q) => $q->when($file->retiree_email, fn ($w) => $w->where('email', strtolower((string) $file->retiree_email)))->orWhere('name', $file->retiree_name))
        ->first();
    $newUrl = $client
        ? \App\Http\Controllers\Srrv\SrrvClientLookupController::formUrl($service, $client->id)
        : route('srrv.start', $service);
@endphp
<div class="mb-6 rounded-2xl bg-slate-800 text-white px-5 py-4 space-y-3" x-data="{ mode: null }" role="status">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-2.5">
            <i data-lucide="eye" class="w-5 h-5 shrink-0"></i>
            <div>
                <p class="text-sm font-bold">{{ $file->retiree_name }} &middot; {{ $label }} &middot; <span class="font-mono">{{ $file->reference }}</span></p>
                <p class="text-[11px] text-white/70">
                    @if ($isAdmin)
                        The file as saved. Nothing here can be changed from this page.
                    @elseif ($canEdit)
                        The whole file, as saved. Press Edit to change it.
                    @else
                        The whole file, as saved. It can't be changed while it is with the admin or once approved.
                    @endif
                </p>
            </div>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ $isAdmin ? route('admin.srrv-approvals.index') : $backUrl }}" class="px-4 py-2 rounded-lg bg-white/10 hover:bg-white/20 text-xs font-bold">{{ $isAdmin ? 'Back to approvals' : 'Back to files' }}</a>
            <a href="{{ $showUrl }}" class="px-4 py-2 rounded-lg bg-white/10 hover:bg-white/20 text-xs font-bold">Open file</a>
            @if ($isAdmin && $file->isAwaitingApproval())
                <button type="button" @click="mode = mode === 'approve' ? null : 'approve'" class="px-4 py-2 rounded-lg bg-emerald-500 hover:bg-emerald-600 text-white text-xs font-bold">Approve</button>
                <button type="button" @click="mode = mode === 'return' ? null : 'return'" class="px-4 py-2 rounded-lg bg-white text-slate-800 hover:bg-slate-100 text-xs font-bold">Return</button>
            @endif
            @if (! $isAdmin && $canEdit)
                <a href="{{ $editUrl }}" class="px-4 py-2 rounded-lg bg-white text-slate-800 hover:bg-slate-100 text-xs font-bold">Edit</a>
            @endif
            @unless ($isAdmin)
                <a href="{{ $newUrl }}" class="px-4 py-2 rounded-lg bg-accent text-dark hover:bg-accent-dark text-xs font-bold">New {{ $label }}</a>
            @endunless
        </div>
    </div>

    {{-- The status: waiting for the admin shows here, in the same chip as the stage. --}}
    <div class="flex flex-wrap items-center gap-3 rounded-xl bg-white/10 px-4 py-3">
        <span class="text-[11px] font-bold uppercase tracking-wider text-white/70">Status</span>
        <span class="inline-flex px-2.5 py-1.5 rounded-lg border text-[11px] font-bold {{ \App\Support\CaseProgress::tone($hold ?? 'preparing') }}">
            {{ $hold ? \App\Support\CaseProgress::holdLabel($hold) : $file->status_label }}
        </span>
        @if ($hold === 'returned' && $file->review_note)
            <span class="text-xs font-semibold text-rose-200">{{ $file->review_note }}</span>
        @endif
    </div>

    @if ($isAdmin && $file->isAwaitingApproval())
        <form x-show="mode === 'approve'" style="display: none" method="POST" action="{{ route('admin.srrv-approvals.approve', [$kind, $file->getKey()]) }}" class="flex flex-wrap items-center gap-3 rounded-xl bg-white/10 px-4 py-3">
            @csrf
            <label class="flex items-center gap-2 text-xs font-semibold">
                <input type="checkbox" name="admin_acknowledged" value="1" class="w-4 h-4 rounded">
                I reviewed the details and they are correct.
            </label>
            <button type="submit" class="px-4 py-2 rounded-lg bg-emerald-500 hover:bg-emerald-600 text-white text-xs font-bold">Approve</button>
        </form>
        <form x-show="mode === 'return'" style="display: none" method="POST" action="{{ route('admin.srrv-approvals.reject', [$kind, $file->getKey()]) }}" class="flex flex-wrap items-center gap-3 rounded-xl bg-white/10 px-4 py-3">
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
