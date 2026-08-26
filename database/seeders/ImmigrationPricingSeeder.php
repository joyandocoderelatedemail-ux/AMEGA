<?php

namespace Database\Seeders;

use App\Models\ImmigrationCategory;
use Illuminate\Database\Seeder;

/**
 * Seeds the Bureau of Immigration pricing sheet transcription.
 *
 * Rows transcribed from illegible or ambiguous parts of the source sheet are
 * seeded with needs_review = true and is_active = false so they appear in the
 * admin panel for staff confirmation but never reach the public price list.
 */
class ImmigrationPricingSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->categories() as $category) {
            $model = ImmigrationCategory::updateOrCreate(
                ['slug' => $category['slug']],
                [
                    'name' => $category['name'],
                    'description' => $category['description'],
                    'icon' => $category['icon'],
                    'processing_time' => $category['processing_time'],
                    'sort_order' => $category['sort_order'],
                    'is_active' => true,
                ]
            );

            foreach (array_values($category['requirements'] ?? []) as $index => $requirement) {
                $model->requirements()->updateOrCreate(
                    ['label' => $requirement['label']],
                    [
                        'type' => $requirement['type'] ?? 'requirement',
                        'needs_review' => $requirement['needs_review'] ?? false,
                        'sort_order' => $index + 1,
                    ]
                );
            }

            foreach (array_values($category['tiers'] ?? []) as $index => $tier) {
                $processType = $tier['process_type'] ?? 'regular';

                $model->pricingTiers()->updateOrCreate(
                    [
                        'extension_label' => $tier['extension_label'] ?? null,
                        'duration_label' => $tier['duration_label'] ?? null,
                        'process_type' => $processType,
                        'payment_method' => $tier['payment_method'] ?? 'cash',
                        'condition_notes' => $tier['condition_notes'] ?? null,
                    ],
                    [
                        'price' => $tier['price'],
                        'processing_time' => $tier['processing_time'] ?? ($processType === 'express' ? '1 day' : '7-10 working days'),
                        'needs_review' => $tier['needs_review'] ?? false,
                        'is_active' => $tier['is_active'] ?? true,
                        'sort_order' => $index + 1,
                    ]
                );
            }
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function categories(): array
    {
        return [
            [
                'slug' => 'visa-extension',
                'name' => 'Tourist Visa Extension',
                'description' => 'Extension of stay for foreign nationals in the Philippines, processed through the Bureau of Immigration. Regular processing runs 7-10 working days; express is a 1-day process.',
                'icon' => 'stamp',
                'processing_time' => '7-10 working days (regular) / 1 day (express)',
                'sort_order' => 1,
                'requirements' => [
                    ['type' => 'note', 'label' => 'Regular process (7-10 working days) applies when the visa is still valid for 8 days or more.'],
                    ['type' => 'note', 'label' => 'Express process (1 day) applies when the visa has less than 7 days validity, or has already expired.'],
                    ['type' => 'note', 'label' => 'The 1st Extension covers the 29-day Visa Waiver granted on arrival, provided the client has 30 days of additional validity.'],
                    ['type' => 'note', 'label' => 'The 2nd Extension applies when the client held a 9(a) visa and has already completed 1 extension, or holds a Balik-Bayan visa. When the extension falls together with I-Card processing, it is done express.'],
                    ['type' => 'note', 'needs_review' => true, 'label' => 'Indian passport holders: add PHP 300 to every extension. PENDING CONFIRMATION - the handwritten note on the source sheet says this depends on the stamper and applies specifically to 9(g) visas. Confirm the exact scope with staff before publishing.'],
                ],
                'tiers' => [
                    // 1st Extension - cash
                    ['extension_label' => '1st Extension', 'duration_label' => '29 days', 'process_type' => 'regular', 'payment_method' => 'cash', 'condition_notes' => 'Visa waiver on arrival, visa validity is 8 days or more', 'price' => 2930],
                    ['extension_label' => '1st Extension', 'duration_label' => '29 days', 'process_type' => 'express', 'payment_method' => 'cash', 'condition_notes' => 'Visa waiver on arrival, visa validity is less than 8 days', 'price' => 5080],
                    ['extension_label' => '1st Extension', 'duration_label' => '29 days', 'process_type' => 'express', 'payment_method' => 'cash', 'condition_notes' => 'Visa waiver on arrival, visa expired - includes penalties', 'price' => 6090],

                    // 2nd Extension - cash
                    ['extension_label' => '2nd Extension', 'duration_label' => '2 months', 'process_type' => 'express', 'payment_method' => 'cash', 'condition_notes' => 'No valid ACR I-Card', 'price' => 10400],
                    ['extension_label' => '2nd Extension', 'duration_label' => '2 months', 'process_type' => 'express', 'payment_method' => 'cash', 'condition_notes' => 'No valid ACR I-Card, visa expired', 'price' => 11880],
                    ['extension_label' => '2nd Extension', 'duration_label' => '1 month', 'process_type' => 'express', 'payment_method' => 'cash', 'condition_notes' => 'No valid ACR I-Card', 'price' => 10000],
                    ['extension_label' => '2nd Extension', 'duration_label' => '1 month', 'process_type' => 'express', 'payment_method' => 'cash', 'condition_notes' => 'No valid ACR I-Card, visa expired', 'price' => 11380],
                    ['extension_label' => '2nd Extension', 'duration_label' => '2 months', 'process_type' => 'express', 'payment_method' => 'cash', 'condition_notes' => 'I-Card needs renewal', 'price' => 9500],
                    ['extension_label' => '2nd Extension', 'duration_label' => '2 months', 'process_type' => 'express', 'payment_method' => 'cash', 'condition_notes' => 'I-Card needs renewal, visa expired', 'price' => 10680],
                    ['extension_label' => '2nd Extension', 'duration_label' => '1 month', 'process_type' => 'express', 'payment_method' => 'cash', 'condition_notes' => 'I-Card needs renewal', 'price' => 9000],
                    ['extension_label' => '2nd Extension', 'duration_label' => '1 month', 'process_type' => 'express', 'payment_method' => 'cash', 'condition_notes' => 'I-Card needs renewal, visa expired', 'price' => 10180],
                    ['extension_label' => '2nd Extension', 'duration_label' => '2 months', 'process_type' => 'regular', 'payment_method' => 'cash', 'condition_notes' => 'Valid ACR I-Card', 'price' => 3250],
                    ['extension_label' => '2nd Extension', 'duration_label' => '2 months', 'process_type' => 'express', 'payment_method' => 'cash', 'condition_notes' => 'Valid ACR I-Card', 'price' => 5600],
                    ['extension_label' => '2nd Extension', 'duration_label' => '2 months', 'process_type' => 'express', 'payment_method' => 'cash', 'condition_notes' => 'Valid ACR I-Card, visa expired', 'price' => 6610],
                    ['extension_label' => '2nd Extension', 'duration_label' => '1 month', 'process_type' => 'regular', 'payment_method' => 'cash', 'condition_notes' => 'Valid ACR I-Card', 'price' => 2750],
                    ['extension_label' => '2nd Extension', 'duration_label' => '1 month', 'process_type' => 'express', 'payment_method' => 'cash', 'condition_notes' => 'Valid ACR I-Card', 'price' => 5100],
                    ['extension_label' => '2nd Extension', 'duration_label' => '1 month', 'process_type' => 'express', 'payment_method' => 'cash', 'condition_notes' => 'Valid ACR I-Card, visa expired', 'price' => 6110],

                    // 3rd Extension - cash
                    ['extension_label' => '3rd Extension', 'duration_label' => '2 months', 'process_type' => 'regular', 'payment_method' => 'cash', 'condition_notes' => null, 'price' => 2730],
                    ['extension_label' => '3rd Extension', 'duration_label' => '2 months', 'process_type' => 'express', 'payment_method' => 'cash', 'condition_notes' => null, 'price' => 4880],
                    ['extension_label' => '3rd Extension', 'duration_label' => '2 months', 'process_type' => 'express', 'payment_method' => 'cash', 'condition_notes' => 'Visa expired', 'price' => 5890],
                    ['extension_label' => '3rd Extension', 'duration_label' => '1 month', 'process_type' => 'regular', 'payment_method' => 'cash', 'condition_notes' => null, 'price' => 2230],
                    ['extension_label' => '3rd Extension', 'duration_label' => '1 month', 'process_type' => 'express', 'payment_method' => 'cash', 'condition_notes' => null, 'price' => 4380],
                    ['extension_label' => '3rd Extension', 'duration_label' => '1 month', 'process_type' => 'express', 'payment_method' => 'cash', 'condition_notes' => 'Visa expired', 'price' => 5390],
                    ['extension_label' => '3rd Extension', 'duration_label' => '2 months', 'process_type' => 'express', 'payment_method' => 'cash', 'condition_notes' => 'Plus I-Card renewal', 'price' => 9000],

                    // 3rd Extension - cash, ambiguous page-break rows awaiting staff confirmation
                    ['extension_label' => '3rd Extension', 'duration_label' => '2 months', 'process_type' => 'express', 'payment_method' => 'cash', 'condition_notes' => 'Plus I-Card renewal, visa expired', 'price' => 10000, 'needs_review' => true, 'is_active' => false],
                    ['extension_label' => '3rd Extension', 'duration_label' => '1 month', 'process_type' => 'express', 'payment_method' => 'cash', 'condition_notes' => 'Plus I-Card renewal', 'price' => 8500, 'needs_review' => true, 'is_active' => false],
                    ['extension_label' => '3rd Extension', 'duration_label' => '1 month', 'process_type' => 'express', 'payment_method' => 'cash', 'condition_notes' => 'Plus I-Card renewal, visa expired', 'price' => 9500, 'needs_review' => true, 'is_active' => false],

                    // 1st Extension - card
                    ['extension_label' => '1st Extension', 'duration_label' => '29 days', 'process_type' => 'regular', 'payment_method' => 'card', 'condition_notes' => 'Visa waiver', 'price' => 3048],
                    ['extension_label' => '1st Extension', 'duration_label' => '29 days', 'process_type' => 'express', 'payment_method' => 'card', 'condition_notes' => 'Visa waiver', 'price' => 5284],
                    ['extension_label' => '1st Extension', 'duration_label' => '29 days', 'process_type' => 'express', 'payment_method' => 'card', 'condition_notes' => 'Visa waiver, visa expired', 'price' => 6334],

                    // 2nd Extension - card
                    ['extension_label' => '2nd Extension', 'duration_label' => '2 months', 'process_type' => 'regular', 'payment_method' => 'card', 'condition_notes' => 'Valid ACR', 'price' => 3380],
                    ['extension_label' => '2nd Extension', 'duration_label' => '2 months', 'process_type' => 'express', 'payment_method' => 'card', 'condition_notes' => 'Valid ACR', 'price' => 5824],
                    ['extension_label' => '2nd Extension', 'duration_label' => '2 months', 'process_type' => 'express', 'payment_method' => 'card', 'condition_notes' => 'Valid ACR, visa expired', 'price' => 6875],
                    ['extension_label' => '2nd Extension', 'duration_label' => '1 month', 'process_type' => 'regular', 'payment_method' => 'card', 'condition_notes' => 'Valid ACR', 'price' => 2860],
                    ['extension_label' => '2nd Extension', 'duration_label' => '1 month', 'process_type' => 'express', 'payment_method' => 'card', 'condition_notes' => 'Valid ACR', 'price' => 5304],
                    ['extension_label' => '2nd Extension', 'duration_label' => '1 month', 'process_type' => 'express', 'payment_method' => 'card', 'condition_notes' => 'Valid ACR, visa expired', 'price' => 6355],
                    ['extension_label' => '2nd Extension', 'duration_label' => '2 months', 'process_type' => 'express', 'payment_method' => 'card', 'condition_notes' => 'Plus I-Card NEW', 'price' => 10816],
                    ['extension_label' => '2nd Extension', 'duration_label' => '2 months', 'process_type' => 'express', 'payment_method' => 'card', 'condition_notes' => 'Plus I-Card NEW, visa expired', 'price' => 12355],
                    ['extension_label' => '2nd Extension', 'duration_label' => '1 month', 'process_type' => 'express', 'payment_method' => 'card', 'condition_notes' => 'Plus I-Card NEW', 'price' => 10400],
                    ['extension_label' => '2nd Extension', 'duration_label' => '1 month', 'process_type' => 'express', 'payment_method' => 'card', 'condition_notes' => 'Plus I-Card NEW, visa expired', 'price' => 11835],
                    ['extension_label' => '2nd Extension', 'duration_label' => '2 months', 'process_type' => 'express', 'payment_method' => 'card', 'condition_notes' => 'Plus I-Card RENEWAL', 'price' => 9880],
                    ['extension_label' => '2nd Extension', 'duration_label' => '2 months', 'process_type' => 'express', 'payment_method' => 'card', 'condition_notes' => 'Plus I-Card RENEWAL, visa expired', 'price' => 11107],
                    ['extension_label' => '2nd Extension', 'duration_label' => '1 month', 'process_type' => 'express', 'payment_method' => 'card', 'condition_notes' => 'Plus I-Card RENEWAL', 'price' => 9360],
                    ['extension_label' => '2nd Extension', 'duration_label' => '1 month', 'process_type' => 'express', 'payment_method' => 'card', 'condition_notes' => 'Plus I-Card RENEWAL, visa expired', 'price' => 10588],

                    // 3rd Extension - card
                    ['extension_label' => '3rd Extension', 'duration_label' => '2 months', 'process_type' => 'regular', 'payment_method' => 'card', 'condition_notes' => null, 'price' => 2840],
                    ['extension_label' => '3rd Extension', 'duration_label' => '2 months', 'process_type' => 'express', 'payment_method' => 'card', 'condition_notes' => null, 'price' => 5075],
                    ['extension_label' => '3rd Extension', 'duration_label' => '2 months', 'process_type' => 'express', 'payment_method' => 'card', 'condition_notes' => 'Visa expired', 'price' => 6126],
                    ['extension_label' => '3rd Extension', 'duration_label' => '1 month', 'process_type' => 'regular', 'payment_method' => 'card', 'condition_notes' => null, 'price' => 2320],
                    ['extension_label' => '3rd Extension', 'duration_label' => '1 month', 'process_type' => 'express', 'payment_method' => 'card', 'condition_notes' => null, 'price' => 4555],
                    ['extension_label' => '3rd Extension', 'duration_label' => '1 month', 'process_type' => 'express', 'payment_method' => 'card', 'condition_notes' => 'Visa expired', 'price' => 5606],
                    ['extension_label' => '3rd Extension', 'duration_label' => '2 months', 'process_type' => 'express', 'payment_method' => 'card', 'condition_notes' => 'Plus I-Card renewal', 'price' => 9360],
                    ['extension_label' => '3rd Extension', 'duration_label' => '2 months', 'process_type' => 'express', 'payment_method' => 'card', 'condition_notes' => 'Plus I-Card renewal, visa expired', 'price' => 10400],
                    ['extension_label' => '3rd Extension', 'duration_label' => '1 month', 'process_type' => 'express', 'payment_method' => 'card', 'condition_notes' => 'Plus I-Card renewal', 'price' => 8840],
                    ['extension_label' => '3rd Extension', 'duration_label' => '1 month', 'process_type' => 'express', 'payment_method' => 'card', 'condition_notes' => 'Plus I-Card renewal, visa expired', 'price' => 9880],

                    // 4th Extension - card
                    ['extension_label' => '4th Extension', 'duration_label' => '2 months', 'process_type' => 'regular', 'payment_method' => 'card', 'condition_notes' => 'Plus CRTV', 'price' => 4306],
                    ['extension_label' => '4th Extension', 'duration_label' => '2 months', 'process_type' => 'express', 'payment_method' => 'card', 'condition_notes' => 'Plus CRTV', 'price' => 6542],
                    ['extension_label' => '4th Extension', 'duration_label' => '2 months', 'process_type' => 'express', 'payment_method' => 'card', 'condition_notes' => 'Plus CRTV, visa expired', 'price' => 7592],
                    ['extension_label' => '4th Extension', 'duration_label' => '1 month', 'process_type' => 'regular', 'payment_method' => 'card', 'condition_notes' => 'Plus CRTV', 'price' => 3786],
                    ['extension_label' => '4th Extension', 'duration_label' => '1 month', 'process_type' => 'express', 'payment_method' => 'card', 'condition_notes' => 'Plus CRTV', 'price' => 6022],
                    ['extension_label' => '4th Extension', 'duration_label' => '1 month', 'process_type' => 'express', 'payment_method' => 'card', 'condition_notes' => 'Plus CRTV, visa expired', 'price' => 7072],
                ],
            ],
            [
                'slug' => 'crtv',
                'name' => 'Certificate of Residence for Temporary Visitors (CRTV)',
                'description' => 'Required once a client\'s stay in the Philippines exceeds 6 months.',
                'icon' => 'file-badge',
                'processing_time' => '7-10 working days (regular) / 1 day (express)',
                'sort_order' => 2,
                'requirements' => [
                    ['type' => 'note', 'label' => 'Required if the client exceeds more than 6 months of stay in the Philippines.'],
                    ['type' => 'note', 'needs_review' => true, 'label' => 'PENDING CONFIRMATION - the source sheet shows a "1410" figure beside this heading that does not match any line item below. Confirm what it refers to before publishing.'],
                ],
                'tiers' => [
                    ['duration_label' => '2 months', 'process_type' => 'regular', 'payment_method' => 'cash', 'condition_notes' => null, 'price' => 4140],
                    ['duration_label' => '2 months', 'process_type' => 'express', 'payment_method' => 'cash', 'condition_notes' => null, 'price' => 6290],
                    ['duration_label' => '2 months', 'process_type' => 'express', 'payment_method' => 'cash', 'condition_notes' => 'Visa already expired', 'price' => 7300],
                    ['duration_label' => '1 month', 'process_type' => 'regular', 'payment_method' => 'cash', 'condition_notes' => null, 'price' => 3640],
                    ['duration_label' => '1 month', 'process_type' => 'express', 'payment_method' => 'cash', 'condition_notes' => null, 'price' => 5790],
                    ['duration_label' => '1 month', 'process_type' => 'express', 'payment_method' => 'cash', 'condition_notes' => 'Visa already expired', 'price' => 6800],
                ],
            ],
            [
                'slug' => 'hongkong-9g',
                'name' => 'Hong Kong Passport Holder - 1st Extension',
                'description' => 'First extension for Hong Kong (Chinese) passport holders on a 9(g) visa. Always processed express.',
                'icon' => 'plane',
                'processing_time' => '1 day (always express)',
                'sort_order' => 3,
                'requirements' => [
                    ['type' => 'note', 'label' => 'Applies to Hong Kong passport holders (Chinese nationals) on a 9(g) visa. Both durations are always processed express.'],
                ],
                'tiers' => [
                    ['extension_label' => '1st Extension', 'duration_label' => '7 days', 'process_type' => 'express', 'payment_method' => 'cash', 'condition_notes' => 'Hong Kong passport holder, 9(g) visa', 'price' => 4380],
                    ['extension_label' => '1st Extension', 'duration_label' => '38 days', 'process_type' => 'express', 'payment_method' => 'cash', 'condition_notes' => 'Hong Kong passport holder, 9(g) visa', 'price' => 5090],
                ],
            ],
            [
                'slug' => 'exit-clearance',
                'name' => 'Exit Clearance (ECC)',
                'description' => 'Clearance required before leaving the Philippines after a stay of more than 6 months. Start the application 2 weeks before the departure date.',
                'icon' => 'plane-takeoff',
                'processing_time' => '1 day (processed same day)',
                'sort_order' => 4,
                'requirements' => [
                    ['label' => 'Fill up the Exit Clearance form'],
                    ['label' => '4 pcs 2x2 photo, white background, no eyeglasses'],
                    ['label' => 'Copy of the client\'s ticket'],
                    ['label' => 'Photocopy of passport biopage'],
                    ['label' => 'Photocopy of latest arrival stamp'],
                    ['label' => 'Photocopy of last extension receipt'],
                    ['label' => 'Photocopy of ACR I-Card (front and back)'],
                    ['type' => 'note', 'label' => 'Required if the client has stayed or exceeded more than 6 months and wants to leave the country.'],
                    ['type' => 'note', 'label' => 'Must be started 2 weeks before the departure date. The clearance itself is a 1-day process and is completed the same day.'],
                    ['type' => 'note', 'label' => 'If it is the client\'s first time exceeding 6 months, they must personally appear at Immigration for biometrics.'],
                    // Spelling confirmed against the AMEGA Client Information Sheet, which carries an "SSRN #" field.
                    ['type' => 'note', 'label' => 'No personal appearance is required if the client already has an SSRN number on file.'],
                ],
                'tiers' => [
                    ['duration_label' => null, 'process_type' => 'express', 'payment_method' => 'cash', 'condition_notes' => 'Exit Clearance, processed same day', 'price' => 4000, 'processing_time' => '1 day'],
                ],
            ],
            [
                'slug' => 're-stamping',
                'name' => 'Re-Stamping',
                'description' => 'Transfers an old visa stamp into a new passport.',
                'icon' => 'stamp',
                'processing_time' => '7-10 working days',
                'sort_order' => 5,
                'requirements' => [
                    ['label' => 'Re-Stamping form'],
                    ['label' => 'Old and new passport'],
                    ['label' => 'Latest extension receipt - optional if on a tourist visa'],
                ],
                'tiers' => [
                    ['duration_label' => null, 'process_type' => 'regular', 'payment_method' => 'cash', 'condition_notes' => 'Transfer of old visa stamp to a new passport', 'price' => 3800, 'needs_review' => true, 'is_active' => false],
                ],
            ],
        ];
    }
}
