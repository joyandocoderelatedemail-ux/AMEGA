<?php

namespace Database\Seeders;

use App\Models\Service;
use Illuminate\Database\Seeder;

class ServiceSeeder extends Seeder
{
    public function run(): void
    {
        $services = [
            [
                'title' => 'Immigration Service Request',
                'short_description' => 'Official Bureau of Immigration (BI) processing for tourist visa extensions, ECC clearance, ACR I-Cards, SSP, and Dual Citizenship.',
                'full_description' => 'Visa Extension & ECC Clearance. ACR I-Card Issuance. SSP & Study Permits.',
                'icon' => 'shield-check',
                'image' => 'newassets/Amega Services/IMMIGRATION/ADS/IMMIG SERVICES 01.jpg',
                'badge' => 'BI Accredited',
                'order' => 1,
            ],
            [
                'title' => 'Passport & Visa Processing',
                'short_description' => 'Expert passport renewal, DFA appointments, Japan, Korea, Schengen, USA, UK, and Canada tourist visa processing.',
                'full_description' => 'DFA Passport Appointment & Renewal. Schengen, USA, UK & Japan Visas. Complete Document Translation.',
                'icon' => 'file-text',
                'image' => 'newassets/Amega Services/PASSPORTING/Renew Replace Relax.jpg',
                'badge' => 'High Approval Rate',
                'order' => 2,
            ],
            [
                'title' => 'PRA Retirement Visa (SRRV)',
                'short_description' => 'Special Resident Retiree\'s Visa (SRRV) processing accredited by the Philippine Retirement Authority for global retirees.',
                'full_description' => 'SRRV Smile & Classic Visa Option. Complete PRA Application Assistance. Retirement Property Guidance.',
                'icon' => 'award',
                'image' => 'newassets/Amega Services/SRRV/2026 AMEGA SRRV NEW.jpg',
                'badge' => 'PRA Accredited',
                'order' => 3,
            ],
            [
                'title' => 'Tourist Visa Extensions',
                'short_description' => 'Hassle-free extension of stay for foreign tourists in the Philippines with expedited BI processing.',
                'full_description' => 'Express Tourist Visa Extension. Motion for Extension. Overstaying Legalization Assistance.',
                'icon' => 'clock',
                'image' => 'newassets/Amega Services/VISA EXTENSION/2026 AMEGA VISA EXTENSION 1.jpg',
                'badge' => 'Express Service',
                'order' => 4,
            ],
            [
                'title' => 'Schengen & Global Visas',
                'short_description' => 'Comprehensive visa consultation, travel insurance, flight reservation, and embassy interview coaching.',
                'full_description' => 'Schengen Visa & Europe Entry. Australia & UK Visa Assistance. Embassy Interview Coaching.',
                'icon' => 'globe',
                'image' => 'newassets/Amega Services/VISA ASSISTANCE/ADS/2026 AMEGA SCHENGEN 1.jpg',
                'badge' => 'Global Coverage',
                'order' => 5,
            ],
            [
                'title' => 'Domestic & Island Packages',
                'short_description' => 'Bespoke domestic island adventures in Boracay, Palawan, Siargao, Batanes, and Mt. Pinatubo 4x4 volcano treks.',
                'full_description' => 'Domestic Island Hopping Packages. 4x4 Mt. Pinatubo Trekking. All-Inclusive Hotel & Transfer Bookings.',
                'icon' => 'compass',
                'image' => 'newassets/2026-2027 DOMESTIC/2026 AMEGA EL NIDO NEW.jpg',
                'badge' => 'All-Inclusive',
                'order' => 6,
            ],
        ];

        foreach ($services as $srv) {
            Service::updateOrCreate(
                ['title' => $srv['title']],
                $srv
            );
        }
    }
}
