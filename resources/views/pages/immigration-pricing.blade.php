@extends('layouts.app')

@section('title', 'Immigration Pricing & Process Guide - AMEGA Travel and Tours')

@section('content')
    <div class="pt-20 bg-primary-dark text-white text-center py-16">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <span class="inline-block text-xs font-semibold uppercase tracking-widest text-accent mb-2">Bureau of Immigration</span>
            <h1 class="font-heading text-4xl sm:text-5xl font-bold">Immigration Pricing &amp; Process Guide</h1>
            <p class="text-blue-200/70 text-base max-w-2xl mx-auto mt-3">
                Visa extensions, exit clearance, re-stamping, and CRTV — full rates, conditions, and requirement checklists.
            </p>
        </div>
    </div>

    <section class="py-16 sm:py-20 section-gradient-light relative overflow-hidden">
        <div class="section-dots"></div>
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">

            <div x-data="{ payment: 'cash' }" class="space-y-10">

                <!-- Cash vs Card Toggle -->
                <div class="flex flex-col sm:flex-row items-center justify-between gap-4 bg-white rounded-2xl border border-gray-100 shadow-sm p-4 sm:p-5">
                    <div>
                        <h2 class="font-heading text-lg font-bold text-dark">Payment Method</h2>
                        <p class="text-xs text-dark/50">Card payments carry different Bureau of Immigration rates than cash.</p>
                    </div>
                    <div class="inline-flex p-1 rounded-full bg-gray-100 border border-gray-200">
                        <button type="button" @click="payment = 'cash'"
                                :class="payment === 'cash' ? 'bg-[#005ADA] text-white shadow-md' : 'text-dark/60 hover:text-dark'"
                                class="px-6 py-2 rounded-full font-bold text-xs transition-all flex items-center gap-2">
                            <i data-lucide="banknote" class="w-4 h-4"></i>
                            <span>Cash</span>
                        </button>
                        <button type="button" @click="payment = 'card'"
                                :class="payment === 'card' ? 'bg-[#005ADA] text-white shadow-md' : 'text-dark/60 hover:text-dark'"
                                class="px-6 py-2 rounded-full font-bold text-xs transition-all flex items-center gap-2">
                            <i data-lucide="credit-card" class="w-4 h-4"></i>
                            <span>Card</span>
                        </button>
                    </div>
                </div>

                @forelse ($categories as $category)
                    @php
                        $tiers = $category->pricingTiers;
                        $cashCount = $tiers->where('payment_method', 'cash')->count();
                        $cardCount = $tiers->where('payment_method', 'card')->count();
                        $checklist = $category->requirements->where('type', 'requirement');
                        $notes = $category->requirements->where('type', 'note');
                    @endphp

                    <div class="bg-white rounded-3xl border border-gray-100 shadow-md overflow-hidden"
                         @if ($tiers->isNotEmpty())
                             x-show="(payment === 'cash' && {{ $cashCount }} > 0) || (payment === 'card' && {{ $cardCount }} > 0)"
                         @endif>

                        <!-- Category Header -->
                        <div class="p-6 sm:p-7 border-b border-gray-100 bg-gray-50/60">
                            <div class="flex items-start gap-4">
                                <div class="w-11 h-11 rounded-xl bg-primary/10 text-primary flex items-center justify-center shrink-0">
                                    <i data-lucide="{{ $category->icon ?? 'stamp' }}" class="w-5 h-5"></i>
                                </div>
                                <div class="min-w-0">
                                    <h2 class="font-heading text-xl sm:text-2xl font-bold text-dark tracking-tight">{{ $category->name }}</h2>
                                    @if ($category->description)
                                        <p class="font-body text-sm text-dark/70 leading-relaxed mt-1.5">{{ $category->description }}</p>
                                    @endif
                                    @if ($category->processing_time)
                                        <span class="inline-flex items-center gap-1.5 mt-3 px-3 py-1 rounded-full bg-amber-50 text-amber-800 text-xs font-bold border border-amber-200/70">
                                            <i data-lucide="clock" class="w-3.5 h-3.5"></i>
                                            {{ $category->processing_time }}
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <!-- Requirements & Process Notes -->
                        @if ($checklist->isNotEmpty() || $notes->isNotEmpty())
                            <div class="p-6 sm:p-7 grid grid-cols-1 {{ $checklist->isNotEmpty() && $notes->isNotEmpty() ? 'lg:grid-cols-2' : '' }} gap-6 border-b border-gray-100">
                                @if ($checklist->isNotEmpty())
                                    <div>
                                        <h3 class="font-subheading text-xs font-bold uppercase tracking-wider text-dark/50 mb-3">Requirements Checklist</h3>
                                        <ol class="space-y-2.5">
                                            @foreach ($checklist as $index => $requirement)
                                                <li class="flex items-start gap-3 text-sm text-dark/80 font-medium">
                                                    <span class="w-5 h-5 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center text-[11px] shrink-0 font-bold mt-0.5">
                                                        {{ $index + 1 }}
                                                    </span>
                                                    <span>{{ $requirement->label }}</span>
                                                </li>
                                            @endforeach
                                        </ol>
                                    </div>
                                @endif

                                @if ($notes->isNotEmpty())
                                    <div>
                                        <h3 class="font-subheading text-xs font-bold uppercase tracking-wider text-dark/50 mb-3">Process Notes</h3>
                                        <ul class="space-y-2.5">
                                            @foreach ($notes as $note)
                                                <li class="flex items-start gap-3 text-sm text-dark/70 leading-relaxed">
                                                    <i data-lucide="info" class="w-4 h-4 text-primary shrink-0 mt-0.5"></i>
                                                    <span>{{ $note->label }}</span>
                                                </li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endif
                            </div>
                        @endif

                        <!-- Pricing Tables, grouped by extension tier -->
                        @foreach ($tiers->groupBy(fn ($tier) => $tier->extension_label ?? '') as $groupLabel => $groupTiers)
                            @php
                                $groupCash = $groupTiers->where('payment_method', 'cash')->count();
                                $groupCard = $groupTiers->where('payment_method', 'card')->count();
                            @endphp

                            <div x-data="{ expanded: true }"
                                 x-show="(payment === 'cash' && {{ $groupCash }} > 0) || (payment === 'card' && {{ $groupCard }} > 0)"
                                 class="border-b border-gray-100 last:border-b-0">

                                @if ($groupLabel !== '')
                                    <button type="button" @click="expanded = !expanded"
                                            class="w-full px-6 sm:px-7 py-4 flex items-center justify-between gap-3 hover:bg-gray-50/60 transition-colors text-left">
                                        <span class="font-heading text-base font-bold text-dark">{{ $groupLabel }}</span>
                                        <i data-lucide="chevron-down" class="w-4 h-4 text-dark/40 transition-transform" :class="expanded ? 'rotate-180' : ''"></i>
                                    </button>
                                @endif

                                <div x-show="expanded" class="px-6 sm:px-7 pb-6 {{ $groupLabel === '' ? 'pt-6' : '' }}">
                                    <div class="overflow-x-auto">
                                        <table class="w-full text-left text-sm">
                                            <thead>
                                                <tr class="border-b border-gray-200 text-dark font-extrabold uppercase tracking-wider text-[11px]">
                                                    <th class="pb-3 pr-3">Duration</th>
                                                    <th class="pb-3 px-3">Condition</th>
                                                    <th class="pb-3 px-3">Process</th>
                                                    <th class="pb-3 pl-3 text-right">Price</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-gray-50">
                                                @foreach ($groupTiers as $tier)
                                                    <tr x-show="payment === '{{ $tier->payment_method }}'" class="hover:bg-gray-50/50 transition-colors">
                                                        <td class="py-3.5 pr-3 font-bold text-dark whitespace-nowrap">
                                                            {{ $tier->duration_label ?: '—' }}
                                                        </td>
                                                        <td class="py-3.5 px-3 text-dark/70">
                                                            {{ $tier->condition_notes ?: 'Standard' }}
                                                        </td>
                                                        <td class="py-3.5 px-3 whitespace-nowrap">
                                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase {{ $tier->process_type === 'express' ? 'bg-rose-100 text-rose-800' : 'bg-sky-100 text-sky-800' }}">
                                                                {{ $tier->process_type }}
                                                            </span>
                                                            <span class="block text-[11px] text-dark/50 mt-1">{{ $tier->processing_time }}</span>
                                                        </td>
                                                        <td class="py-3.5 pl-3 text-right font-heading font-black text-dark whitespace-nowrap">
                                                            ₱{{ number_format((float) $tier->price, 0) }}
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        @endforeach

                        @if ($tiers->isEmpty())
                            <div class="px-6 sm:px-7 py-8 text-center text-sm text-dark/50">
                                Rates for this process are confirmed on request — please contact us for a quotation.
                            </div>
                        @endif

                        <!-- Category Footer CTA -->
                        <div class="px-6 sm:px-7 py-4 bg-gray-50/80 border-t border-gray-100 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
                            <span class="text-[11px] text-dark/50">Fees are set by the Bureau of Immigration and may change without notice.</span>
                            <a href="{{ route('contact') }}"
                               onclick="selectImmigrationInquiry('{{ addslashes($category->name) }}')"
                               class="inline-flex items-center gap-2 px-5 py-2.5 bg-[#005ADA] text-white font-bold text-xs rounded-full hover:bg-[#003B95] transition-all duration-300 shadow-md shrink-0">
                                <span>Inquire About {{ Str::limit($category->name, 28) }}</span>
                                <i data-lucide="arrow-right" class="w-4 h-4"></i>
                            </a>
                        </div>
                    </div>
                @empty
                    <div class="py-16 text-center bg-white rounded-3xl border border-gray-100">
                        <i data-lucide="stamp" class="w-10 h-10 text-dark/30 mx-auto mb-2"></i>
                        <h3 class="font-heading text-lg font-bold text-dark">Price list coming soon</h3>
                        <p class="text-sm text-dark/50 mt-1">Please <a href="{{ route('contact') }}" class="text-primary font-bold hover:underline">contact us</a> for current immigration rates.</p>
                    </div>
                @endforelse

                <!-- Global Disclaimer -->
                <div class="rounded-2xl bg-white border border-gray-100 shadow-sm p-5 flex items-start gap-3">
                    <i data-lucide="shield-check" class="w-5 h-5 text-primary shrink-0 mt-0.5"></i>
                    <p class="text-xs text-dark/60 leading-relaxed">
                        AMEGA Travel and Tours is a Bureau of Immigration accredited agency. All fees listed are inclusive of BI charges and our processing service.
                        Rates are subject to change by the Bureau of Immigration without prior notice — contact our office to confirm the current figure for your specific case.
                    </p>
                </div>
            </div>
        </div>
    </section>

    @include('partials.cta')

    <script>
        function selectImmigrationInquiry(categoryName) {
            sessionStorage.setItem('amega_immigration_inquiry', categoryName);
        }

        document.addEventListener('DOMContentLoaded', () => {
            const messageInput = document.getElementById('message');
            const stored = sessionStorage.getItem('amega_immigration_inquiry');

            if (messageInput && stored) {
                messageInput.value = "Hello AMEGA Team,\n\nI would like to request details and a quotation for: " + stored + ".\nPlease provide the instructions and processing requirements.\n\nThank you!";
                sessionStorage.removeItem('amega_immigration_inquiry');
            }
        });
    </script>
@endsection
