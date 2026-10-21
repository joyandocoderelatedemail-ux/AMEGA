{{-- Step 0 of a Visa Extension file: who can apply and how, as the BI sets it out for tourist (9A) extensions. --}}
<div class="border-b border-gray-100 pb-2">
    <h3 id="step-overview" class="font-heading text-sm font-bold text-dark uppercase tracking-wider">Overview</h3>
</div>

<div class="space-y-6">
    <div>
        <h4 class="text-sm font-bold text-dark">Who can apply?</h4>
        <ul class="mt-2 space-y-1.5 text-sm text-dark/70 leading-relaxed list-disc pl-5">
            <li>Non-visa required tourists admitted initially for thirty (30) days, for the initial 29-day extension.</li>
            <li>A foreign national whose stay will exceed fifty-nine (59) days, who must secure extensions from the Bureau of Immigration.</li>
            <li>Temporary visitors, regardless of nationality and not otherwise disqualified, who want to extend their tourist visa during the last 30 days of a previously issued Long-Stay Visitor Visa Extension (LSVVE), or upon expiry of a regular visa extension. The total extension may not exceed six months from expiration of the authorized stay. LSVVE is filed at the BI Main Office only.</li>
        </ul>
    </div>

    <div>
        <h4 class="text-sm font-bold text-dark">How to apply?</h4>
        <ol class="mt-2 space-y-3">
            @foreach ([
                'Get the Application Form (CGAF) from the Public Information and Assistance Unit (PIAU) at BI G/F Main Office or from the official BI Website.',
                'Submit the completed form, attachments and passport to the frontline officer.',
                'If there is no derogatory record, a BI Clearance Certificate is issued. Otherwise, proceed to the Verification and Certification Unit.',
                'Get the Order of Payment Slip and pay at the cashier.',
                'Pay and receive the Official Receipt.',
                'Submit the Official Receipt, form, attachments and passport to the receiving/assessment officer.',
                'Claim the passport with the extension stamped.',
            ] as $instruction)
                <li class="flex items-start gap-3 text-sm text-dark/80 leading-relaxed">
                    <span class="w-6 h-6 rounded-full bg-primary/10 text-primary font-heading font-extrabold text-xs flex items-center justify-center shrink-0">{{ $loop->iteration }}</span>
                    <span class="pt-0.5">{{ $instruction }}</span>
                </li>
            @endforeach
        </ol>
    </div>
</div>
