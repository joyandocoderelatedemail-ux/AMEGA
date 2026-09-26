<?php

namespace Database\Seeders;

use App\Models\VisaPricingTier;
use Illuminate\Database\Seeder;

/**
 * Visa assistance counter fees.
 *
 * Visit visa prices come from the AMEGA "Visa Assistance Fee Breakdown"
 * (revised August 1, 2026): per person, the same for adults, children and
 * infants, with the selling price split into our service fee and the visa
 * fee / expenses. The rush surcharge (PHP 5,000) is from the counter
 * flowchart. e-Visa is a starting price; each country's rate is still to be
 * confirmed. Passporting figures are not priced yet, so they stay flagged
 * for review and off the public price list.
 */
class VisaPricingSeeder extends Seeder
{
    public function run(): void
    {
        // [country, service fee, visa fee / expenses, inclusions, processing time, notes]
        $countries = [
            ['Canada', 6500, 8500, 'Biometric', null, null],
            ['USA', 9600, 11400, 'Biometric, briefing and interview', null, null],
            ['Korea', 2000, 1500, null, null, null],
            ['Japan', 3500, 2100, null, null, null],
            ['New Zealand', 6400, 3600, null, '1-3 weeks', 'No embassy appearance needed.'],
            ['Schengen (Europe)', 7000, 0, null, null, 'Embassy / VFS visa fee is paid by the client directly. Add PHP 1,000 per person when the client takes travel insurance through us.'],
            ['China', 5000, 4900, null, null, null],
            ['Dubai', 3500, 6500, null, null, null],
            ['United Kingdom', 7100, 11400, 'Biometric', null, null],
            ['Australia', 7000, 11000, null, '1-3 weeks', 'No embassy appearance needed.'],
        ];

        $tiers = [];

        foreach ($countries as $index => [$country, $serviceFee, $visaFee, $inclusions, $processingTime, $notes]) {
            $tiers[] = [
                'service_type' => 'visit_visa',
                'label' => "Visit Visa — {$country}",
                'country' => $country,
                'amount' => $serviceFee + $visaFee,
                'service_fee' => $serviceFee,
                'visa_fee' => $visaFee,
                'insurance_fee' => $country === 'Schengen (Europe)' ? 1000 : null,
                'inclusions' => $inclusions,
                'condition_notes' => $notes ?? 'Per person; the same for adults, children and infants.',
                'processing_time' => $processingTime,
                'needs_review' => false,
                'sort_order' => 10 + $index,
            ];
        }

        $tiers[] = [
            'service_type' => 'visit_visa',
            'label' => 'Visit Visa — Rush',
            'condition_notes' => 'PHP 5,000 added to the bill for expedited handling.',
            'processing_time' => '1-5 days',
            'amount' => 5000,
            'needs_review' => false,
            'sort_order' => 1,
        ];

        $tiers[] = [
            'service_type' => 'e_visa',
            'label' => 'e-Visa — Individual',
            'condition_notes' => 'Starting price per person; the rate differs by country. No appearance, no embassy queue.',
            'processing_time' => '1-3 days',
            'amount' => 3500,
            'service_fee' => 3500,
            'needs_review' => false,
            'sort_order' => 1,
        ];

        $tiers[] = [
            'service_type' => 'e_visa',
            'label' => 'e-Visa — Group Package',
            'condition_notes' => 'Several pax under one package, priced per person from PHP 3,500; the rate differs by country.',
            'processing_time' => '1-3 days',
            'amount' => 3500,
            'service_fee' => 3500,
            'needs_review' => false,
            'sort_order' => 2,
        ];

        $tiers[] = [
            'service_type' => 'passporting',
            'label' => 'Passporting — Foreign Passport',
            'condition_notes' => 'USA, Canada, Australia and the United Kingdom. Photos to the embassy specification.',
            'processing_time' => null,
            'amount' => 0,
            'needs_review' => true,
            'sort_order' => 1,
        ];

        $tiers[] = [
            'service_type' => 'passporting',
            'label' => 'Passporting — Local Passport',
            'condition_notes' => 'Philippine passport processed through the DFA. Appointment is the step the timeline hangs on.',
            'processing_time' => null,
            'amount' => 0,
            'needs_review' => true,
            'sort_order' => 2,
        ];

        foreach ($tiers as $tier) {
            VisaPricingTier::updateOrCreate(
                [
                    'service_type' => $tier['service_type'],
                    'label' => $tier['label'],
                ],
                $tier + ['currency' => 'PHP', 'is_active' => true]
            );
        }
    }
}
