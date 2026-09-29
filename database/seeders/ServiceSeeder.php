<?php

namespace Database\Seeders;

use App\Models\Service;
use Illuminate\Database\Seeder;

class ServiceSeeder extends Seeder
{
    /**
     * Titles from the earlier four-card layout; the seven services below replace them.
     *
     * @var list<string>
     */
    private const RETIRED_TITLES = [
        'Immigration Service Request',
        'Passport & Visa Processing',
        'Tourist Visa Extensions',
        'PRA Retirement Visa (SRRV)',
    ];

    public function run(): void
    {
        $services = [
            [
                'title' => 'Visa Assistance',
                'short_description' => 'We process Tourist, Fiance & Spouse Visa to USA, Australia, UK, Canada and other Schengen Countries.',
                'full_description' => "Tourist, Fiance & Spouse Visa to USA, Australia, UK, Canada and other Schengen Countries\nTourist Visa to Japan, Korea, China, Dubai, Saudi Arabia and other countries",
                'icon' => 'stamp',
                'image' => 'images/public logo/visaaaaaaa.webp',
                'badge' => 'High Approval Rate',
                'email' => 'visas@amegatravelandtours.com',
                'order' => 1,
            ],
            [
                'title' => 'Passport Assistance',
                'short_description' => 'Apply for a new passport or renew your existing one.',
                'full_description' => 'Apply NEW or RENEW Passport for Philippines, USA, UK & Australia',
                'icon' => 'book-user',
                'image' => 'images/public logo/passport.jpeg',
                'badge' => 'New & Renewal',
                'email' => 'passportassistance@amegatravelandtours.com',
                'order' => 2,
            ],
            [
                'title' => 'Immigration Services',
                'short_description' => 'Bureau of Immigration processing for extensions, work permits, ACR I-Cards, clearances and record concerns.',
                'full_description' => "Visa Extensions\nVisa Updating for Overstaying Tourist\nExit Clearances\n9G Working Visa\nMissionary Visa\nImmigrant Visa by Marriage (SEC13A)\nDual Citizenship\nSpecial Work Permit - Commercial\nPermanent ACR I-CARD Renewal/Re-Issuance\nECC (Emigration Clearance Certificate)\nApplication for Correction\nStamping and Transfer Request for Travel Record\nVisa Downgrading\nBlacklist / Derogatory Record Lifting",
                'icon' => 'shield-check',
                'image' => 'images/public logo/immigration.jpg',
                'badge' => 'BI Accredited',
                'order' => 3,
            ],
            [
                'title' => 'PRA - Retirement Visa',
                'short_description' => 'Application and renewal of the Special Resident Retiree\'s Visa (SRRV).',
                'full_description' => "Application\nRenewal\nSpecial Resident Retiree's Visa (SRRV)",
                'icon' => 'award',
                'image' => 'images/public logo/srrv.jpg',
                'badge' => 'PRA Accredited',
                'email' => 'srrv-pra@amegatravelandtours.com',
                'order' => 4,
            ],
            [
                'title' => 'Documentation Services',
                'short_description' => 'Citizenship, embassy and marital-status documents for Australia, the USA and the UK.',
                'full_description' => "Australian Citizenship by Descent\nReport of Birth Abroad for American\nEmbassy Assistance\nApplication for Certificate of Impediment to Marriage (CNI) - Australia\nGreen Card Renewal\nCertificate of Legal Capacity of Contract Marriage - USA\nAffirmation/Affidavit of Marital Status - UK",
                'icon' => 'file-text',
                'image' => 'newassets/Amega Services/PASSPORTING/Renew Replace Relax.jpg',
                'badge' => 'Embassy Support',
                'email' => 'sales@amegatravelandtours.com',
                'order' => 5,
            ],
            [
                'title' => 'Travel Services',
                'short_description' => 'Ticketing, tour packages and cruises, all in one place.',
                'full_description' => "International & Domestic Ticketing\nInternational & Domestic Tour Packages\nInternational Cruises",
                'icon' => 'plane',
                'image' => 'images/tours/tour-intl-1.jpg',
                'badge' => 'Flights, Tours & Cruises',
                'email' => 'ticketing@amegatravelandtours.com',
                'logo_strip' => 'images/services/cruise-lines.png',
                'order' => 6,
            ],
            [
                'title' => 'Local Tour Services',
                'short_description' => 'Guided day tours and getaways around Pampanga and nearby destinations.',
                'full_description' => "Puning Hot Spring\nClark Pampanga\nGastroventure\nMt. Pinatubo\nPampanga Heritage\nCorregidor\nTutulari Avatar Gorge\nLas Casas Filipinas De Acuzar\nHundred Islands\nSubic Adventure",
                'icon' => 'map-pin',
                'image' => 'images/gallery/mtpinatubo-1.jpg',
                'badge' => 'Guided Tours',
                'email' => 'localtours@amegatravelandtours.com',
                'order' => 7,
            ],
        ];

        Service::whereIn('title', self::RETIRED_TITLES)->delete();

        foreach ($services as $srv) {
            Service::updateOrCreate(
                ['title' => $srv['title']],
                $srv
            );
        }
    }
}
