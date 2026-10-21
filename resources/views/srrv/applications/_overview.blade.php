{{-- Step 0 of a new SRRV application: who can apply and how, as the Philippine Retirement Authority sets it out (pra.gov.ph/SRRVisa). --}}
<div class="rounded-2xl bg-white border border-gray-200 shadow-card p-6 space-y-6">
    <div class="border-b border-gray-100 pb-2">
        <h2 class="font-heading text-sm font-bold text-dark uppercase tracking-wider">Overview</h2>
    </div>

    <div>
        <h3 class="text-sm font-bold text-dark">Who can apply?</h3>
        <ul class="mt-2 space-y-2 text-sm text-dark/70 leading-relaxed list-disc pl-5">
            <li><span class="font-semibold text-dark">SRRV Classic:</span> an SRRV option for principal retirees, pensioners or non-pensioners: foreign nationals or former Filipino citizens aged 40 and above.</li>
            <li><span class="font-semibold text-dark">SRRV Courtesy (foreign nationals):</span> retired diplomats, officers of international organizations recognized by the Philippine Department of Foreign Affairs, retired military members of countries with recognized bilateral relations with the Philippines, and high achievers in academics, business, arts, culture, music, sports or philanthropy.</li>
            <li><span class="font-semibold text-dark">SRRV Courtesy (former Filipinos):</span> Filipino retirees that are naturalized in other countries and have not re-acquired Filipino citizenship.</li>
            <li><span class="font-semibold text-dark">Dependents:</span> the legally married spouse, and unmarried children under 21 at the time of application (legitimate or legally adopted).</li>
        </ul>
    </div>

    <div>
        <h3 class="text-sm font-bold text-dark">How to apply?</h3>
        <p class="mt-1 text-[11px] text-dark/50">Submit the PRA's basic requirements, in this order:</p>
        <ol class="mt-2 space-y-3">
            @foreach ([
                'Original passport with a valid tourist visa (valid for at least one month during processing).',
                'Accomplished PRA Application Form.',
                'Medical certificate, issued within six months before submission.',
                'Police clearance from the country of origin or residence, issued within six months.',
                'Bureau of Immigration Clearance Certificate (BICC).',
                'NBI Clearance, if the applicant has stayed more than 90 days in the Philippines.',
                'Eight (8) 2x2 photos with white background, taken within the last three months.',
                'PRA processing fee of USD 1,500.',
                'Bank certificate or proof of inward remittance of the visa deposit to a PRA-accredited bank (a private bank needs a Letter of Introduction from the PRA).',
                'PRA annual fee.',
            ] as $instruction)
                <li class="flex items-start gap-3 text-sm text-dark/80 leading-relaxed">
                    <span class="w-6 h-6 rounded-full bg-primary/10 text-primary font-heading font-extrabold text-xs flex items-center justify-center shrink-0">{{ $loop->iteration }}</span>
                    <span class="pt-0.5">{{ $instruction }}</span>
                </li>
            @endforeach
        </ol>
        <p class="mt-3 text-[11px] text-dark/50">Each joining dependent pays USD 300 and submits their own form, medical certificate, clearances, photos and apostilled proof of relationship. Foreign documents must be in English (or translated) and apostilled or authenticated.</p>
    </div>
</div>
