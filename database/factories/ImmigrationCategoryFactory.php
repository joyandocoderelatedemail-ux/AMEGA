<?php

namespace Database\Factories;

use App\Models\ImmigrationCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ImmigrationCategory>
 */
class ImmigrationCategoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);

        return [
            'slug' => Str::slug($name),
            'name' => Str::title($name),
            'description' => fake()->sentence(),
            'icon' => 'stamp',
            'processing_time' => '7-10 working days',
            'sort_order' => fake()->numberBetween(1, 20),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => ['is_active' => false]);
    }
}
