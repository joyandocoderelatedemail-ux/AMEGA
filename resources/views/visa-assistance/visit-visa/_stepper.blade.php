{{--
    The one Non-Immigrant step list, Step 0 to Step 6 (VisaApplication::VISIT_VISA_STEPS), shared by the new-file wizard
    and the file page so the process is only ever drawn once. The current step is olive, the steps done are navy, the
    steps after it are grey with a lock (each opens once the one before it is done).
    Expects $current (the step number) and optionally $alpine (a JavaScript expression for the current step, when it
    changes in the page, as in the wizard), $done (every step finished: a released file), $pill (the small label above
    the heading) with $pillAlpine (a JavaScript expression for it).
--}}
@php
    // $processSteps and $processLabel switch it to another service's process (E-Visa); a wizard has its own $steps.
    $steps = $processSteps ?? \App\Models\VisaApplication::VISIT_VISA_STEPS;
    $last = array_key_last($steps);
    $alpine = $alpine ?? null;
    $done = $done ?? false;
    $state = fn (int $n) => $done || $n < $current ? 'done' : ($n === $current ? 'current' : 'locked');
@endphp
<div class="space-y-6" data-visit-visa-steps>
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div class="min-w-0">
            @if (! empty($pill))
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-heading font-extrabold uppercase tracking-wider whitespace-nowrap bg-primary/10 text-primary">
                    <i data-lucide="globe" class="w-3.5 h-3.5"></i>
                    <span @if (! empty($pillAlpine)) x-text="{{ $pillAlpine }}" @endif>{{ $pill }}</span>
                </span>
            @endif
            <h1 class="text-xl sm:text-2xl font-black text-dark tracking-tight mt-1.5">
                @if ($alpine)
                    <span x-text="'Step ' + ({{ $alpine }}) + ' - ' + {{ Js::from($steps) }}[{{ $alpine }}]">Step {{ $current }} - {{ $steps[$current] }}</span>
                @else
                    {{ $done ? 'Released' : 'Step '.$current.' - '.$steps[$current] }}
                @endif
            </h1>
        </div>
        <div class="flex items-center gap-2 whitespace-nowrap">
            <span class="text-xs font-bold uppercase tracking-wider text-dark/40">Step</span>
            <span class="w-8 h-8 rounded-full bg-primary text-white font-heading font-extrabold text-sm flex items-center justify-center shadow-md shadow-primary/30"
                  @if ($alpine) x-text="{{ $alpine }}" @endif>{{ $done ? $last : $current }}</span>
            <span class="text-xs font-bold text-dark/40">of {{ $last }}</span>
        </div>
    </div>

    <ol aria-label="{{ $processLabel ?? 'Non-Immigrant' }} process" class="grid gap-1.5" style="grid-template-columns: repeat({{ count($steps) }}, minmax(0, 1fr))">
        @foreach ($steps as $n => $label)
            <li class="flex flex-col gap-1.5" title="Step {{ $n }} - {{ $label }}"
                @if ($alpine) :aria-current="({{ $alpine }}) === {{ $n }} ? 'step' : null" @elseif ($state($n) === 'current') aria-current="step" @endif>
                <span class="h-2 rounded-full transition-all duration-300 {{ $alpine ? '' : ['done' => 'bg-primary', 'current' => 'bg-accent', 'locked' => 'bg-gray-200'][$state($n)] }}"
                      @if ($alpine) :class="({{ $alpine }}) === {{ $n }} ? 'bg-accent' : (({{ $alpine }}) > {{ $n }} ? 'bg-primary' : 'bg-gray-200')" @endif></span>
                <span class="text-[9px] font-bold uppercase tracking-wider truncate hidden lg:flex items-center gap-1 {{ $alpine ? '' : ['done' => 'text-primary', 'current' => 'text-accent font-extrabold', 'locked' => 'text-dark/40'][$state($n)] }}"
                      @if ($alpine) :class="({{ $alpine }}) === {{ $n }} ? 'text-accent font-extrabold' : (({{ $alpine }}) > {{ $n }} ? 'text-primary' : 'text-dark/40')" @endif>
                    @if ($alpine)
                        <i x-show="({{ $alpine }}) < {{ $n }}" data-lucide="lock" class="w-2.5 h-2.5 shrink-0" aria-hidden="true"></i>
                    @elseif ($state($n) === 'locked')
                        <i data-lucide="lock" class="w-2.5 h-2.5 shrink-0" aria-hidden="true"></i>
                    @endif
                    <span class="truncate">{{ $n }} &middot; {{ $label }}</span>
                </span>
            </li>
        @endforeach
    </ol>
</div>
