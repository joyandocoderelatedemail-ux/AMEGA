<?php

namespace Database\Seeders;

use App\Models\SrrvPricingTier;
use Illuminate\Database\Seeder;

/**
 * SRRV desk fees.
 *
 * The annual renewal fee is specified in the source flowchart and turns on the
 * visa class alone. The application and re-stamping fees are not given, so they
 * seed with needs_review = true and stay off the public price list.
 */
class SrrvPricingSeeder extends Seeder
{
    public function run(): void
    {
        $tiers = [
            [
                'service_type' => 'renewal',
                'visa_class' => 'classic',
                'label' => 'SRRV Classic — Annual Renewal',
                'condition_notes' => 'Per year, for as long as the retiree stays.',
                'amount' => 360,
                'currency' => 'USD',
                'needs_review' => false,
                'sort_order' => 1,
            ],
            [
                'service_type' => 'renewal',
                'visa_class' => 'courtesy',
                'label' => 'SRRV Courtesy — Annual Renewal',
                'condition_notes' => 'Per year. Courtesy covers government employees and military, aged 50 and above.',
                'amount' => 10,
                'currency' => 'USD',
                'needs_review' => false,
                'sort_order' => 2,
            ],
            [
                'service_type' => 'renewal_application',
                'visa_class' => 'classic',
                'label' => 'SRRV Classic — Renewal Application',
                'condition_notes' => 'Standard PRA checklist plus police clearance and proof of pension. Paid in full; no deposit at this stage.',
                'amount' => 0,
                'currency' => 'USD',
                'needs_review' => true,
                'sort_order' => 1,
            ],
            [
                'service_type' => 'renewal_application',
                'visa_class' => 'courtesy',
                'label' => 'SRRV Courtesy — Renewal Application',
                'condition_notes' => 'Standard PRA checklist plus proof of military service. Paid in full; no deposit at this stage.',
                'amount' => 0,
                'currency' => 'USD',
                'needs_review' => true,
                'sort_order' => 2,
            ],
            [
                'service_type' => 'restamping',
                'visa_class' => 'any',
                'label' => 'SRRV Re-stamping',
                'condition_notes' => 'Not yet specified — awaiting the counter workflow.',
                'amount' => 0,
                'currency' => 'USD',
                'needs_review' => true,
                'sort_order' => 1,
            ],
        ];

        foreach ($tiers as $tier) {
            SrrvPricingTier::updateOrCreate(
                [
                    'service_type' => $tier['service_type'],
                    'visa_class' => $tier['visa_class'],
                    'label' => $tier['label'],
                ],
                $tier + ['is_active' => true]
            );
        }
    }
}
