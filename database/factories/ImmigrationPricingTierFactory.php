<?php

namespace Database\Factories;

use App\Models\ImmigrationCategory;
use App\Models\ImmigrationPricingTier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ImmigrationPricingTier>
 */
class ImmigrationPricingTierFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'immigration_category_id' => ImmigrationCategory::factory(),
            'extension_label' => fake()->randomElement(['1st Extension', '2nd Extension', '3rd Extension']),
            'duration_label' => fake()->randomElement(['1 month', '2 months']),
            'process_type' => fake()->randomElement(ImmigrationPricingTier::PROCESS_TYPES),
            'payment_method' => fake()->randomElement(ImmigrationPricingTier::PAYMENT_METHODS),
            'condition_notes' => fake()->sentence(4),
            'price' => fake()->randomFloat(2, 2000, 12000),
            'processing_time' => '7-10 working days',
            'needs_review' => false,
            'is_active' => true,
            'sort_order' => fake()->numberBetween(1, 50),
        ];
    }

    public function needsReview(): static
    {
        return $this->state(fn (array $attributes): array => [
            'needs_review' => true,
            'is_active' => false,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => ['is_active' => false]);
    }
}
