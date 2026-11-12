<?php

namespace App\Support;

use App\Models\ImmigrationClient;

/**
 * The services the immigration counter offers, in the counter's own order. Visa
 * Extension, ECC, ACR I-Card Renewal, Re Stamping Amendment, the 13(A) services, Dual
 * Citizenship, Visa Downgrading and Visa Overstaying run on the client sheet. Missionary Visa is
 * listed as coming soon until its forms are built. Each
 * service names its own new-file page.
 */
class ImmigrationServices
{
    /** AMEGA's authorized representative at the BI: every form filed by a representative names her. */
    public const REPRESENTATIVE_NAME = 'Aurora M. Agustin';

    /** Her BI accreditation number. */
    public const REPRESENTATIVE_ACCREDITATION = 'T.A 202623914-2';

    /** The BI Commissioner, to whom letter requests are addressed. */
    public const BI_COMMISSIONER = 'Hon. Joel Anthony M Viado';

    /** Where AMEGA's clients sign and swear their letters. */
    public const SIGNING_CITY = 'Angeles';

    /** The service a new client sheet is for when none is named. */
    public const DEFAULT = 'visa_extension';

    /**
     * @return list<array{key: string, label: string, icon: string, color: string, available: bool, note: string, title: string, blurb: string, url: ?string}>
     */
    public static function all(): array
    {
        // [key, label, icon, colour of the icon on the dashboard card]
        $services = [
            ['visa_extension', 'Visa Extension', 'calendar-plus', 'sky'],
            ['ecc', 'ECC', 'file-badge', 'violet'],
            ['permanent_13a_series', 'ACR I-Card Renewal', 'id-card', 'teal'],
            ['re_stamping_amendment', 'Re Stamping Amendment', 'stamp', 'indigo'],
            ['13a_spouse', '13/A Spouse Visa', 'heart-handshake', 'pink'],
            ['13a_permanent', 'SEC 13/A Permanent', 'home', 'emerald'],
            ['dual_citizenship', 'Dual Citizenship', 'flag', 'blue'],
            ['visa_downgrading', 'Visa Downgrading', 'arrow-down-circle', 'orange'],
            ['visa_overstaying', 'Visa Overstaying', 'clock-alert', 'rose'],
            ['missionary_visa', 'Missionary Visa', 'church', 'amber'],
        ];

        // What the new-file page says for each service that runs here.
        $pages = [
            'visa_extension' => [
                'note' => 'Extension of stay, filed with the BI',
                'blurb' => 'Extension of stay with the Bureau of Immigration. Key in the client\'s information sheet and the extension, so their next visit is a lookup.',
            ],
            'dual_citizenship' => [
                'note' => 'R.A. 9225 retention / re-acquisition',
                'blurb' => 'Retention or re-acquisition of Philippine citizenship under R.A. 9225, for a former natural-born Filipino. Record what the sworn petition asks, collect the BI requirements, and print the petition (BI Form 2014-01-005) and checklist.',
            ],
            'permanent_13a_series' => [
                'note' => 'ACR I-Card renewal of a 13(A) resident',
                'blurb' => 'Renewal of the ACR I-Card of a permanent 13(A) resident. Record the card and the transaction, collect the BI requirements, and print the renewal form (BI Form 2014-08-010) and checklist.',
            ],
            '13a_spouse' => [
                'note' => 'Probationary immigrant visa by marriage',
                'blurb' => 'Conversion to a non-quota immigrant visa by marriage to a Filipino (probationary). Record the petitioning spouse and the travel details, collect the BI requirements, and print the immigrant visa CGAF and checklist.',
            ],
            '13a_permanent' => [
                'note' => 'Probationary to permanent',
                'blurb' => 'Amendment of a probationary 13(A) visa to permanent. Record the petitioning spouse and the ACR I-Card, collect the BI requirements, and print the immigrant visa CGAF and checklist.',
            ],
            're_stamping_amendment' => [
                'note' => 'Transfer or correction of admission',
                'blurb' => 'Transfer of admission status to a new passport, or amendment/correction of admission (failed to check, encode or stamp). Record the arrival details, collect the BI requirements, and print the IRD application form and checklist.',
            ],
            'visa_downgrading' => [
                'note' => 'Back to temporary visitor status',
                'blurb' => 'Downgrading of a working, missionary, marriage or other visa, after resignation, termination, late filing or the company closing. Record the visa and the reason, collect the BI requirements, and print the letter request to the Commissioner and the BI checklist.',
            ],
            'ecc' => [
                'note' => 'Emigration Clearance Certificate before leaving',
                'blurb' => 'The Emigration Clearance Certificate a foreign national needs before leaving the Philippines. Record the trip and the latest admission, collect the requirements, and print the ECC application form (ARD04.QF.003) and the checklist.',
            ],
            'visa_overstaying' => [
                'note' => 'Updating of stay after it lapsed',
                'blurb' => 'For a tourist whose authorized stay lapsed without an extension. Record why, collect the BI requirements, update the missed extensions on the ledger, and print the Request for Reconsideration with the CGAF and checklist.',
            ],
        ];

        return array_map(function (array $service) use ($pages) {
            [$key, $label, $icon, $color] = $service;
            $available = isset($pages[$key]);

            return [
                'key' => $key,
                'label' => $label,
                'icon' => $icon,
                'color' => $color,
                'available' => $available,
                'note' => $pages[$key]['note'] ?? 'Coming soon',
                'title' => 'New '.$label.' File',
                'blurb' => $pages[$key]['blurb'] ?? 'Coming soon.',
                // Like the visa counter's menu, choosing a service opens a new file for it.
                'url' => $available ? route('admin.client-sheets.applications', ['service' => $key]) : null,
            ];
        }, $services);
    }

    /**
     * A client's folder opened in a service (and on a case), e.g. for a print's "Back"
     * link, so the agent returns where they were rather than to Visa Extension.
     */
    public static function folderUrl(ImmigrationClient $client, ?string $service = null, ?int $case = null): string
    {
        $key = self::find($service)['key'];

        return route('admin.client-sheets.edit', array_filter([
            'client_sheet' => $client->id,
            'service' => $key === self::DEFAULT ? null : $key,
            'case' => $case,
        ]));
    }

    /**
     * A service that runs here, by key; anything else falls back to the default.
     *
     * @return array{key: string, label: string, icon: string, available: bool, note: string, title: string, blurb: string, url: ?string}
     */
    public static function find(?string $key): array
    {
        $services = collect(self::all())->keyBy('key');
        $service = $services->get((string) $key);

        return $service && $service['available'] ? $service : $services->get(self::DEFAULT);
    }
}
