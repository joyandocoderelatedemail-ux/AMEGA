@props(['file', 'type'])

@php
    /**
     * Who owns a desk file. Desk files are private to their owner, so admins
     * can take a file on themselves or hand it to a staff member at the same
     * desk here, including a file whose owner's account was removed.
     */
    [, $desk] = \App\Http\Controllers\Admin\FileOwnerController::FILES[$type];
    $owner = $file->created_by ? \App\Models\User::find($file->created_by) : null;
    $viewer = auth()->user();
    $canReassign = $viewer?->isAdmin();
    $staff = $canReassign ? \App\Models\User::deskStaff($desk) : collect();
@endphp

<div {{ $attributes->merge(['class' => 'rounded-2xl bg-white border shadow-sm px-5 py-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3 '.($owner ? 'border-gray-100' : 'border-amber-200')]) }}>
    <div class="flex items-center gap-3 min-w-0">
        <span class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0 {{ $owner ? 'bg-primary/10 text-primary' : 'bg-amber-100 text-amber-700' }}">
            <i data-lucide="{{ $owner ? 'user-round' : 'user-x' }}" class="w-4 h-4"></i>
        </span>
        <div class="min-w-0">
            <p class="text-[10px] font-bold uppercase tracking-wider text-dark/40">Owner</p>
            @if ($owner)
                <p class="text-xs font-bold text-dark truncate">
                    {{ $owner->id === $viewer?->id ? 'You' : $owner->name }}
                    <span class="font-normal text-dark/50">&middot; only the owner and admins can see this file</span>
                </p>
            @else
                <p class="text-xs font-bold text-amber-800">No owner &mdash; the account that opened it was removed. Only admins can see it.</p>
            @endif
        </div>
    </div>

    @if ($canReassign)
        <div class="flex flex-wrap items-center gap-2 shrink-0">
        @if ($file->created_by !== $viewer->id)
            <form method="POST" action="{{ route('admin.files.owner', ['type' => $type, 'id' => $file->id]) }}" class="m-0">
                @csrf
                <input type="hidden" name="created_by" value="{{ $viewer->id }}">
                <button type="submit" class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-white border border-primary/30 text-primary font-bold text-xs rounded-xl hover:bg-primary/5 transition-all">
                    <i data-lucide="hand" class="w-3.5 h-3.5"></i>
                    Assign to me
                </button>
            </form>
        @endif
        <form method="POST" action="{{ route('admin.files.owner', ['type' => $type, 'id' => $file->id]) }}" class="flex items-center gap-2 m-0">
            @csrf
            <label for="file-owner-{{ $type }}" class="sr-only">Reassign to</label>
            <select id="file-owner-{{ $type }}" name="created_by" required
                    class="px-3 py-2 rounded-xl bg-white border border-gray-200 text-dark text-xs font-bold focus:outline-none focus:ring-2 focus:ring-primary">
                <option value="">Reassign to&hellip;</option>
                @foreach ($staff as $member)
                    <option value="{{ $member->id }}" @disabled($member->id === $file->created_by)>{{ $member->name }}{{ $member->isAdmin() ? ' (Admin)' : '' }}{{ $member->id === $file->created_by ? ' — owner' : '' }}</option>
                @endforeach
            </select>
            <button type="submit" class="px-3.5 py-2 bg-primary text-white font-bold text-xs rounded-xl hover:bg-primary-light transition-all">Reassign</button>
        </form>
        </div>
        @error('created_by')
            <p class="text-[11px] text-rose-600">{{ $message }}</p>
        @enderror
    @endif
</div>
