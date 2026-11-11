{{--
    Tells a staff member their session ran out while a page was still open, instead of
    letting the next click drop them on the sign-in page without a word. The notification
    bells raise `session-ended` when their poll is answered 401 (signed out) or 419.
    "Sign in again" reloads the page, so signing in brings the person back to it.
--}}
<div x-data="{ ended: false }"
     x-on:session-ended.window="ended = true"
     x-show="ended"
     x-cloak
     style="display: none;"
     class="fixed inset-0 z-[200] flex items-center justify-center p-4 bg-slate-900/60"
     role="alertdialog"
     aria-modal="true"
     aria-labelledby="session-ended-title"
     aria-describedby="session-ended-text">
    <div class="w-full max-w-sm rounded-2xl bg-white p-6 text-center shadow-2xl">
        <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-xl bg-amber-50 text-amber-600">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <circle cx="12" cy="12" r="10"></circle>
                <polyline points="12 6 12 12 16 14"></polyline>
            </svg>
        </div>
        <h2 id="session-ended-title" class="mt-4 text-lg font-bold text-slate-900">Your session has ended</h2>
        <p id="session-ended-text" class="mt-1.5 text-sm leading-relaxed text-slate-600">
            You were signed out after a period of inactivity. Sign in again to continue. Anything typed on this page and not yet saved will need to be entered again.
        </p>
        <button type="button"
                x-on:click="window.location.reload()"
                x-effect="ended && $nextTick(() => $el.focus())"
                class="mt-5 inline-flex h-11 w-full items-center justify-center rounded-lg bg-navy-700 px-4 text-sm font-semibold text-white transition-colors hover:bg-navy-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-500 focus-visible:ring-offset-2">
            Sign in again
        </button>
    </div>
</div>
