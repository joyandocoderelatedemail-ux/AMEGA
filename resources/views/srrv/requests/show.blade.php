@extends('layouts.srrv')

@section('title', $file->reference.' - AMEGA Travel and Tours')

@php
    $next = $file->nextStage();
    $blocker = $file->isFinal() ? null : $file->stageBlocker();
    $stages = $file->stages();
    $at = array_search($file->status, array_keys($stages), true);
    $card = 'rounded-2xl bg-white border border-gray-200 shadow-card p-6';
    $requirements = collect($file->requirements())->filter(fn ($item) => $item[2]);
    $received = $file->checklist ?? [];
@endphp

@section('content')
<div class="space-y-6">
    {{-- Header --}}
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 mb-2">
                <a href="{{ route('srrv.services', $file->type) }}" class="text-[11px] font-bold uppercase tracking-wider text-dark/40 hover:text-primary">{{ $file->typeLabel() }}</a>
                <i data-lucide="chevron-right" class="w-3 h-3 text-dark/25"></i>
                <span class="text-[11px] font-bold uppercase tracking-wider text-primary">{{ $file->reference }}</span>
            </div>
            <h1 class="font-heading text-2xl sm:text-3xl font-extrabold text-dark">{{ $file->retiree_name }}</h1>
            <div class="flex flex-wrap items-center gap-2 mt-2">
                <span class="inline-flex px-2.5 py-1 rounded-lg text-[10px] font-bold uppercase tracking-wider {{ $file->status === 'completed' ? 'bg-emerald-100 text-emerald-800' : ($file->status === 'cancelled' ? 'bg-rose-100 text-rose-700' : 'bg-amber-100 text-amber-800') }}">{{ $file->status_label }}</span>
                @if ($file->isAwaitingApproval())
                    <span class="inline-flex px-2.5 py-1 rounded-lg text-[10px] font-bold uppercase tracking-wider bg-amber-100 text-amber-800">Waiting for approval</span>
                @elseif ($file->isReturned())
                    <span class="inline-flex px-2.5 py-1 rounded-lg text-[10px] font-bold uppercase tracking-wider bg-rose-100 text-rose-700">Returned: {{ $file->review_note }}</span>
                @endif
                @if ($file->srrv_card_number)<span class="text-xs text-dark/50">SRRV {{ $file->srrv_card_number }}</span>@endif
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            @if (! $file->isFinal() && $next)
                <form method="POST" action="{{ route('srrv.requests.advance', $file) }}" class="m-0">
                    @csrf
                    <button type="submit" @disabled($blocker) title="{{ $blocker ?: 'Move the request to the next stage' }}"
                            class="inline-flex items-center gap-2 px-5 py-2.5 bg-primary text-white font-heading font-bold text-xs rounded-xl hover:bg-primary-light shadow-sm disabled:opacity-40 disabled:cursor-not-allowed">
                        <i data-lucide="arrow-right-circle" class="w-4 h-4"></i>
                        <span>Advance to {{ $file->stageLabel($next) }}</span>
                    </button>
                </form>
            @endif
            <a href="{{ route('srrv.requests.edit', $file) }}" class="inline-flex items-center gap-2 px-4 py-2.5 bg-white border border-gray-200 text-dark/70 font-bold text-xs rounded-xl hover:border-gray-300">
                <i data-lucide="pencil-line" class="w-4 h-4"></i><span>Edit</span>
            </a>
            <a href="{{ route('srrv.requests.print', $file) }}" target="_blank" rel="noopener" class="inline-flex items-center gap-2 px-4 py-2.5 bg-white border border-gray-200 text-dark/70 font-bold text-xs rounded-xl hover:border-gray-300">
                <i data-lucide="printer" class="w-4 h-4"></i><span>PRA Checklist</span>
            </a>
            @unless ($file->isFinal())
                <form method="POST" action="{{ route('srrv.requests.cancel', $file) }}" onsubmit="return confirm('Cancel this request?');" class="m-0">
                    @csrf
                    <button type="submit" class="inline-flex items-center gap-2 px-4 py-2.5 bg-white border border-rose-200 text-rose-700 font-bold text-xs rounded-xl hover:bg-rose-50">Cancel request</button>
                </form>
            @endunless
            @if (auth()->user()?->isAdmin())
                <form method="POST" action="{{ route('srrv.requests.destroy', $file) }}" onsubmit="return confirm('Delete this request permanently?');" class="m-0">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="p-2.5 text-rose-500 hover:bg-rose-50 rounded-xl" title="Delete"><i data-lucide="trash-2" class="w-4 h-4"></i></button>
                </form>
            @endif
        </div>
    </div>

    @if ($blocker && ! $file->isFinal())
        <div class="rounded-xl bg-amber-50 border border-amber-200 px-4 py-3 text-xs text-amber-900"><b>Before it moves on:</b> {{ $blocker }}</div>
    @endif

    {{-- Stages --}}
    <div class="{{ $card }}">
        <ol class="grid gap-2" style="grid-template-columns: repeat({{ count($stages) }}, minmax(0, 1fr))">
            @foreach ($stages as $key => $text)
                @php $i = array_search($key, array_keys($stages), true); @endphp
                <li class="space-y-1.5">
                    <span class="block h-2 rounded-full {{ $file->status === 'cancelled' ? 'bg-gray-200' : ($i < $at ? 'bg-primary' : ($i === $at ? 'bg-accent' : 'bg-gray-200')) }}"></span>
                    <span class="block text-[10px] font-bold uppercase tracking-wider {{ $i === $at ? 'text-dark' : 'text-dark/40' }}">{{ $text }}</span>
                </li>
            @endforeach
        </ol>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Requirements --}}
        <div class="lg:col-span-2 {{ $card }} space-y-3">
            <h2 class="font-heading text-sm font-extrabold text-dark">Requirements <span class="text-dark/40 font-semibold">{{ count(array_intersect(array_keys($requirements->all()), $received)) }} of {{ $requirements->count() }}</span></h2>
            <ul class="space-y-1.5">
                @php $filed = $file->documents->groupBy('requirement'); @endphp
                @foreach ($requirements as $key => [$text, $section])
                    @php $has = in_array($key, $received, true); @endphp
                    <li class="text-xs">
                        <span class="flex items-start gap-2.5">
                            <i data-lucide="{{ $has ? 'check-circle-2' : 'circle' }}" class="w-4 h-4 shrink-0 {{ $has ? 'text-emerald-600' : 'text-dark/25' }}"></i>
                            <span class="{{ $has ? 'text-dark' : 'text-dark/60' }}">{{ $text }}</span>
                        </span>
                        @foreach ($filed->get($key, collect()) as $document)
                            @include('srrv.requests._document', ['document' => $document])
                        @endforeach
                    </li>
                @endforeach
                @foreach ($filed->get('other', collect()) as $document)
                    <li class="text-xs"><span class="text-dark/50">Other document</span> @include('srrv.requests._document', ['document' => $document])</li>
                @endforeach
            </ul>
            <a href="{{ route('srrv.requests.edit', $file) }}?step=3" class="inline-block text-xs font-bold text-primary hover:underline">Tick and upload documents</a>
        </div>

        <div class="space-y-6">
            {{-- Details --}}
            <div class="{{ $card }} space-y-2 text-xs">
                <h2 class="font-heading text-sm font-extrabold text-dark mb-1">Details</h2>
                @if ($file->type === 'termination')
                    <p><span class="text-dark/50">Reason:</span> {{ \App\Models\SrrvServiceRequest::REASONS[$file->detail('reason', 'voluntary')] ?? '—' }}</p>
                    <p><span class="text-dark/50">Visa:</span> {{ $file->detail('with_downgrading') ? "Downgrade to a Temporary Visitor's Visa (59 days); ECC needed per member" : 'Cancel (proof of departure needed)' }}</p>
                    <p><span class="text-dark/50">Members:</span> {{ $file->detail('members', 1) }}</p>
                    <p><span class="text-dark/50">Visa deposit:</span> {{ $file->detail('withdraw_deposit') ? 'Withdrawn to the principal\'s bank account' : 'Not withdrawn' }}</p>
                @else
                    <p><span class="text-dark/50">Property:</span> {{ $file->detail('property_address') }}</p>
                    <p><span class="text-dark/50">Sublessor:</span> {{ $file->detail('sublessor_name') }}</p>
                    <p><span class="text-dark/50">Sublease:</span> US$ {{ number_format((float) $file->detail('sublease_amount'), 2) }} for {{ $file->detail('sublease_years') }} years</p>
                    <p><span class="text-dark/50">SRRV issued:</span> {{ $file->detail('srrv_issued_on') ? \Illuminate\Support\Carbon::parse($file->detail('srrv_issued_on'))->format('M j, Y') : '—' }}</p>
                    <p><span class="text-dark/50">Ocular inspection:</span> {{ $file->detail('ocular_inspection_on') ? \Illuminate\Support\Carbon::parse($file->detail('ocular_inspection_on'))->format('M j, Y') : 'Not yet scheduled' }}</p>
                @endif
            </div>

            {{-- Termination fees --}}
            @if ($file->type === 'termination')
                <div class="{{ $card }} space-y-3 text-xs">
                    <h2 class="font-heading text-sm font-extrabold text-dark">Fees</h2>
                    <div class="flex justify-between"><span class="text-dark/60">PRA service fee</span><span>US$ {{ number_format((float) $file->paid_usd, 2) }} / {{ number_format((float) $file->fee_usd, 2) }}</span></div>
                    <div class="flex justify-between"><span class="text-dark/60">Bureau of Immigration fee</span><span>₱{{ number_format((float) $file->paid_php, 2) }} / {{ number_format((float) $file->fee_php, 2) }}</span></div>
                    @if ($file->isFullyPaid())
                        <p class="text-emerald-700 font-bold">Fully paid.</p>
                    @elseif (! $file->isFinal())
                        <form method="POST" action="{{ route('srrv.requests.payment', $file) }}" class="space-y-2 pt-2 border-t border-gray-100">
                            @csrf
                            <div class="grid grid-cols-2 gap-2">
                                <input type="number" step="0.01" min="0" name="paid_usd" placeholder="US$ received" class="w-full px-3 py-2 rounded-lg border border-gray-200 text-xs">
                                <input type="number" step="0.01" min="0" name="paid_php" placeholder="₱ received" class="w-full px-3 py-2 rounded-lg border border-gray-200 text-xs">
                            </div>
                            @error('paid_usd')<p class="text-rose-600">{{ $message }}</p>@enderror
                            @error('paid_php')<p class="text-rose-600">{{ $message }}</p>@enderror
                            <button type="submit" class="w-full px-3 py-2 rounded-lg bg-primary text-white font-bold">Record payment</button>
                        </form>
                        <p class="text-[10px] text-dark/45">Paid at the PRA, or to Land Bank: Philippine Retirement Authority for (name / SRRV no.), US$ account 0054 0033 58 (SWIFT TLBPPHMMXXX) or peso account 0052 105463.</p>
                    @endif
                </div>
            @endif

            @if ($file->remarks)
                <div class="{{ $card }} text-xs"><h2 class="font-heading text-sm font-extrabold text-dark mb-1">Remarks</h2><p class="whitespace-pre-line text-dark/70">{{ $file->remarks }}</p></div>
            @endif
        </div>
    </div>
</div>
@endsection
