<?php

namespace Database\Factories;

use App\Models\ImmigrationClient;
use App\Models\ImmigrationClientExtension;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ImmigrationClientExtension>
 */
class ImmigrationClientExtensionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'immigration_client_id' => ImmigrationClient::factory(),
            'sequence' => 1,
            'soa_or_number' => strtoupper(fake()->bothify('OR-#####')),
            'extension_date' => fake()->dateTimeBetween('-1 year', 'now')->format('Y-m-d'),
            'details' => fake()->randomElement([
                '2 months extension, valid ACR I-Card',
                '1 month extension, express',
                'Visa waiver, 29 days',
            ]),
            'amount_paid' => fake()->randomFloat(2, 2000, 12000),
            'annual_report' => null,
            'refund' => null,
        ];
    }
}
