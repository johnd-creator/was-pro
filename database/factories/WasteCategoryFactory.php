<?php

namespace Database\Factories;

use App\Models\WasteCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\WasteCategory>
 */
class WasteCategoryFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = WasteCategory::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->randomElement([
            'Organic Waste', 'Plastic Waste', 'Paper Waste', 'Metal Waste',
            'Electronic Waste', 'Chemical Waste', 'Medical Waste', 'Glass Waste',
            'Textile Waste', 'Rubber Waste', 'Construction Waste', 'Food Waste',
        ]);

        return [
            'name' => $name,
            'code' => strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $name), 0, 3)).fake()->unique()->randomNumber(3),
            'description' => fake()->sentence(),
            'is_active' => true,
        ];
    }

    /**
     * Indicate that the category is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
