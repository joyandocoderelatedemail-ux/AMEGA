<?php

namespace App\Support;

/**
 * The Local Tours desk's catalog, from the "Local Tours Booking Procedures Workflow": the tours
 * AMEGA books, who supplies each one, what the supplier needs to know about the guests, the extra
 * checks each tour adds to the booking steps, and the AMEGA drivers.
 *
 * A supplier's or driver's number changes here, in one place.
 */
class LocalTours
{
    /**
     * Tour key => what the desk needs to book it.
     *
     *  - contact_label: how the supplier is named on the page (a supplier, or a tour guide)
     *  - nationality: whether the supplier needs the guests' nationality ('required' or 'if_required')
     *  - asks: optional booking fields this tour uses (children_ages, attractions)
     *  - guest_info: the guest information the supplier asks for
     *  - checks: extra checks at a stage, on top of the steps every tour shares (stage => key => label)
     *  - important: the procedure's caution for this tour
     *
     * @var array<string, array<string, mixed>>
     */
    public const TOURS = [
        'puning' => [
            'label' => 'Puning Hot Spring',
            'icon' => 'flame',
            'contact_label' => 'Supplier / Contact Person',
            'supplier' => 'Ms. Grace',
            'phone' => '0968 724 0148',
            'accreditation' => null,
            'nationality' => 'if_required',
            'asks' => [],
            'guest_info' => [
                'Complete name of the lead guest',
                'Total number of guests (adults and children)',
                'Valid IDs of guests, as required by the operator',
                'Contact number',
                'Pick-up and drop-off location',
                'Preferred travel date',
                'Nationality, if required by the operator',
            ],
            'checks' => [],
            'important' => null,
        ],
        'corregidor' => [
            'label' => 'Corregidor Island Tour',
            'icon' => 'ship',
            'contact_label' => 'Supplier / Contact Person',
            'supplier' => 'Guia Del Mar Travel and Tours',
            'phone' => '+63 997 764 0946',
            'accreditation' => null,
            'nationality' => 'required',
            'asks' => [],
            'guest_info' => [
                'Complete name of the lead guest',
                'Total number of guests (adults and children)',
                'Valid IDs',
                'Contact number',
                'Pick-up and drop-off location',
                'Nationality of each guest',
                'Preferred travel date',
                'Special requests or requirements',
            ],
            'checks' => [
                'availability' => [
                    'schedule_verified' => 'Verified the meeting point, ferry departure schedule and return schedule',
                    'terminal_transport' => 'Confirmed whether transportation to the ferry terminal is included or separately arranged',
                ],
            ],
            'important' => 'Confirm whether transportation to the ferry terminal is included or separately arranged. Verify ferry schedules and check-in requirements before confirming the booking.',
        ],
        'angeles_city' => [
            'label' => 'Angeles City Tour (Private / Exclusive)',
            'icon' => 'map',
            'contact_label' => 'Tour Guide',
            'supplier' => 'Maria Cristina Hulleza or any DOT-accredited guide',
            'phone' => null,
            'accreditation' => 'DOT-accredited Tour Guide',
            'nationality' => 'required',
            'asks' => ['attractions'],
            'guest_info' => [
                'Complete name of the lead guest',
                'Total number of guests (adults and children)',
                'Valid IDs',
                'Contact number',
                'Pick-up and drop-off location',
                'Nationality of guests',
                'Preferred travel date and tour schedule',
                'Preferred attractions or places to visit',
                'Special requests or accessibility requirements',
            ],
            'checks' => [
                'availability' => [
                    'itinerary_discussed' => 'Discussed the preferred itinerary, duration and attractions',
                    'inclusions_confirmed' => 'Confirmed whether transportation, entrance fees, meals and other expenses are included',
                ],
            ],
            'important' => "Confirm the guide's fee and payment arrangements directly. If the tour requires a separate vehicle, account for driver fees, fuel, parking and applicable entrance fees.",
        ],
        'camayan' => [
            'label' => 'Camayan Beach Resort (Day Tour)',
            'icon' => 'umbrella',
            'contact_label' => 'Supplier / Contact Person',
            'supplier' => 'Camayan Beach Resort',
            'phone' => '(047) 252 8000',
            'accreditation' => null,
            'nationality' => 'required',
            'asks' => ['children_ages'],
            'guest_info' => [
                'Complete name of the lead guest',
                'Total number of guests (adults and children)',
                'Valid IDs',
                'Contact number',
                'Pick-up and drop-off location',
                'Nationality of guests',
                'Preferred travel date',
                'Age of children, if applicable',
                'Special requests',
            ],
            'checks' => [
                'availability' => [
                    'package_confirmed' => 'Confirmed the applicable package, inclusions and resort access',
                    'hours_verified' => 'Verified the operating hours and check-in requirements',
                    'charges_verified' => 'Verified beach and swimming pool access, meal arrangements and any additional charges',
                ],
            ],
            'important' => 'Verify the day-tour inclusions, beach access, swimming pool access, meal arrangements and any additional charges before accepting payment.',
        ],
    ];

    /** The AMEGA drivers: key => [name, contact number]. */
    public const DRIVERS = [
        'rey' => ['Sir Rey', '0916 849 1928'],
        'cris' => ['Sir Cris', '0962 622 8009'],
        'arnel' => ['Sir Arnel', '0921 248 5035'],
    ];

    /** Who takes the guests there. */
    public const TRANSPORT = [
        'amega' => 'AMEGA driver and vehicle',
        'supplier' => 'Included by the supplier',
        'own' => 'The guests arrange their own',
    ];

    /** Driver assignment procedure, in order. */
    public const DRIVER_PROCEDURE = [
        'Check driver availability on the confirmed travel date.',
        'Confirm vehicle availability and appropriate passenger capacity.',
        'Assign the driver based on the number of guests and itinerary.',
        'Provide the driver with the travel date and pick-up time; guest name and contact number; number of passengers; pick-up and drop-off locations; tour itinerary and destinations; and special instructions.',
        "Confirm the driver's acceptance of the assignment.",
        "Send the driver's name and contact number to the client when appropriate.",
        'Reconfirm transportation arrangements two days before the tour.',
    ];

    public const DRIVER_IMPORTANT = 'Do not promise transportation until the driver and vehicle have confirmed availability. Confirm whether fuel, toll fees, parking, waiting time and overtime are included in the transportation quotation.';

    /** The procedure's closing reminders. */
    public const REMINDERS = [
        'Never confirm a booking to the client without supplier confirmation.',
        'Never send payment to a supplier without verifying the booking details and payment instructions.',
        "Always secure the client's booking agreement before processing.",
        'Make sure all guest details are accurate, especially names and identification documents.',
        'Always clarify whether transportation is included or charged separately.',
        'Keep proof of payment and supplier confirmation.',
        'Release the voucher only after checking the booking details.',
        'Always provide emergency contact details and important travel reminders.',
        'Immediately inform the client of any changes from the supplier.',
        'Keep a copy of all booking records for future reference.',
    ];

    /** @return array<string, mixed>|null */
    public static function tour(?string $key): ?array
    {
        return self::TOURS[$key] ?? null;
    }

    /** @return array<string, string> key => label, for a select */
    public static function labels(): array
    {
        return array_map(fn (array $tour): string => $tour['label'], self::TOURS);
    }

    public static function driverName(?string $key): ?string
    {
        return self::DRIVERS[$key][0] ?? null;
    }

    public static function driverPhone(?string $key): ?string
    {
        return self::DRIVERS[$key][1] ?? null;
    }
}
