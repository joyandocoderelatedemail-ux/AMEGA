{{--
    The step header of an SRRV desk form, the same stepper as the ticketing wizard and the immigration
    counter: "Step N - Title", the round step number, and one bar per step (olive now, navy done, grey ahead).
    Expects $steps (number => title), $startStep, $pillIcon and $pillText, inside an x-data with step, visited, titles.
--}}
<div class="bg-white rounded-3xl p-6 sm:p-8 border border-gray-200 shadow-card space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div class="min-w-0">
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-heading font-extrabold uppercase tracking-wider whitespace-nowrap bg-primary/10 text-primary">
                <i data-lucide="{{ $pillIcon }}" class="w-3.5 h-3.5"></i>
                <span>{{ $pillText }}</span>
            </span>
            <h1 class="text-xl sm:text-2xl font-black text-dark tracking-tight mt-1.5">
                <span x-text="'Step ' + step + ' - ' + titles[step]">Step {{ $startStep }} - {{ $steps[$startStep] }}</span>
            </h1>
        </div>

        <div class="flex items-center gap-2 whitespace-nowrap">
            <span class="text-xs font-bold uppercase tracking-wider text-dark/40">Step</span>
            <span class="w-8 h-8 rounded-full bg-primary text-white font-heading font-extrabold text-sm flex items-center justify-center shadow-md shadow-primary/30" x-text="step">{{ $startStep }}</span>
            <span class="text-xs font-bold text-dark/40">of {{ array_key_last($steps) }}</span>
        </div>
    </div>

    <nav aria-label="Form steps" class="grid gap-1.5" style="grid-template-columns: repeat({{ count($steps) }}, minmax(0, 1fr))">
        @foreach ($steps as $number => $stepLabel)
            <button type="button" @click="step = {{ $number }}" class="flex flex-col gap-1.5 cursor-pointer text-left"
                    :aria-current="step === {{ $number }} ? 'step' : null" title="{{ $stepLabel }}">
                <span class="h-2 rounded-full transition-all duration-300"
                      :class="step === {{ $number }} ? 'bg-accent' : (visited.includes({{ $number }}) ? 'bg-primary' : 'bg-gray-200')"></span>
                <span class="text-[9px] font-bold uppercase tracking-wider truncate hidden lg:block"
                      :class="step === {{ $number }} ? 'text-accent font-extrabold' : (visited.includes({{ $number }}) ? 'text-primary' : 'text-dark/40')">{{ $stepLabel }}</span>
            </button>
        @endforeach
    </nav>
</div>
