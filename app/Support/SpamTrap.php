<?php

namespace App\Support;

use Illuminate\Http\Request;

/** Public-form spam traps; the fields come from resources/views/partials/spam-trap.blade.php. */
class SpamTrap
{
    /** The hidden "website" field was filled, or the form came back under 3 seconds after the page loaded. */
    public static function caught(Request $request): bool
    {
        if (filled($request->input('website'))) {
            return true;
        }

        // shortcut: a post without form_started is let through (the page always sends it), require it if spam keeps coming.
        if (! $request->filled('form_started')) {
            return false;
        }

        try {
            return now()->timestamp - (int) decrypt($request->input('form_started')) < 3;
        } catch (\Throwable) {
            return true; // tampered value
        }
    }
}
