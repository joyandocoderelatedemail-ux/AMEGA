<?php

namespace App\Support;

/**
 * The services the immigration counter offers, in the counter's own order. Visa
 * Extension and Visa Overstaying run on the client sheet; the rest are listed as
 * coming soon. Each service names its own new-file page.
 */
class ImmigrationServices
{
    /** The service a new client sheet is for when none is named. */
    public const DEFAULT = 'visa_extension';

    /**
     * @return list<array{key: string, label: string, icon: string, available: bool, note: string, title: string, blurb: string, url: ?string}>
     */
    public static function all(): array
    {
        $services = [
            ['ecc', 'ECC', 'file-badge'],
            ['re_stamping_amendment', 'Re Stamping Amendment', 'stamp'],
            ['permanent_13a_series', 'Permanent Sec 13/A Series', 'files'],
            ['13a_spouse', 'SEC 13/A Spouse Visa Application', 'heart-handshake'],
            ['13a_permanent', 'SEC 13/A Permanent', 'home'],
            ['visa_application', 'Visa Application', 'file-plus'],
            ['visa_overstaying', 'Visa Overstaying', 'clock-alert'],
            ['visa_downgrading', 'Visa Downgrading', 'arrow-down-circle'],
            ['visa_extension', 'Visa Extension', 'calendar-plus'],
            ['dual_citizenship', 'Dual Citizenship', 'flag'],
            ['missionary_visa', 'Missionary Visa', 'church'],
            ['filipino_recognition', 'Application for Recognition as Filipino Citizen', 'id-card'],
        ];

        // What the new-file page says for each service that runs here.
        $pages = [
            'visa_extension' => [
                'note' => 'Extension of stay, filed with the BI',
                'blurb' => 'Extension of stay with the Bureau of Immigration. Key in the client\'s information sheet and the extension, so their next visit is a lookup.',
            ],
            'visa_overstaying' => [
                'note' => 'Updating of stay after it lapsed',
                'blurb' => 'For a tourist whose authorized stay lapsed without an extension. Record why, collect the BI requirements, update the missed extensions on the ledger, and print the Request for Reconsideration with the CGAF and checklist.',
            ],
        ];

        return array_map(function (array $service) use ($pages) {
            [$key, $label, $icon] = $service;
            $available = isset($pages[$key]);

            return [
                'key' => $key,
                'label' => $label,
                'icon' => $icon,
                'available' => $available,
                'note' => $pages[$key]['note'] ?? 'Coming soon',
                'title' => 'New '.$label.' File',
                'blurb' => $pages[$key]['blurb'] ?? 'Coming soon.',
                // Like the visa counter's menu, choosing a service opens a new file for it.
                'url' => $available ? route('admin.client-sheets.start', ['service' => $key]) : null,
            ];
        }, $services);
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
