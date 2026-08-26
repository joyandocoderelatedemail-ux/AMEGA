<?php

namespace Database\Factories;

use App\Models\ImmigrationClient;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ImmigrationClient>
 */
class ImmigrationClientFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'last_name' => fake()->lastName(),
            'given_name' => fake()->firstName(),
            'address' => fake()->address(),
            'email' => fake()->safeEmail(),
            'mobile_number' => '+63 9'.fake()->numerify('## ### ####'),
            'height' => fake()->numberBetween(150, 190).' cm',
            'weight' => fake()->numberBetween(45, 100).' kg',
            'civil_status' => fake()->randomElement(['Single', 'Married', 'Widowed']),
            'nationality' => fake()->randomElement(['American', 'Korean', 'Chinese', 'British', 'Indian']),
            'date_of_birth' => fake()->dateTimeBetween('-70 years', '-20 years')->format('Y-m-d'),
            'passport_number' => strtoupper(Str::random(2)).fake()->unique()->numerify('#######'),
        ];
    }

    public function withoutPassport(): static
    {
        return $this->state(fn (array $attributes): array => ['passport_number' => null]);
    }
}
