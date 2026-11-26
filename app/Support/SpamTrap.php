<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Public-form spam traps.
 *
 * Layer 1 – Honeypot: a hidden "website" field that humans never fill.
 * Layer 2 – Timing: reject submissions faster than 3 s after page load.
 * Layer 3 – JS proof-of-work: a nonce derived from the CSRF token that only
 *           real browsers with JS can compute (headless scrapers that skip JS
 *           cannot produce the correct value).
 * Layer 4 – Content heuristics: catches SEO/link spam that browser-bots paste.
 */
class SpamTrap
{
    /**
     * Returns true when the submission looks like spam.
     * The caller should still return a 200 / success so the bot has no signal.
     */
    public static function caught(Request $request): bool
    {
        // --- Layer 1: honeypot ---
        if (filled($request->input('website'))) {
            self::log('honeypot', $request);

            return true;
        }

        // --- Layer 2: timing gate ---
        if (! $request->filled('form_started')) {
            self::log('no-timestamp', $request);

            return true;
        }

        try {
            $elapsed = now()->timestamp - (int) decrypt($request->input('form_started'));
            if ($elapsed < 3) {
                self::log('too-fast', $request);

                return true;
            }
        } catch (\Throwable) {
            self::log('tampered-timestamp', $request);

            return true;
        }

        // --- Layer 3: JS proof-of-work ---
        // The page computes: SHA-256(csrfToken + '|amega-shield') truncated to
        // 16 hex chars, and puts it in a hidden `_shield` field. A bot that
        // doesn't run the page's JS won't have this value.
        $shield = $request->input('_shield');
        $expected = self::expectedShield($request->session()->token());

        if (! $shield || ! hash_equals($expected, $shield)) {
            self::log('bad-shield', $request);

            return true;
        }

        // --- Layer 4: content heuristics ---
        $message = (string) $request->input('message');
        $name = trim(($request->input('first_name') ?? '').' '.($request->input('last_name') ?? ''));
        if (empty($name)) {
            $name = (string) $request->input('name');
        }

        if (self::looksSpammy($name, (string) $request->input('email'), $message)) {
            self::log('content-heuristic', $request);

            return true;
        }

        return false;
    }

    /**
     * The expected shield value for a given CSRF token.
     * Must match the JS in partials/spam-trap.blade.php.
     */
    public static function expectedShield(string $csrfToken): string
    {
        return substr(hash('sha256', $csrfToken.'|amega-shield'), 0, 16);
    }

    /**
     * Content-based heuristics — catches SEO spam, link injection, and
     * gibberish that browser bots paste into the message field.
     */
    private static function looksSpammy(string $name, string $email, string $message): bool
    {
        $text = $name.' '.$message;

        // More than 2 URLs in the message is almost certainly spam.
        if (preg_match_all('#https?://#i', $text) > 2) {
            return true;
        }

        // BBCode / HTML link injection
        if (preg_match('#\[url[=\]]#i', $text)) {
            return true;
        }

        // Typical SEO-spam / pharma keywords (case-insensitive)
        $spamKeywords = [
            'buy cheap', 'cheap pills', 'viagra', 'cialis', 'casino online',
            'poker online', 'crypto invest', 'earn money fast', 'binary option',
            'SEO service', 'link building', 'web traffic', 'backlink',
            // Captcha-solver adverts (XEvil and the like)
            'xevil', 'captcha', 'antigate',
        ];

        // Real names never carry a run of digits; generated bot names do ("KatyGync5361MC").
        if (preg_match('/\d{3,}/', $name)) {
            return true;
        }

        $lower = mb_strtolower($text);
        foreach ($spamKeywords as $keyword) {
            if (str_contains($lower, mb_strtolower($keyword))) {
                return true;
            }
        }

        // The email domain is a known disposable / spam factory.
        $disposableDomains = [
            'guerrillamail.com', 'mailinator.com', 'tempmail.com', 'throwaway.email',
            'yopmail.com', 'sharklasers.com', 'guerrillamailblock.com', 'grr.la',
            'dispostable.com', 'trashmail.com', 'fakeinbox.com',
        ];

        $emailDomain = strtolower(substr($email, strrpos($email, '@') + 1));
        if (in_array($emailDomain, $disposableDomains, true)) {
            return true;
        }

        return false;
    }

    private static function log(string $reason, Request $request): void
    {
        Log::channel('single')->info('[SpamTrap] blocked', [
            'reason' => $reason,
            'ip' => $request->ip(),
            'email' => $request->input('email'),
            'ua' => $request->userAgent(),
        ]);
    }
}
