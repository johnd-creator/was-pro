<?php

namespace Database\Factories;

use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Vendor>
 */
class VendorFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = Vendor::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $companyName = fake()->company();

        return [
            'name' => $companyName,
            'code' => strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $companyName), 0, 6)).fake()->unique()->randomNumber(2),
            'description' => fake()->sentence(),
            'contact_person' => fake()->name(),
            'phone' => fake()->phoneNumber(),
            'email' => fake()->companyEmail(),
            'address' => fake()->address(),
            'license_number' => 'LIC-'.fake()->unique()->numerify('########'),
            'license_expiry_date' => fake()->dateTimeBetween('now', '+5 years'),
            'is_active' => true,
        ];
    }

    /**
     * Indicate that the vendor is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Indicate that the vendor has an expired license.
     */
    public function withExpiredLicense(): static
    {
        return $this->state(fn (array $attributes) => [
            'license_expiry_date' => fake()->dateTimeBetween('-5 years', 'now'),
        ]);
    }
}
