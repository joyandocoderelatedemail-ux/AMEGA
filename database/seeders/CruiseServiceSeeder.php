<?php

namespace Database\Seeders;

use App\Models\Service;
use Illuminate\Database\Seeder;

/**
 * Adds the "Cruise Services" card to Our Core Services on a site that is already live.
 *
 * Unlike ServiceSeeder (which re-saves every core service and would overwrite edits made in the
 * admin), this only creates the card when it is missing and never touches one that exists.
 */
class CruiseServiceSeeder extends Seeder
{
    /** The card as the public site shows it. ServiceSeeder uses the same row for a fresh install. */
    public static function row(): array
    {
        return [
            'title' => 'Cruise Services',
            'short_description' => 'Fly & Cruise packages from Manila with Royal Caribbean, Star Cruises, MSC, Disney and Adora.',
            'full_description' => implode("\n", [
                'Disney Adventure - Singapore',
                'Star Navigator - Taiwan, Korea and Japan',
                'Navigator of the Seas - Singapore, Penang and Phuket',
                'Spectrum of the Seas - Hong Kong, Japan and Taiwan',
                'MSC Bellissima - Taiwan and Japan',
                'Adora Mediterranea - Manila to Miyakojima, Japan',
            ]),
            'icon' => 'ship',
            'image' => 'newassets/2026-2027 CRUISE/cruise-services-card.jpg',
            'badge' => 'Fly & Cruise 2026-2027',
            'email' => 'ticketing@amegatravelandtour.com',
            'logo_strip' => 'images/services/cruise-lines.png',
            'order' => 8,
        ];
    }

    public function run(): void
    {
        $row = self::row();

        Service::firstOrCreate(['title' => $row['title']], $row);
    }
}
