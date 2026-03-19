<?php

namespace Database\Factories;

use App\Models\FabaMonthlyApproval;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\FabaMonthlyApproval>
 */
class FabaMonthlyApprovalFactory extends Factory
{
    protected $model = FabaMonthlyApproval::class;

    public function definition(): array
    {
        return [
            'year' => (int) now()->format('Y'),
            'month' => fake()->numberBetween(1, 12),
            'status' => fake()->randomElement([
                FabaMonthlyApproval::STATUS_DRAFT,
                FabaMonthlyApproval::STATUS_SUBMITTED,
                FabaMonthlyApproval::STATUS_APPROVED,
                FabaMonthlyApproval::STATUS_REJECTED,
            ]),
            'submitted_by' => null,
            'submitted_at' => null,
            'approved_by' => null,
            'approved_at' => null,
            'rejected_by' => null,
            'rejected_at' => null,
            'rejection_note' => null,
        ];
    }
}
