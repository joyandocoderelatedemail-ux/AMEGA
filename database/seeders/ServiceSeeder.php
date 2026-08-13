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
                'title' => 'Tourist Visa Extensions',
                'short_description' => 'Hassle-free extension of stay for foreign tourists in the Philippines with expedited BI processing.',
                'full_description' => 'Express Tourist Visa Extension. Motion for Extension. Overstaying Legalization Assistance.',
                'icon' => 'clock',
                'image' => 'newassets/Amega Services/VISA EXTENSION/2026 AMEGA VISA EXTENSION 1.jpg',
                'badge' => 'Express Service',
                'order' => 3,
            ],
            [
                'title' => 'PRA Retirement Visa (SRRV)',
                'short_description' => 'Special Resident Retiree\'s Visa (SRRV) processing accredited by the Philippine Retirement Authority for global retirees.',
                'full_description' => 'SRRV Smile & Classic Visa Option. Complete PRA Application Assistance. Retirement Property Guidance.',
                'icon' => 'award',
                'image' => 'newassets/Amega Services/SRRV/2026 AMEGA SRRV NEW.jpg',
                'badge' => 'PRA Accredited',
                'order' => 4,
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
