{{-- Back / Continue under each step, as in the ticketing wizard. The last step carries the save button itself. --}}
<div x-show="step < last" class="flex items-center justify-between gap-3 pt-2">
    <button type="button" @click="step--" x-show="step > first" style="display: none"
            class="inline-flex items-center gap-2 px-5 py-2.5 rounded-2xl bg-gray-100 hover:bg-gray-200 text-dark font-heading font-bold text-xs transition-colors cursor-pointer">
        <i data-lucide="arrow-left" class="w-4 h-4"></i>
        <span>Back</span>
    </button>
    <span x-show="step === first"></span>
    {{-- A step with its own Advance button (gateStep) does not get a second one here; nothing past a locked step opens.
         Only a form that sets locked to a step number locks one: the renewal and request forms have no locked, and a
         missing key read as "locked" on first render, greying out their Continue. --}}
    <button type="button" @click="if (! (Number.isInteger($data.locked) && step + 1 > $data.locked)) step++" x-show="step !== $data.gateStep"
            :disabled="Number.isInteger($data.locked) && step + 1 > $data.locked"
            class="inline-flex items-center gap-2 px-6 py-3 rounded-2xl bg-primary text-white font-heading font-bold text-xs hover:bg-navy transition-colors shadow-md cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed">
        <span x-text="'Continue to ' + titles[step + 1]"></span>
        <i data-lucide="arrow-right" class="w-4 h-4"></i>
    </button>
</div>
<div x-show="step === last" style="display: none" class="flex items-center justify-start pt-2">
    <button type="button" @click="step--"
            class="inline-flex items-center gap-2 px-5 py-2.5 rounded-2xl bg-gray-100 hover:bg-gray-200 text-dark font-heading font-bold text-xs transition-colors cursor-pointer">
        <i data-lucide="arrow-left" class="w-4 h-4"></i>
        <span>Back</span>
    </button>
</div>
