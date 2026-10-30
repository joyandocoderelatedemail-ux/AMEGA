{{--
    A desk dashboard's forms saved as pending (DeskDraftController), each with Continue and Discard.
    $desk: immigration | visa | srrv. Staff see their own; an admin sees everyone's.
--}}
@php
    $pendingForms = \App\Models\DeskDraft::listFor($desk);
    $pendingRoutes = ['immigration' => 'admin.immigration.pending', 'visa' => 'visa.pending', 'srrv' => 'srrv.pending'][$desk];
@endphp
@if ($pendingForms->isNotEmpty())
    <div id="pending-forms" class="bg-white rounded-xl border border-amber-200 shadow-sm overflow-hidden scroll-mt-24">
        <div class="px-5 py-4 border-b border-amber-100 flex items-center justify-between gap-3">
            <div>
                <h2 class="text-sm font-bold text-dark flex items-center gap-2">
                    <i data-lucide="clock" class="w-4 h-4 text-amber-600"></i>
                    Pending &mdash; continue later
                </h2>
                <p class="text-[11px] text-dark/50 mt-0.5">Forms saved part-way. Continue one to pick up with everything filled in; it becomes a file only when you save it.</p>
            </div>
            <span class="shrink-0 text-[10px] font-bold uppercase tracking-wider px-2.5 py-1 rounded-full bg-amber-100 text-amber-800">{{ $pendingForms->count() }} pending</span>
        </div>
        <ul class="divide-y divide-gray-100">
            @foreach ($pendingForms as $pending)
                <li class="px-5 py-3.5 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-xs font-bold text-dark truncate">{{ $pending->client_name ?: 'No name yet' }}</p>
                        <p class="text-[11px] text-dark/50 truncate">
                            {{ $pending->title }}
                            <span class="text-dark/30">&middot;</span> saved {{ $pending->updated_at->diffForHumans() }}
                            @if (auth()->user()?->isAdmin() && $pending->creator)
                                <span class="text-dark/30">&middot;</span> by {{ $pending->creator->name }}
                            @endif
                        </p>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <a href="{{ route($pendingRoutes.'.continue', $pending) }}"
                           class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-navy-700 text-white font-bold text-xs hover:bg-navy-800 transition-colors">
                            <i data-lucide="play" class="w-3.5 h-3.5"></i>
                            Continue
                        </a>
                        <form method="POST" action="{{ route($pendingRoutes.'.destroy', $pending) }}" class="m-0"
                              onsubmit="return confirm('Discard this pending form? What was typed will be lost.');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" title="Discard pending form"
                                    class="p-2 rounded-lg border border-gray-200 text-rose-600 hover:border-rose-300 transition-colors">
                                <i data-lucide="trash-2" class="w-4 h-4"></i>
                            </button>
                        </form>
                    </div>
                </li>
            @endforeach
        </ul>
    </div>
@endif
