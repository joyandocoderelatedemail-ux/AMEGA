{{-- Step 0 of a Dual Citizenship file: who can apply and how, as the BI sets it out. --}}
<div class="border-b border-gray-100 pb-2">
    <h3 id="step-overview" class="font-heading text-sm font-bold text-dark uppercase tracking-wider">Overview</h3>
</div>

<div class="space-y-6">
    <div>
        <h4 class="text-sm font-bold text-dark">Who can apply?</h4>
        <p class="mt-2 text-sm text-dark/70 leading-relaxed">Former Philippine citizens who have been naturalized in another country and wishes to retain or re-acquire their Philippine citizenship</p>
    </div>

    <div>
        <h4 class="text-sm font-bold text-dark">How to apply?</h4>
        <ol class="mt-2 space-y-3">
            @foreach ([
                'Secure the Checklist of required documents from either at the Public Information and Assistance Unit (PIAU) at BI G/F Main Office or from the official BI Website.',
                'Submit the documents for pre-screening to the Central Receiving Unit (CRU) to process this transaction.',
                'Take Oath of Allegiance.',
                'Get the Order of Payment Slip (OPS).',
                'Pay the required fees.',
                'Verify status of application whether approved or not.',
                'If approved, claim the Certificate of Retention/Reacquisition of Philippine Citizenship, Order of Approval and Oath of Allegiance.',
            ] as $instruction)
                <li class="flex items-start gap-3 text-sm text-dark/80 leading-relaxed">
                    <span class="w-6 h-6 rounded-full bg-primary/10 text-primary font-heading font-extrabold text-xs flex items-center justify-center shrink-0">{{ $loop->iteration }}</span>
                    <span class="pt-0.5">{{ $instruction }}</span>
                </li>
            @endforeach
        </ol>
    </div>
</div>
