<?php

namespace App\Support;

use App\Models\MessageTemplate;
use Illuminate\Support\HtmlString;

/**
 * The messages sent to clients, with placeholders filled from the booking or folder. Each has a
 * default here; an admin may reword it (Message Templates), and Reset brings the default back.
 */
class MessageTemplates
{
    /**
     * The placeholders every case notice shares (name => [what it is, a sample value for the preview]).
     *
     * @var array<string, array{string, string}>
     */
    public const CASE_PLACEHOLDERS = [
        'client_name' => ['The client\'s name', 'John Smith'],
        'reference' => ['The case reference', 'ECC-2026-0014'],
        'service' => ['The service the case is for', 'Emigration Clearance Certificate (ECC)'],
        'status' => ['Where the case stands now', 'On-going Preparation'],
        'filed_on' => ['The date it was filed with the Bureau of Immigration', 'October 26, 2026'],
        'released_on' => ['The date it was released', 'November 3, 2026'],
        'requirements' => ['The documents still missing, one per line', "• Passport copy\n• 2x2 photo"],
        'passport_number' => ['The client\'s passport number', 'P1234567A'],
        'agent_name' => ['The staff member who sends it', 'Shey'],
    ];

    /**
     * key => name, what it is for, how it goes out, whether it has a subject, its placeholders
     * (name => [what it is, a sample value for the preview]), and the default wording.
     *
     * @var array<string, array<string, mixed>>
     */
    public const TEMPLATES = [
        'flight_reminder' => [
            'name' => 'Flight Reminder',
            'description' => 'Emailed to the client from the ticket page or the dashboard\'s Departing Soon list.',
            'channel' => 'Email',
            'placeholders' => [
                'client_name' => ['The booker\'s name', 'Maria Santos'],
                'reference' => ['The ticket reference', 'TKT-DOM-20261026-CBAD'],
                'flights' => ['Every flight on the booking (date, departure, from, to, flight number); one block per flight on a round trip or multi-city trip', "✈️ Flight Date: Monday, October 26, 2026\n🛫 Departure: 11:10 AM\n📍 From: Manila (MNL)\n📍 To: Cebu (CEB)\n✈️ Flight Number: 5J 560"],
                'flight_date' => ['The first flight\'s date', 'Monday, October 26, 2026'],
                'departure' => ['The first flight\'s departure time', '11:10 AM'],
                'from' => ['Where the first flight leaves from', 'Manila (MNL)'],
                'to' => ['Where the first flight goes', 'Cebu (CEB)'],
                'flight_number' => ['The first flight\'s number', '5J 560'],
                'travel_type' => ['domestic or international', 'domestic'],
                'when' => ['How soon: today, tomorrow, in 3 days', 'in 2 days'],
                'agent_name' => ['The staff member who sends it', 'Shey'],
            ],
            'subject' => 'Flight reminder from Amega: your flight is {when}',
            'body' => <<<'TEXT'
                Mabuhay! 😊
                Good day, Sir/Ma’am!

                This is a friendly reminder regarding your upcoming flight. Please see the important details below:

                {flights}

                Important Reminders:
                • Please arrive at the airport at least 3 hours before your {travel_type} flight.
                • Kindly bring a valid government-issued ID/passport and your travel documents.
                • Please check your baggage allowance before proceeding to the airport.
                • Make sure all personal belongings and important documents are with you.
                • We also recommend checking your flight status before leaving for the airport, as schedules may change.

                Should you have any questions or concerns regarding your flight, please feel free to message us.

                Thank you so much for choosing Amega Travel and Tours Services! 💙
                Have a safe and wonderful journey!

                Best Regards,
                {agent_name}
                TEXT,
        ],
        'passport_pickup' => [
            'name' => 'Passport Pick-up Notice',
            'description' => 'From an Immigration client folder: copied to send by text or Messenger, and emailed when the client has an address.',
            'channel' => 'Text blast & email',
            'placeholders' => [
                'client_name' => ['The client\'s name', 'John Smith'],
                'valid_until' => ['The visa\'s valid-until date on the folder', 'December 20, 2026'],
                'passport_number' => ['The client\'s passport number', 'P1234567A'],
                'agent_name' => ['The staff member who sends it', 'Shey'],
            ],
            'subject' => 'Your passport and visa are ready for collection',
            'body' => <<<'TEXT'
                Mabuhay!
                Your passport and visa are now ready for collection and are valid until {valid_until}.

                📍 New Office Address
                Unit 1 & 2, Astrofield Building
                Mitchell Avenue, Balibago, Angeles City
                Nearest landmark: Dennis Locksmith or NBH Hardware at the back of Red Street

                ⚠️ Important Reminder
                • Passports cannot be stored once released.
                • We are not liable for any unclaimed passports beyond the designated period.
                • Please claim promptly to avoid delays, missed flights, or travel issues. Amega Travel and Tours shall not be liable for any damages, loss, or inconvenience (e.g. missed flight) by reason of failure to claim your passport after being notified.

                📌 Immigration Update – Angeles Field Office
                Tourist Visa Extension (Regular Process) is now only allowed if your current visa is valid for at least 15 working days. Otherwise, applications will be processed under Express Service.

                🧾 For Third-Party Collection
                • Authorization Letter and valid ID of representative
                • Original receipt from Amega

                🕘 Office Hours
                • Monday–Friday: 9:00 AM – 5:00 PM (Lunch Break: 12:00 – 1:00 PM)
                • Saturday: 9:00 AM – 3:00 PM
                🚫 Closed on Sundays and holidays

                ❗ If you have already claimed your passport, kindly disregard this notice.

                ‼️‼️ Great news! We are now offering pick-up/delivery service of passports for visa extension transactions for a fee. Extend your visa from the comfort of your home.

                Amega Travel and Tours Services
                TEXT,
        ],
        'case_preparing' => [
            'name' => 'Case Notice: On-going Preparation',
            'description' => 'From an Immigration file at On-going Preparation: lists the documents still needed. Copied for a text or Messenger, and emailed when the client has an address.',
            'channel' => 'Text blast & email',
            'placeholders' => self::CASE_PLACEHOLDERS,
            'subject' => 'Your {service} is being prepared: documents we still need',
            'body' => <<<'TEXT'
                Good day, {client_name}!

                Thank you for trusting Amega Travel and Tours Services with your {service} (Ref. {reference}). We are now preparing your application.

                To move forward, we still need the following from you:
                {requirements}

                Kindly send or bring these to our office as soon as you can so we can file your application without delay. If anything is unclear, please feel free to message us anytime.

                Salamat po!

                Regards,
                {agent_name}
                Amega Travel and Tours Services
                sales@amegatravelandtour.com
                TEXT,
        ],
        'case_for_submission' => [
            'name' => 'Case Notice: For Submission',
            'description' => 'From an Immigration file at For Submission: the papers are ready to be filed with the Bureau of Immigration. Copied for a text or Messenger, and emailed when the client has an address.',
            'channel' => 'Text blast & email',
            'placeholders' => self::CASE_PLACEHOLDERS,
            'subject' => 'Your {service} is ready to be filed',
            'body' => <<<'TEXT'
                Good day, {client_name}!

                Good news! Your documents for your {service} (Ref. {reference}) are complete and checked. We will be filing your application with the Bureau of Immigration very soon.

                You do not need to do anything for now. We will message you again once it has been submitted.

                Salamat po for your patience!

                Regards,
                {agent_name}
                Amega Travel and Tours Services
                sales@amegatravelandtour.com
                TEXT,
        ],
        'case_submitted' => [
            'name' => 'Case Notice: Submitted',
            'description' => 'From an Immigration file at Submitted: the application has been filed with the Bureau of Immigration. Copied for a text or Messenger, and emailed when the client has an address.',
            'channel' => 'Text blast & email',
            'placeholders' => self::CASE_PLACEHOLDERS,
            'subject' => 'Your {service} has been submitted to the Bureau of Immigration',
            'body' => <<<'TEXT'
                Good day, {client_name}!

                We are happy to let you know that your {service} (Ref. {reference}) was submitted to the Bureau of Immigration on {filed_on}.

                Processing now rests with the Bureau, and the waiting time can vary. We will keep track of it for you and message you as soon as there is an update.

                Please keep your receipt and reference number in a safe place. If you have any questions, you are always welcome to message us.

                Salamat po!

                Regards,
                {agent_name}
                Amega Travel and Tours Services
                sales@amegatravelandtour.com
                TEXT,
        ],
        'case_awaiting_decision' => [
            'name' => 'Case Notice: Awaiting Decision/Result',
            'description' => 'From an Immigration file at Awaiting Decision/Result: the Bureau of Immigration is still deciding. Copied for a text or Messenger, and emailed when the client has an address.',
            'channel' => 'Text blast & email',
            'placeholders' => self::CASE_PLACEHOLDERS,
            'subject' => 'Update on your {service}: awaiting the Bureau of Immigration decision',
            'body' => <<<'TEXT'
                Good day, {client_name}!

                This is a quick update on your {service} (Ref. {reference}). Your application, filed on {filed_on}, is now with the Bureau of Immigration and we are awaiting their decision.

                There is nothing you need to do at this time. We follow up regularly and will message you right away once the result is out.

                Thank you for your patience and trust. Salamat po!

                Regards,
                {agent_name}
                Amega Travel and Tours Services
                sales@amegatravelandtour.com
                TEXT,
        ],
        'case_for_briefing' => [
            'name' => 'Case Notice: For Briefing',
            'description' => 'From an Immigration file at For Briefing: the client is to be briefed before the Bureau of Immigration decides. Copied for a text or Messenger, and emailed when the client has an address.',
            'channel' => 'Text blast & email',
            'placeholders' => self::CASE_PLACEHOLDERS,
            'subject' => 'Your {service}: please get ready for your briefing',
            'body' => <<<'TEXT'
                Good day, {client_name}!

                Your {service} (Ref. {reference}) has moved to the briefing stage. Our team will go over what to expect and what to bring with you before you appear at the Bureau of Immigration.

                Please:
                • Keep your passport (No. {passport_number}) and a valid ID ready.
                • Reply or message us so we can confirm the schedule with you.
                • Arrive a little early on the day itself.

                If you have any concerns, please let us know and we will gladly help.

                Salamat po!

                Regards,
                {agent_name}
                Amega Travel and Tours Services
                sales@amegatravelandtour.com
                TEXT,
        ],
        'case_approved' => [
            'name' => 'Case Notice: Approved',
            'description' => 'From an Immigration file at Approved: the Bureau of Immigration approved the application, not yet released. Copied for a text or Messenger, and emailed when the client has an address.',
            'channel' => 'Text blast & email',
            'placeholders' => self::CASE_PLACEHOLDERS,
            'subject' => 'Good news: your {service} has been approved',
            'body' => <<<'TEXT'
                Good day, {client_name}!

                We are pleased to let you know that your {service} (Ref. {reference}) has been approved by the Bureau of Immigration. Congratulations!

                We are now waiting for your documents to be released. We will message you again as soon as your passport is ready for collection, so please keep your phone close.

                Thank you for trusting Amega Travel and Tours Services. Salamat po!

                Regards,
                {agent_name}
                Amega Travel and Tours Services
                sales@amegatravelandtour.com
                TEXT,
        ],
        'case_denied' => [
            'name' => 'Case Notice: Denied',
            'description' => 'From an Immigration file at Denied: the Bureau of Immigration did not grant the application. Copied for a text or Messenger, and emailed when the client has an address.',
            'channel' => 'Text blast & email',
            'placeholders' => self::CASE_PLACEHOLDERS,
            'subject' => 'Update on your {service}',
            'body' => <<<'TEXT'
                Good day, {client_name}.

                We regret to inform you that the Bureau of Immigration did not grant your {service} (Ref. {reference}).

                We understand this is not the news you were hoping for. Please visit or message our office so we can go over the decision together and discuss what options may be available to you.

                We are here to help you every step of the way.

                Regards,
                {agent_name}
                Amega Travel and Tours Services
                sales@amegatravelandtour.com
                TEXT,
        ],
        'case_withdrawn' => [
            'name' => 'Case Notice: Withdrawn by Client',
            'description' => 'From an Immigration file at Withdrawn by Client: the client chose to withdraw the application. Copied for a text or Messenger, and emailed when the client has an address.',
            'channel' => 'Text blast & email',
            'placeholders' => self::CASE_PLACEHOLDERS,
            'subject' => 'Your {service} has been withdrawn',
            'body' => <<<'TEXT'
                Good day, {client_name}!

                As you requested, we have recorded the withdrawal of your {service} (Ref. {reference}). We have stopped all work on it.

                If you decide to apply again in the future, we will be glad to assist you. Please message us anytime.

                Thank you, and salamat po!

                Regards,
                {agent_name}
                Amega Travel and Tours Services
                sales@amegatravelandtour.com
                TEXT,
        ],
        'case_deferred' => [
            'name' => 'Case Notice: Deferred by Applicant',
            'description' => 'From an Immigration file at Deferred by Applicant: the client asked to put the application on hold. Copied for a text or Messenger, and emailed when the client has an address.',
            'channel' => 'Text blast & email',
            'placeholders' => self::CASE_PLACEHOLDERS,
            'subject' => 'Your {service} has been put on hold',
            'body' => <<<'TEXT'
                Good day, {client_name}!

                As you asked, your {service} (Ref. {reference}) has been put on hold. We will not proceed with it until you tell us you are ready.

                Whenever you wish to continue, just message us and we will pick up from where we left off. Some documents may need to be refreshed by then, and we will guide you through it.

                Salamat po!

                Regards,
                {agent_name}
                Amega Travel and Tours Services
                sales@amegatravelandtour.com
                TEXT,
        ],
    ];

    /**
     * The wording in use: the admin's, or the default.
     *
     * @return array{subject: string, body: string, custom: bool}
     */
    public static function get(string $key): array
    {
        $default = self::TEMPLATES[$key];
        $custom = MessageTemplate::where('key', $key)->first();

        return [
            'subject' => $custom?->subject ?? $default['subject'],
            'body' => $custom?->body ?? self::dedent($default['body']),
            'custom' => $custom !== null,
        ];
    }

    /** The default wording, as the Reset button brings back. */
    public static function defaultBody(string $key): string
    {
        return self::dedent(self::TEMPLATES[$key]['body']);
    }

    /**
     * The message with its placeholders filled in. A placeholder with no value reads
     * "To be confirmed"; an unknown {word} is left as typed.
     *
     * @param  array<string, string|null>  $values
     * @return array{subject: string, body: string}
     */
    public static function render(string $key, array $values): array
    {
        $template = self::get($key);
        $fill = fn (string $text) => preg_replace_callback('/\{([a-z_]+)\}/', function (array $match) use ($values, $key) {
            if (! array_key_exists($match[1], self::TEMPLATES[$key]['placeholders'])) {
                return $match[0];
            }

            return filled($values[$match[1]] ?? null) ? (string) $values[$match[1]] : 'To be confirmed';
        }, $text);

        return ['subject' => $fill($template['subject']), 'body' => $fill($template['body'])];
    }

    /** The sample values the admin preview fills in. */
    public static function samples(string $key): array
    {
        return array_map(fn (array $placeholder) => $placeholder[1], self::TEMPLATES[$key]['placeholders']);
    }

    /**
     * A message body as email paragraphs: blank lines split paragraphs, each line keeps its own line.
     *
     * @return list<HtmlString>
     */
    public static function paragraphs(string $body): array
    {
        return collect(preg_split('/\R\s*\R/', trim(str_replace("\r\n", "\n", $body))))
            ->map(fn (string $paragraph) => new HtmlString(implode('<br>', array_map(fn ($line) => e(trim($line)), explode("\n", trim($paragraph))))))
            ->values()
            ->all();
    }

    private static function dedent(string $text): string
    {
        return implode("\n", array_map('ltrim', explode("\n", $text)));
    }
}
