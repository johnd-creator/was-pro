<?php

namespace Database\Factories;

use App\Models\WasteCharacteristic;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\WasteCharacteristic>
 */
class WasteCharacteristicFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = WasteCharacteristic::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->randomElement([
            'Flammable', 'Corrosive', 'Reactive', 'Toxic', 'Non-Hazardous',
            'Infectious', 'Oxidizing', 'Explosive', 'Radioactive', 'Compressible',
        ]);

        return [
            'name' => $name,
            'code' => strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $name), 0, 3)).fake()->unique()->randomNumber(3),
            'description' => fake()->sentence(),
            'is_hazardous' => fake()->boolean(30), // 30% chance of being hazardous
            'is_active' => true,
        ];
    }

    /**
     * Indicate that the characteristic is hazardous.
     */
    public function hazardous(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_hazardous' => true,
        ]);
    }

    /**
     * Indicate that the characteristic is non-hazardous.
     */
    public function nonHazardous(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_hazardous' => false,
        ]);
    }

    /**
     * Indicate that the characteristic is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
