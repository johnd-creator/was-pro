<?php

namespace Database\Factories;

use App\Models\WasteRecord;
use App\Models\WasteType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\WasteRecord>
 */
class WasteRecordFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = WasteRecord::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $organization = auth()->user()?->organization;
        $prefix = 'WR-'.($organization?->code ?? 'TEST').'-'.now()->format('Y-m');

        return [
            'record_number' => $prefix.'-'.str_pad(fake()->unique()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT),
            'date' => fake()->dateTimeBetween('-30 days', 'today')->format('Y-m-d'),
            'waste_type_id' => WasteType::factory(),
            'quantity' => fake()->randomFloat(2, 1, 1000),
            'unit' => fake()->randomElement(['kg', 'ton', 'lb', 'g']),
            'source' => fake()->randomElement(['Kitchen', 'Production Area', 'Warehouse', 'Office', 'Cafeteria']),
            'description' => fake()->optional()->sentence(),
            'notes' => fake()->optional()->sentence(),
            'status' => fake()->randomElement(['draft', 'pending_review', 'approved', 'rejected']),
            'rejection_reason' => fake()->optional()->sentence(),
            'created_by' => null,
            'updated_by' => null,
            'submitted_by' => null,
            'submitted_at' => fake()->optional()->dateTimeBetween('-30 days', 'now'),
            'approved_by' => null,
            'approved_at' => fake()->optional()->dateTimeBetween('-30 days', 'now'),
            'approval_notes' => fake()->optional()->sentence(),
        ];
    }

    /**
     * Indicate that the record is in draft status.
     */
    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'draft',
            'submitted_by' => null,
            'submitted_at' => null,
            'approved_by' => null,
            'approved_at' => null,
        ]);
    }

    /**
     * Indicate that the record is pending review.
     */
    public function pendingReview(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending_review',
            'submitted_by' => null,
            'submitted_at' => now(),
            'approved_by' => null,
            'approved_at' => null,
        ]);
    }

    /**
     * Indicate that the record is approved.
     */
    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'approved',
            'submitted_by' => null,
            'submitted_at' => now()->subDay(),
            'approved_by' => null,
            'approved_at' => now(),
        ]);
    }

    /**
     * Indicate that the record is rejected.
     */
    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'rejected',
            'submitted_by' => null,
            'submitted_at' => now()->subDay(),
            'approved_by' => null,
            'approved_at' => now(),
            'rejection_reason' => fake()->sentence(),
        ]);
    }
}
