<?php

namespace Database\Factories;

use App\Models\ImmigrationCategory;
use App\Models\ImmigrationRequirement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ImmigrationRequirement>
 */
class ImmigrationRequirementFactory extends Factory
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
            'label' => fake()->sentence(),
            'type' => 'requirement',
            'needs_review' => false,
            'sort_order' => fake()->numberBetween(1, 10),
        ];
    }

    public function note(): static
    {
        return $this->state(fn (array $attributes): array => ['type' => 'note']);
    }

    public function needsReview(): static
    {
        return $this->state(fn (array $attributes): array => ['needs_review' => true]);
    }
}
