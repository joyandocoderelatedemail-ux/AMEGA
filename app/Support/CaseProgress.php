<?php

namespace App\Support;

/**
 * Where a counter case stands in the desk's own workflow, in order. Set by hand from the Cases
 * list or the client's folder; a case that was never updated is in preparation.
 */
class CaseProgress
{
    public const DEFAULT = 'preparing';

    public const OPTIONS = [
        'preparing' => 'On-going Preparation',
        'for_validation' => 'For Validation',
        'for_final_checking' => 'For Final Checking',
        'for_submission' => 'For Submission',
        'submitted' => 'Submitted',
        'awaiting_decision' => 'Awaiting Decision/Result',
        'for_briefing' => 'For Briefing',
        'approved' => 'Approved',
        'denied' => 'Denied',
        'withdrawn' => 'Withdrawn by Client',
        'deferred' => 'Deferred by Applicant',
    ];

    /** Statuses that mean the work has gone to the BI: only a case an admin approved can take them. */
    public const NEEDS_APPROVAL = ['submitted', 'awaiting_decision', 'for_briefing', 'approved'];

    /** Statuses that end a case: setting one closes it, and moving it off one reopens it. */
    public const CLOSING = ['denied', 'withdrawn', 'deferred'];

    public static function closes(?string $key): bool
    {
        return in_array($key, self::CLOSING, true);
    }

    public static function label(?string $key): string
    {
        return self::OPTIONS[$key ?: self::DEFAULT] ?? self::OPTIONS[self::DEFAULT];
    }

    /**
     * Badge colours: green for approved, red for denied, amber while the desk has work to do,
     * navy once it is with the BI, grey when the client or applicant stepped back.
     */
    public static function tone(?string $key): string
    {
        return match ($key ?: self::DEFAULT) {
            'approved' => 'bg-emerald-50 text-emerald-800 border-emerald-300',
            'denied' => 'bg-rose-50 text-rose-800 border-rose-300',
            'submitted', 'awaiting_decision' => 'bg-navy-50 text-navy-700 border-navy-200',
            'withdrawn', 'deferred' => 'bg-slate-100 text-slate-700 border-slate-300',
            default => 'bg-amber-50 text-amber-800 border-amber-300',
        };
    }
}
