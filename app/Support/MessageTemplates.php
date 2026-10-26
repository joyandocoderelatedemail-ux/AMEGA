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
