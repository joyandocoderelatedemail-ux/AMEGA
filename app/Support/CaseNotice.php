<?php

namespace App\Support;

use App\Models\ImmigrationClient;

/**
 * The template an Immigration file offers at its current status: the agent copies it into the Notice box (or
 * a text or Messenger), edits it, and notifies the client by email. Each client-facing status has a template in MessageTemplates (admins may reword them); a
 * released file uses the passport pick-up notice. The internal checking steps have no notice.
 */
class CaseNotice
{
    /** The workflow status => the template for it. For Validation and For Final Checking are internal. */
    public const STATUS_KEYS = [
        'preparing' => 'case_preparing',
        'for_submission' => 'case_for_submission',
        'submitted' => 'case_submitted',
        'awaiting_decision' => 'case_awaiting_decision',
        'for_briefing' => 'case_for_briefing',
        'approved' => 'case_approved',
        'denied' => 'case_denied',
        'withdrawn' => 'case_withdrawn',
        'deferred' => 'case_deferred',
    ];

    /** Released keeps the pick-up notice, so the wording admins already edited stays in use. */
    public const RELEASED_KEY = 'passport_pickup';

    /** Every template key a notice may send: the only ones the send route accepts. */
    public static function keys(): array
    {
        return [...array_values(self::STATUS_KEYS), self::RELEASED_KEY];
    }

    /** The template for a case row (from ImmigrationCases::row), or null when this status has no client notice. */
    public static function keyFor(array $case): ?string
    {
        $workflow = (string) ($case['workflow'] ?? $case['progress'] ?? CaseProgress::DEFAULT);

        if ($case['released'] ?? false) {
            return CaseProgress::closes($workflow) ? self::STATUS_KEYS[$workflow] : self::RELEASED_KEY;
        }

        return self::STATUS_KEYS[$workflow] ?? null;
    }

    /**
     * The template for a case, rendered, at whatever status it is (the agent copies it into the Notice box, edits
     * it and notifies the client). 'state' is 'ready', or 'none' when this status has no template; 'message'
     * says so then. The default subject is used for a message the agent writes themselves.
     *
     * @param  array<string, mixed>  $case  a row from ImmigrationCases::row
     * @return array{state: string, key: ?string, subject: ?string, body: ?string, message: ?string}
     */
    public static function for(array $case, ImmigrationClient $client, ?string $sender): array
    {
        if (($key = self::keyFor($case)) === null) {
            return ['state' => 'none', 'key' => null, 'subject' => self::defaultSubject($case), 'body' => null, 'message' => 'There is no template for this status: write your message below.'];
        }

        $rendered = $key === self::RELEASED_KEY ? self::pickup($client, $sender) : MessageTemplates::render($key, [
            'client_name' => $client->full_name,
            'reference' => $case['reference'],
            'service' => $case['label'],
            'status' => CaseProgress::label($case['workflow'] ?? $case['progress']),
            'filed_on' => $case['filed_on']?->format('F j, Y'),
            'released_on' => $case['released_on']?->format('F j, Y'),
            'requirements' => self::requirements($case),
            'passport_number' => $client->passport_number,
            'agent_name' => $sender,
        ]);

        return ['state' => 'ready', 'key' => $key] + $rendered + ['message' => null];
    }

    /** The subject for a message the agent writes themselves about a case. */
    public static function defaultSubject(array $case): string
    {
        return "Update on your {$case['label']} ({$case['reference']})";
    }

    /**
     * The passport pick-up notice for a client: the wording an admin set, or the default.
     *
     * @return array{subject: string, body: string}
     */
    public static function pickup(ImmigrationClient $client, ?string $sender): array
    {
        return MessageTemplates::render(self::RELEASED_KEY, [
            'client_name' => $client->full_name,
            'valid_until' => $client->visa_expiry_date?->format('F j, Y'),
            'passport_number' => $client->passport_number,
            'agent_name' => $sender,
        ]);
    }

    /** What the client still has to bring, one bullet per line. */
    private static function requirements(array $case): string
    {
        $missing = $case['missing_items'] ?? [];

        return $missing === []
            ? '• Nothing more for now: your requirements are complete.'
            : collect($missing)->map(fn (string $item) => '• '.$item)->implode("\n");
    }
}
