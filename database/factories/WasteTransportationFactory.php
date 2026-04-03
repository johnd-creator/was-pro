<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Vendor;
use App\Models\WasteRecord;
use App\Models\WasteTransportation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\WasteTransportation>
 */
class WasteTransportationFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = WasteTransportation::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $organization = auth()->user()?->organization;
        $prefix = 'TR-'.($organization?->code ?? 'TEST').'-'.now()->format('Y-m');

        return [
            'transportation_number' => $prefix.'-'.str_pad(fake()->unique()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT),
            'waste_record_id' => WasteRecord::factory(),
            'vendor_id' => Vendor::factory(),
            'transportation_date' => fake()->dateTimeBetween('-30 days', 'now')->format('Y-m-d'),
            'quantity' => fake()->randomFloat(2, 10, 500),
            'unit' => fake()->randomElement(['kg', 'ton', 'lb']),
            'vehicle_number' => fake()->bothify('?? ####'),
            'driver_name' => fake()->name(),
            'driver_phone' => fake()->phoneNumber(),
            'status' => fake()->randomElement(['pending', 'in_transit', 'delivered', 'cancelled']),
            'notes' => fake()->optional()->sentence(),
            'delivery_notes' => fake()->optional()->sentence(),
            'dispatched_at' => fake()->optional()->dateTimeBetween('-30 days', 'now'),
            'delivered_at' => fake()->optional()->dateTimeBetween('-30 days', 'now'),
            'created_by' => User::factory(),
            'updated_by' => User::factory(),
        ];
    }

    /**
     * Indicate that the transportation is pending.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'dispatched_at' => null,
            'delivered_at' => null,
        ]);
    }

    /**
     * Indicate that the transportation is in transit.
     */
    public function inTransit(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'in_transit',
            'dispatched_at' => now()->subHours(fake()->numberBetween(1, 24)),
            'delivered_at' => null,
        ]);
    }

    /**
     * Indicate that the transportation is delivered.
     */
    public function delivered(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'delivered',
            'dispatched_at' => now()->subDays(fake()->numberBetween(1, 5)),
            'delivered_at' => now()->subDays(fake()->numberBetween(0, 4)),
        ]);
    }

    /**
     * Indicate that the transportation is cancelled.
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'cancelled',
            'dispatched_at' => null,
            'delivered_at' => null,
        ]);
    }
}
