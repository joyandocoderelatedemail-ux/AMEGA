@php
    /**
     * A ticket's stage as a coloured pill (TicketBooking::STAGES): one hue per meaning,
     * rose = returned, amber = needs approval, navy = with the cashier, emerald = ready, slate = done.
     *
     * @var \App\Models\TicketBooking $ticket
     */
    $stage = $ticket->stageInfo();
    $pillTone = [
        'rose' => ['bg-rose-50 text-rose-700 ring-rose-600/20', 'bg-rose-500'],
        'amber' => ['bg-amber-50 text-amber-800 ring-amber-600/20', 'bg-amber-500'],
        'navy' => ['bg-navy-50 text-navy-700 ring-navy-700/20', 'bg-navy-600'],
        'emerald' => ['bg-emerald-50 text-emerald-700 ring-emerald-600/20', 'bg-emerald-500'],
        'slate' => ['bg-slate-100 text-slate-700 ring-slate-500/20', 'bg-slate-400'],
    ][$stage['tone']];
@endphp
<span class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-semibold whitespace-nowrap ring-1 ring-inset {{ $pillTone[0] }}">
    <span class="w-1.5 h-1.5 rounded-full {{ $pillTone[1] }}" aria-hidden="true"></span>
    {{ $stage['label'] }}
</span>
