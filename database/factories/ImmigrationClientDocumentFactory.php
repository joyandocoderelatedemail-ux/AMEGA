<?php

namespace Database\Factories;

use App\Models\ImmigrationClient;
use App\Models\ImmigrationClientDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ImmigrationClientDocument>
 */
class ImmigrationClientDocumentFactory extends Factory
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
            'document_type' => fake()->randomElement(array_keys(ImmigrationClientDocument::TYPES)),
            'reference_number' => strtoupper(fake()->bothify('??-######')),
            'date_paid' => fake()->dateTimeBetween('-2 years', 'now')->format('Y-m-d'),
            'ssrn_number' => fake()->numerify('SSRN-######'),
            'validity' => fake()->dateTimeBetween('now', '+2 years')->format('M j, Y'),
        ];
    }
}
