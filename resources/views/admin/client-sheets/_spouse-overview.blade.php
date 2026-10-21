{{-- Step 0 of a 13/A Spouse Visa file: who can apply and how, as the BI sets it out. --}}
<div class="border-b border-gray-100 pb-2">
    <h3 id="step-overview" class="font-heading text-sm font-bold text-dark uppercase tracking-wider">Overview</h3>
</div>

<div class="space-y-6">
    <div>
        <h4 class="text-sm font-bold text-dark">Who can apply?</h4>
        <p class="mt-2 text-sm text-dark/70 leading-relaxed">A foreign national on the basis of his valid marriage to a Philippine citizen.</p>
    </div>

    <div>
        <h4 class="text-sm font-bold text-dark">How to apply?</h4>
        <ol class="mt-2 space-y-3">
            @foreach ([
                'Secure the CGAF from either at the Public Information and Assistance Unit (PIAU) at BI G/F Main Office or from the official BI Website.',
                'Submit the documents for pre-screening to the Central Receiving Unit (CRU) or to the frontline officer or staff of other Immigration Offices able to process this transaction.',
                'Get the Order of Payment Slip (OPS).',
                'Pay the required fees.',
                'Submit copy of Official Receipt.',
                'Attend hearing. Please refer to the Official Receipt for the schedule and venue of the hearing and Image and Fingerprint Capturing.',
                'Proceed to Image and Fingerprint Capturing Counter of the Alien Registration Division (ARD) and submit requirements for ACR I-Card application',
                'Check website if visa application is already approved.',
                'If approved, submit passport for visa implementation.',
                'If ACR I-Card is approved, claim ACR I-Card.',
            ] as $instruction)
                <li class="flex items-start gap-3 text-sm text-dark/80 leading-relaxed">
                    <span class="w-6 h-6 rounded-full bg-primary/10 text-primary font-heading font-extrabold text-xs flex items-center justify-center shrink-0">{{ $loop->iteration }}</span>
                    <span class="pt-0.5">{{ $instruction }}</span>
                </li>
            @endforeach
        </ol>
    </div>
</div>
