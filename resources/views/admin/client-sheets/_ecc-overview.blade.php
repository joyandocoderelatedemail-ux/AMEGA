{{-- Step 0 of an ECC file: who can apply and how, as the BI sets it out. --}}
<div class="border-b border-gray-100 pb-2">
    <h3 id="step-overview" class="font-heading text-sm font-bold text-dark uppercase tracking-wider">Overview</h3>
</div>

<div class="space-y-6">
    <div>
        <h4 class="text-sm font-bold text-dark">Who can apply?</h4>
        <p class="mt-2 text-sm text-dark/70 leading-relaxed">An individual certifying that he/she is not in any of derogatory database, list or record of the Bureau</p>
    </div>

    <div>
        <h4 class="text-sm font-bold text-dark">How to apply?</h4>
        <ol class="mt-2 space-y-3">
            @foreach ([
                'Secure and fill out application form.',
                'Submit duly accomplished form and attach the other supporting documents.',
                'Wait for the issuance of Order of Payment Slip (OPS).',
                'Pay the corresponding fees.',
                'Submit the accomplished application form with the required attachments and attach original Official Receipts.',
                'Present claim stub on the appointed date and time of return for releasing of Original Certification and Receipts.',
                'Acknowledge receipt by signing the duplicate copy of the Certification before releasing the original Certification and Official Receipt.',
            ] as $instruction)
                <li class="flex items-start gap-3 text-sm text-dark/80 leading-relaxed">
                    <span class="w-6 h-6 rounded-full bg-primary/10 text-primary font-heading font-extrabold text-xs flex items-center justify-center shrink-0">{{ $loop->iteration }}</span>
                    <span class="pt-0.5">{{ $instruction }}</span>
                </li>
            @endforeach
        </ol>
    </div>
</div>
