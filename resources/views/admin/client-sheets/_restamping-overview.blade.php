{{-- Step 0 of a Re Stamping Amendment file: who can apply and how, as the BI sets it out. --}}
<div class="border-b border-gray-100 pb-2">
    <h3 id="step-overview" class="font-heading text-sm font-bold text-dark uppercase tracking-wider">Overview</h3>
</div>

<div class="space-y-6">
    <div>
        <h4 class="text-sm font-bold text-dark">Who can apply?</h4>
        <p class="mt-2 text-sm text-dark/70 leading-relaxed">Foreign nationals who have new passports. The admission status in the latest arrival stamp in the lost or cancelled passport is transferred to the new passport</p>
    </div>

    <div>
        <h4 class="text-sm font-bold text-dark">How to apply?</h4>
        <ol class="mt-2 space-y-3">
            @foreach ([
                'Fill out application form.',
                'Submit passport and application form.',
                'Claim the passport.',
            ] as $instruction)
                <li class="flex items-start gap-3 text-sm text-dark/80 leading-relaxed">
                    <span class="w-6 h-6 rounded-full bg-primary/10 text-primary font-heading font-extrabold text-xs flex items-center justify-center shrink-0">{{ $loop->iteration }}</span>
                    <span class="pt-0.5">{{ $instruction }}</span>
                </li>
            @endforeach
        </ol>
    </div>
</div>
