{{-- Step 0 of an ACR I-Card renewal: who can apply and how, as the BI sets it out. --}}
<div class="border-b border-gray-100 pb-2">
    <h3 id="step-overview" class="font-heading text-sm font-bold text-dark uppercase tracking-wider">Overview</h3>
</div>

<div class="space-y-6">
    <div>
        <h4 class="text-sm font-bold text-dark">Who can apply?</h4>
        <p class="mt-2 text-sm text-dark/70 leading-relaxed">All foreign nationals who are existing ACR I-card holders whose basis of renewal is any of the following:</p>
        <ul class="mt-2 space-y-1.5 text-sm text-dark/70 leading-relaxed list-disc pl-5">
            <li>Expiration of the one (1) year validity of the card;</li>
            <li>Re-registration of alien upon reaching the age of 14;</li>
            <li>Change of visa status;</li>
            <li>Replacement of ACR as required under the Alien Registration Act of 1950, as amended.</li>
        </ul>
    </div>

    <div>
        <h4 class="text-sm font-bold text-dark">How to apply?</h4>
        <ol class="mt-2 space-y-3">
            @foreach ([
                'Secure and fill out an application form and attach required documents.',
                'Submit application with complete requirements.',
                'Check the database for any derogatory record.',
                'Get an Order of Payment Slip (OPS).',
                'Pay the required fees.',
                'Submit copy of Official Receipt.',
                'After evaluation application and approved, the ACR I-Card shall be printed.',
                'Present claim stub and identification (ID) card.',
            ] as $instruction)
                <li class="flex items-start gap-3 text-sm text-dark/80 leading-relaxed">
                    <span class="w-6 h-6 rounded-full bg-primary/10 text-primary font-heading font-extrabold text-xs flex items-center justify-center shrink-0">{{ $loop->iteration }}</span>
                    <span class="pt-0.5">{{ $instruction }}</span>
                </li>
            @endforeach
        </ol>
    </div>
</div>
