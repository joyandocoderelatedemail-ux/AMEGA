{{-- Who the visit visa extension is for, and what the client brings: shown before anything else. --}}
<section class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden" aria-label="About the visit visa extension">
    <div class="grid grid-cols-1 md:grid-cols-2 md:divide-x divide-slate-200">
        <div class="p-5">
            <div class="flex items-center gap-2">
                <span class="w-8 h-8 rounded-lg bg-navy-50 text-navy-700 flex items-center justify-center shrink-0">
                    <i data-lucide="user-check" class="w-4 h-4"></i>
                </span>
                <h2 class="text-base font-bold text-slate-900">Who can apply?</h2>
            </div>
            <p class="mt-3 text-sm text-slate-600 leading-relaxed">
                Non-visa required tourists admitted initially for <span class="font-semibold text-slate-900">thirty (30) days</span>
                and requesting an initial extension of <span class="font-semibold text-slate-900">twenty-nine (29) days</span>.
            </p>
        </div>

        <div class="p-5 border-t md:border-t-0 border-slate-200">
            <div class="flex items-center gap-2">
                <span class="w-8 h-8 rounded-lg bg-navy-50 text-navy-700 flex items-center justify-center shrink-0">
                    <i data-lucide="folder-check" class="w-4 h-4"></i>
                </span>
                <h2 class="text-base font-bold text-slate-900">What to bring?</h2>
            </div>
            <ol class="mt-3 space-y-2.5">
                <li class="flex items-start gap-3">
                    <span class="w-6 h-6 rounded-full bg-slate-100 text-xs font-bold text-slate-700 flex items-center justify-center shrink-0">1</span>
                    <span class="min-w-0 text-sm">
                        <span class="font-semibold text-slate-900">Checklist</span>
                        <span class="block text-slate-500">Checklist of Documentary Requirements, ticked off in the application.</span>
                        <a href="{{ route('visa.tourist-visa.blank-checklist') }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1 mt-0.5 text-xs font-semibold text-navy-700 hover:text-navy-900">
                            <i data-lucide="printer" class="w-3.5 h-3.5"></i> Print a blank checklist
                        </a>
                    </span>
                </li>
                <li class="flex items-start gap-3">
                    <span class="w-6 h-6 rounded-full bg-slate-100 text-xs font-bold text-slate-700 flex items-center justify-center shrink-0">2</span>
                    <span class="min-w-0 text-sm">
                        <span class="font-semibold text-slate-900">Application form</span>
                        <span class="block text-slate-500">The CGAF for Extension of Stay, filled in here and printed on the BI layout.</span>
                        <a href="{{ route('visa.tourist-visa.blank-form') }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1 mt-0.5 text-xs font-semibold text-navy-700 hover:text-navy-900">
                            <i data-lucide="printer" class="w-3.5 h-3.5"></i> Print a blank form
                        </a>
                    </span>
                </li>
            </ol>
            @if ($showLinks ?? false)
                <div class="mt-4 flex flex-wrap items-center gap-2">
                    <a href="{{ route('visa.tourist-visa.create') }}" class="inline-flex items-center justify-center gap-2 h-9 px-3 rounded-lg bg-navy-700 text-sm font-semibold text-white hover:bg-navy-800 transition-colors">
                        <i data-lucide="file-plus" class="w-4 h-4"></i> Fill in the application
                    </a>
                    <a href="{{ route('visa.tourist-visa.index') }}" class="inline-flex items-center justify-center h-9 px-3 rounded-lg border border-slate-300 bg-white text-sm font-semibold text-slate-700 hover:border-slate-400 hover:text-navy-700 transition-colors">
                        Filed applications
                    </a>
                </div>
            @endif
        </div>
    </div>
</section>
