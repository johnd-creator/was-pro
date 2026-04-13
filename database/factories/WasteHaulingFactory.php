<?php

namespace Database\Factories;

use App\Models\WasteHauling;
use App\Models\WasteRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\WasteHauling>
 */
class WasteHaulingFactory extends Factory
{
    protected $model = WasteHauling::class;

    public function definition(): array
    {
        $organization = auth()->user()?->organization;
        $prefix = 'WH-'.($organization?->code ?? 'TEST').'-'.now()->format('Y-m');

        return [
            'hauling_number' => $prefix.'-'.str_pad((string) fake()->unique()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT),
            'waste_record_id' => WasteRecord::factory(),
            'hauling_date' => fake()->dateTimeBetween('-7 days', '+7 days')->format('Y-m-d'),
            'quantity' => fake()->randomFloat(2, 1, 100),
            'unit' => 'kg',
            'notes' => fake()->optional()->sentence(),
            'status' => 'pending_approval',
            'submitted_by' => null,
            'submitted_at' => now(),
            'approved_by' => null,
            'approved_at' => null,
            'approval_notes' => null,
            'rejection_reason' => null,
            'created_by' => null,
            'updated_by' => null,
        ];
    }

    public function pendingApproval(): static
    {
        return $this->state(fn () => [
            'status' => 'pending_approval',
            'submitted_at' => now(),
            'approved_by' => null,
            'approved_at' => null,
            'rejection_reason' => null,
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn () => [
            'status' => 'approved',
            'submitted_at' => now()->subDay(),
            'approved_at' => now(),
            'rejection_reason' => null,
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn () => [
            'status' => 'rejected',
            'submitted_at' => now()->subDay(),
            'approved_at' => now(),
            'rejection_reason' => fake()->sentence(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => [
            'status' => 'cancelled',
            'approved_at' => null,
            'approval_notes' => null,
            'rejection_reason' => null,
        ]);
    }
}
