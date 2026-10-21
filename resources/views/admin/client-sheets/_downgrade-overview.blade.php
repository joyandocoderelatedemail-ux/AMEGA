{{-- Step 0 of a Visa Downgrading file: who can apply and how, as the BI sets it out. --}}
<div class="border-b border-gray-100 pb-2">
    <h3 id="step-overview" class="font-heading text-sm font-bold text-dark uppercase tracking-wider">Overview</h3>
</div>

<div class="space-y-6">
    <div>
        <h4 class="text-sm font-bold text-dark">Who can apply?</h4>
        <p class="mt-2 text-sm text-dark/70 leading-relaxed">Foreign Nationals who shall apply for the reversion of their immigration visa to temporary visitor/tourist to continue to stay legally in the Philippines</p>
    </div>

    <div>
        <h4 class="text-sm font-bold text-dark">How to apply?</h4>
        <ol class="mt-2 space-y-3">
            @foreach ([
                'Present the letter request for downgrading and other requirements.',
                'Get the Order of Payment Slip.',
                'Pay the required immigration fees.',
                'Get the Official Receipt.',
                'Submit official receipt with the other requirements for downgrading.',
                'If approved, present passport for implementation.',
                'Claim the passport stamped with the downgraded visa.',
            ] as $instruction)
                <li class="flex items-start gap-3 text-sm text-dark/80 leading-relaxed">
                    <span class="w-6 h-6 rounded-full bg-primary/10 text-primary font-heading font-extrabold text-xs flex items-center justify-center shrink-0">{{ $loop->iteration }}</span>
                    <span class="pt-0.5">{{ $instruction }}</span>
                </li>
            @endforeach
        </ol>
    </div>
</div>
