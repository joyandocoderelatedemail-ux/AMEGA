<?php

namespace Database\Seeders;

use App\Models\VisaPricingTier;
use Illuminate\Database\Seeder;

/**
 * Visa assistance counter fees.
 *
 * Only the rush surcharge is specified in the source flowchart (PHP 5,000).
 * Every other figure is seeded with needs_review = true so it stays off the
 * public price list until someone confirms the real amount at the counter.
 */
class VisaPricingSeeder extends Seeder
{
    public function run(): void
    {
        $tiers = [
            [
                'service_type' => 'visit_visa',
                'label' => 'Visit Visa — Regular',
                'condition_notes' => 'Embassy decides; no guaranteed turnaround.',
                'processing_time' => '1-3 weeks',
                'amount' => 0,
                'needs_review' => true,
                'sort_order' => 1,
            ],
            [
                'service_type' => 'visit_visa',
                'label' => 'Visit Visa — Rush',
                'condition_notes' => 'PHP 5,000 added to the bill for expedited handling.',
                'processing_time' => '1-5 days',
                'amount' => 5000,
                'needs_review' => false,
                'sort_order' => 2,
            ],
            [
                'service_type' => 'visit_visa',
                'label' => 'Visit Visa — Australia & New Zealand',
                'condition_notes' => 'No embassy appearance needed.',
                'processing_time' => '1-3 weeks',
                'amount' => 0,
                'needs_review' => true,
                'sort_order' => 3,
            ],
            [
                'service_type' => 'e_visa',
                'label' => 'e-Visa — Individual',
                'condition_notes' => 'Priced per applicant. No appearance, no embassy queue.',
                'processing_time' => '1-3 days',
                'amount' => 0,
                'needs_review' => true,
                'sort_order' => 1,
            ],
            [
                'service_type' => 'e_visa',
                'label' => 'e-Visa — Group Package',
                'condition_notes' => 'Several pax booked together under one package. Same steps either way.',
                'processing_time' => '1-3 days',
                'amount' => 0,
                'needs_review' => true,
                'sort_order' => 2,
            ],
            [
                'service_type' => 'passporting',
                'label' => 'Passporting — Foreign Passport',
                'condition_notes' => 'USA, Canada, Australia and the United Kingdom. Photos to the embassy specification.',
                'processing_time' => null,
                'amount' => 0,
                'needs_review' => true,
                'sort_order' => 1,
            ],
            [
                'service_type' => 'passporting',
                'label' => 'Passporting — Local Passport',
                'condition_notes' => 'Philippine passport processed through the DFA. Appointment is the step the timeline hangs on.',
                'processing_time' => null,
                'amount' => 0,
                'needs_review' => true,
                'sort_order' => 2,
            ],
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
