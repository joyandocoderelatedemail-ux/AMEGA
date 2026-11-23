{{--
    A desk dashboard's forms saved as pending (DeskDraftController), each with Continue and Discard.
    $desk: immigration | visa | srrv. Staff see their own; an admin sees everyone's.
--}}
@php
    $pendingForms = \App\Models\DeskDraft::listFor($desk);
    $pendingRoutes = ['immigration' => 'admin.immigration.pending', 'visa' => 'visa.pending', 'srrv' => 'srrv.pending'][$desk];
@endphp
@if ($pendingForms->isNotEmpty())
    <div id="pending-forms" class="bg-white rounded-xl border border-slate-200 shadow-sm ring-1 ring-amber-200 overflow-hidden scroll-mt-24">
        <div class="px-5 py-4 border-b border-amber-100 flex items-center justify-between gap-3">
            <div>
                <h2 class="text-base font-bold text-slate-900 flex items-center gap-2">
                    <i data-lucide="clock" class="w-4 h-4 text-amber-600" aria-hidden="true"></i>
                    Pending &mdash; continue later
                </h2>
                <p class="text-sm text-slate-500 mt-0.5">Forms saved part-way. Continue one to pick up with everything filled in; it becomes a file only when you save it.</p>
            </div>
            <span class="shrink-0 inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold whitespace-nowrap ring-1 ring-inset bg-amber-50 text-amber-800 ring-amber-600/20">{{ $pendingForms->count() }} pending</span>
        </div>
        <ul class="divide-y divide-slate-100">
            @foreach ($pendingForms as $pending)
                <li class="px-5 py-3.5 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-slate-900 truncate">{{ $pending->client_name ?: 'No name yet' }}</p>
                        <p class="text-xs text-slate-500 truncate">
                            {{ $pending->title }}
                            <span class="text-slate-300">&middot;</span> saved {{ $pending->updated_at->diffForHumans() }}
                            @if (auth()->user()?->isAdmin() && $pending->creator)
                                <span class="text-slate-300">&middot;</span> by {{ $pending->creator->name }}
                            @endif
                        </p>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <a href="{{ route($pendingRoutes.'.continue', $pending) }}"
                           class="inline-flex items-center gap-1.5 h-8 px-3 rounded-lg bg-navy-700 text-white font-semibold text-xs hover:bg-navy-800 transition-colors">
                            <i data-lucide="play" class="w-3.5 h-3.5" aria-hidden="true"></i>
                            Continue
                        </a>
                        <form method="POST" action="{{ route($pendingRoutes.'.destroy', $pending) }}" class="m-0"
                              onsubmit="return confirm('Discard this pending form? What was typed will be lost.');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" title="Discard pending form" aria-label="Discard pending form"
                                    class="inline-flex items-center justify-center w-8 h-8 rounded-lg border border-slate-300 bg-white text-rose-600 hover:border-rose-300 transition-colors">
                                <i data-lucide="trash-2" class="w-4 h-4" aria-hidden="true"></i>
                            </button>
                        </form>
                    </div>
                </li>
            @endforeach
        </ul>
    </div>
@endif
