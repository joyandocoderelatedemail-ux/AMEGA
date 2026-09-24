@extends('layouts.srrv')

@section('title', $renewal->reference.' - AMEGA Travel and Tours')

@section('content')

    <!-- Header -->
    <div class="flex flex-wrap items-start justify-between gap-4 mb-8">
        <div>
            <div class="flex items-center gap-2 mb-2">
                <a href="{{ route('srrv.renewals.index') }}"
                   class="text-[11px] font-bold uppercase tracking-wider text-dark/40 hover:text-primary transition-colors">Renewal Ledger</a>
                <i data-lucide="chevron-right" class="w-3 h-3 text-dark/25"></i>
                <span class="text-[11px] font-bold uppercase tracking-wider text-primary">{{ $renewal->reference }}</span>
            </div>
            <h1 class="font-heading text-2xl sm:text-3xl font-extrabold text-dark">{{ $renewal->retiree_name }}</h1>
            <div class="flex flex-wrap items-center gap-2 mt-2">
                <span class="inline-flex px-2.5 py-1 rounded-lg text-[10px] font-bold uppercase tracking-wider {{ $renewal->visa_class === 'courtesy' ? 'bg-amber-100 text-amber-800' : 'bg-primary/10 text-primary' }}">
                    {{ $renewal->visa_class }}
                </span>
                <span class="inline-flex px-2.5 py-1 rounded-lg text-[10px] font-bold uppercase tracking-wider {{ $renewal->status === 'collected' ? 'bg-emerald-100 text-emerald-800' : ($renewal->status === 'cancelled' ? 'bg-rose-100 text-rose-700' : 'bg-amber-100 text-amber-800') }}">
                    {{ str_replace('_', ' ', $renewal->status) }}
                </span>
                <span class="inline-flex px-2.5 py-1 rounded-lg bg-gray-100 text-dark/60 text-[10px] font-bold uppercase tracking-wider">
                    {{ $renewal->years_paid }} year(s)
                </span>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            @if(! $isFinal && $renewal->status !== 'cancelled' && $nextStage !== 'collected')
                <form method="POST" action="{{ route('srrv.renewals.advance', $renewal) }}" class="m-0">
                    @csrf
                    <button type="submit"
                            class="inline-flex items-center gap-2 px-5 py-2.5 bg-primary text-white font-heading font-bold text-xs rounded-xl hover:bg-primary-light transition-all shadow-sm">
                        <i data-lucide="arrow-right-circle" class="w-4 h-4"></i>
                        <span>Advance to {{ str_replace('_', ' ', $nextStage) }}</span>
                    </button>
                </form>
            @endif

            <a href="{{ route('srrv.renewals.edit', $renewal) }}"
               class="inline-flex items-center gap-2 px-4 py-2.5 bg-white border border-gray-200 text-dark/70 font-bold text-xs rounded-xl hover:border-gray-300 transition-all">
                <i data-lucide="pencil-line" class="w-4 h-4"></i>
                <span>Edit</span>
            </a>

            @if($renewal->status !== 'cancelled')
                <form method="POST" action="{{ route('srrv.renewals.cancel', $renewal) }}" class="m-0"
                      onsubmit="return confirm('Cancel this renewal?');">
                    @csrf
                    <button type="submit"
                            class="inline-flex items-center gap-2 px-4 py-2.5 bg-white border border-gray-200 text-rose-600 font-bold text-xs rounded-xl hover:border-rose-300 transition-all">
                        <i data-lucide="ban" class="w-4 h-4"></i>
                        <span>Cancel</span>
                    </button>
                </form>
            @endif

            <form method="POST" action="{{ route('srrv.renewals.destroy', $renewal) }}" class="m-0"
                  onsubmit="return confirm('Delete this renewal? This cannot be undone.');">
                @csrf
                @method('DELETE')
                <button type="submit" title="Delete renewal"
                        class="p-2.5 bg-white border border-gray-200 text-rose-600 rounded-xl hover:border-rose-300 transition-all">
                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                </button>
            </form>
        </div>
    </div>

    <!-- Pipeline -->
    <div class="rounded-2xl bg-white border border-gray-100 shadow-sm p-6 mb-6">
        <h2 class="font-heading text-sm font-extrabold text-dark mb-4">Pipeline</h2>
        <div class="flex flex-wrap items-center gap-2">
            @foreach($stages as $index => $stage)
                @php
                    $done = $index < $currentIndex;
                    $current = $index === $currentIndex;
                @endphp
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-[10px] font-bold uppercase tracking-wider border
                        {{ $current ? 'bg-primary text-white border-primary shadow-sm' : ($done ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-gray-50 text-dark/40 border-gray-200') }}">
                        @if($done)
                            <i data-lucide="check" class="w-3 h-3"></i>
                        @elseif($current)
                            <i data-lucide="circle-dot" class="w-3 h-3"></i>
                        @endif
                        <span>{{ str_replace('_', ' ', $stage) }}</span>
                    </span>
                    @if(! $loop->last)
                        <i data-lucide="chevron-right" class="w-3.5 h-3.5 text-dark/25"></i>
                    @endif
                </div>
            @endforeach
        </div>

        <p class="text-[11px] text-dark/50 mt-3">No appearance, no oath at this step.</p>

        <div class="flex flex-wrap gap-x-6 gap-y-2 mt-4 pt-4 border-t border-gray-100">
            @foreach([
                'Signature Taken' => $renewal->signature_thumbmark_at,
                'Email Sent' => $renewal->email_sent_at,
                'Processed' => $renewal->processed_at,
                'Ready at PRA' => $renewal->ready_at_pra_at,
                'Client Notified' => $renewal->client_notified_at,
                'Collected' => $renewal->collected_at,
            ] as $label => $stamp)
                <div class="text-[11px]">
                    <span class="font-bold uppercase tracking-wider text-dark/40">{{ $label }}</span>
                    <span class="ml-2 font-semibold {{ $stamp ? 'text-dark' : 'text-dark/30' }}">
                        {{ $stamp?->format('M d, Y') ?? '—' }}
                    </span>
                </div>
            @endforeach
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5 mb-6">

        <!-- Details -->
        <div class="lg:col-span-2 rounded-2xl bg-white border border-gray-100 shadow-sm p-6">
            <h2 class="font-heading text-sm font-extrabold text-dark mb-4">Renewal Details</h2>
            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-4">
                <div>
                    <dt class="text-[10px] font-bold uppercase tracking-wider text-dark/40">Retiree</dt>
                    <dd class="text-xs font-semibold text-dark mt-0.5">{{ $renewal->retiree_name }}</dd>
                </div>
                <div>
                    <dt class="text-[10px] font-bold uppercase tracking-wider text-dark/40">Email</dt>
                    <dd class="text-xs text-dark/70 mt-0.5">{{ $renewal->retiree_email ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="text-[10px] font-bold uppercase tracking-wider text-dark/40">SRRV Card</dt>
                    <dd class="text-xs font-semibold text-dark mt-0.5">{{ $renewal->srrv_card_number ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="text-[10px] font-bold uppercase tracking-wider text-dark/40">Linked File</dt>
                    <dd class="text-xs mt-0.5">
                        @if($renewal->application)
                            <a href="{{ route('srrv.applications.show', $renewal->application) }}"
                               class="font-bold text-primary hover:underline">{{ $renewal->application->reference }}</a>
                        @else
                            <span class="text-dark/70">Standalone renewal</span>
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-[10px] font-bold uppercase tracking-wider text-dark/40">ID &amp; Photocopy</dt>
                    <dd class="text-xs font-semibold mt-0.5 {{ $renewal->id_and_photocopy_received ? 'text-emerald-700' : 'text-dark/40' }}">
                        {{ $renewal->id_and_photocopy_received ? 'Received' : 'Outstanding' }}
                    </dd>
                </div>
                <div>
                    <dt class="text-[10px] font-bold uppercase tracking-wider text-dark/40">Online Form</dt>
                    <dd class="text-xs font-semibold mt-0.5 {{ $renewal->form_filled_online ? 'text-emerald-700' : 'text-dark/40' }}">
                        {{ $renewal->form_filled_online ? 'Filled in' : 'Outstanding' }}
                    </dd>
                </div>
            </dl>

            @if($renewal->remarks)
                <div class="mt-5 pt-5 border-t border-gray-100">
                    <dt class="text-[10px] font-bold uppercase tracking-wider text-dark/40 mb-1">Remarks</dt>
                    <dd class="text-xs text-dark/70 leading-relaxed whitespace-pre-line">{{ $renewal->remarks }}</dd>
                </div>
            @endif
        </div>

        <!-- Fee -->
        <div class="rounded-2xl bg-white border border-gray-100 shadow-sm p-6">
            <h2 class="font-heading text-sm font-extrabold text-dark mb-4">Fee</h2>
            <ul class="space-y-2.5">
                <li class="flex items-center justify-between text-xs">
                    <span class="text-dark/55">Rate ({{ $renewal->visa_class }})</span>
                    <span class="font-semibold text-dark">
                        USD {{ number_format(\App\Models\SrrvRenewal::feeForClass($renewal->visa_class), 2) }}
                    </span>
                </li>
                <li class="flex items-center justify-between text-xs">
                    <span class="text-dark/55">Years paid</span>
                    <span class="font-semibold text-dark">{{ $renewal->years_paid }}</span>
                </li>
            </ul>
            <div class="mt-4 pt-4 border-t border-gray-100">
                <div class="flex items-center justify-between">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-dark/40">Total</span>
                    <span class="font-heading text-sm font-extrabold text-dark">
                        {{ $renewal->currency }} {{ number_format((float) $renewal->fee_amount, 2) }}
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- Collection -->
    @if($renewal->status === 'ready_for_collection')
        <div class="rounded-2xl bg-emerald-50 border border-emerald-200 p-6">
            <div class="flex items-start gap-3 mb-4">
                <i data-lucide="package-check" class="w-5 h-5 text-emerald-600 shrink-0 mt-0.5"></i>
                <div>
                    <h2 class="font-heading text-sm font-extrabold text-emerald-900">Ready at the PRA office</h2>
                    <p class="text-[11px] text-emerald-800 mt-0.5">
                        Collected in person, never posted. The counter makes the call.
                    </p>
                </div>
            </div>

            <form method="POST" action="{{ route('srrv.renewals.collect', $renewal) }}"
                  class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                @csrf
                <div class="sm:col-span-2">
                    <label for="collected_by_name" class="block text-[10px] font-bold uppercase tracking-wider text-emerald-800/70 mb-1">Collected By *</label>
                    <input id="collected_by_name" type="text" name="collected_by_name" required
                           placeholder="Name of the person who collected it"
                           class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-emerald-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-emerald-500">
                </div>
                <div class="flex items-end">
                    <button type="submit"
                            class="w-full px-4 py-2.5 bg-emerald-600 text-white font-bold text-xs rounded-xl hover:bg-emerald-700 transition-all">
                        Record Collection
                    </button>
                </div>
            </form>
        </div>
    @elseif($renewal->status === 'collected')
        <div class="rounded-2xl bg-white border border-gray-100 shadow-sm p-6 flex items-center gap-3">
            <i data-lucide="check-circle-2" class="w-5 h-5 text-emerald-600 shrink-0"></i>
            <div>
                <p class="text-xs font-bold text-dark">Collected by {{ $renewal->collected_by_name ?: 'the retiree' }}</p>
                <p class="text-[10px] text-dark/45 mt-0.5">{{ $renewal->collected_at?->format('M d, Y') }}</p>
            </div>
        </div>
    @endif

@endsection
