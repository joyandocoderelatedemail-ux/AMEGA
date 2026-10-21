{{-- Step 0 of a Visa Overstaying file: who can apply and how, as the BI sets it out for the Motion for Reconsideration. --}}
<div class="border-b border-gray-100 pb-2">
    <h3 id="step-overview" class="font-heading text-sm font-bold text-dark uppercase tracking-wider">Overview</h3>
</div>

<div class="space-y-6">
    <div>
        <h4 class="text-sm font-bold text-dark">Who can apply?</h4>
        <p class="mt-2 text-sm text-dark/70 leading-relaxed">Foreign nationals who:</p>
        <ul class="mt-2 space-y-1.5 text-sm text-dark/70 leading-relaxed list-disc pl-5">
            <li>Have overstayed beyond the maximum allowable stay (36 months for non-visa-required nationals, 24 months for visa-required nationals), or</li>
            <li>Have overstayed for more than six months, regardless of whether the stay is within the allowable period.</li>
        </ul>
    </div>

    <div>
        <h4 class="text-sm font-bold text-dark">How to apply?</h4>
        <ol class="mt-2 space-y-3">
            @foreach ([
                'Get the Application Form (CGAF) from the Public Information and Assistance Unit (PIAU) or the official BI Website.',
                'Pay the Motion for Reconsideration fee at the cashier, then submit the MR, Official Receipt and other documents to the Central Receiving Unit (CRU) or the frontline officer of another authorized office.',
                'Claim the approved MR from the Visa Extension Section and go to their counters for the Order of Payment Slip.',
                'Pay the fees.',
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
