{{-- Step 0 of a new ID renewal: who can apply and how, as the Philippine Retirement Authority sets it out (pra.gov.ph/id-services). --}}
<div class="rounded-2xl bg-white border border-gray-200 shadow-card p-6 space-y-6">
    <div class="border-b border-gray-100 pb-2">
        <h2 class="font-heading text-sm font-bold text-dark uppercase tracking-wider">Overview</h2>
    </div>

    <div>
        <h3 class="text-sm font-bold text-dark">Who can apply?</h3>
        <p class="mt-2 text-sm text-dark/70 leading-relaxed">The principal SRRV holder, for the renewal of the SRRV ID card. A representative may file it with an authorization letter signed by the principal, a notarized guarantee letter and a government-issued ID.</p>
    </div>

    <div>
        <h3 class="text-sm font-bold text-dark">How to apply?</h3>
        <ol class="mt-2 space-y-3">
            @foreach ([
                'Get a queue number at Window 2 of the PRA Servicing Lounge.',
                'Fill out the Retiree Request Form (RRF) and wait for your number at Counters 2 to 4.',
                'Submit the documents for assessment.',
                'The Financial Management Division verifies the visa deposit and payment records and issues an Order of Payment Slip.',
                'Pay at the Cashier and get the Official Receipt.',
                'Submit the complete documents at Counters 2 to 4 and receive the new ID.',
            ] as $instruction)
                <li class="flex items-start gap-3 text-sm text-dark/80 leading-relaxed">
                    <span class="w-6 h-6 rounded-full bg-primary/10 text-primary font-heading font-extrabold text-xs flex items-center justify-center shrink-0">{{ $loop->iteration }}</span>
                    <span class="pt-0.5">{{ $instruction }}</span>
                </li>
            @endforeach
        </ol>
        <p class="mt-3 text-[11px] text-dark/50">Bring the RRF signed by or for the principal, the official receipt, the latest PRA ID card, a photocopy of the latest passport and a recent 2x2 photo (taken within six months). By courier or email, send the same to the Servicing Division; the Order of Payment Slip is emailed, payment goes through Land Bank, and the PRA says when the ID is ready.</p>
    </div>
</div>
