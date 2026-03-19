<?php

namespace Database\Factories;

use App\Models\WasteCategory;
use App\Models\WasteCharacteristic;
use App\Models\WasteType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\WasteType>
 */
class WasteTypeFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = WasteType::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $names = [
            'Mixed Paper', 'Cardboard', 'Plastic Bottles', 'Aluminum Cans',
            'Glass Bottles', 'Food Scraps', 'Yard Waste', 'Batteries',
            'Electronics', 'Motor Oil', 'Paint', 'Pesticides',
            'Medical Waste', 'Construction Debris', 'Tires', 'Appliances',
        ];

        $name = fake()->randomElement($names);

        return [
            'name' => $name,
            'code' => strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $name), 0, 5)).fake()->unique()->randomNumber(2),
            'category_id' => WasteCategory::factory(),
            'characteristic_id' => WasteCharacteristic::factory(),
            'description' => fake()->sentence(),
            'storage_period_days' => fake()->numberBetween(1, 365),
            'transport_cost' => fake()->randomFloat(2, 10, 1000),
            'is_active' => true,
        ];
    }

    /**
     * Indicate that the waste type is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
