<?php

namespace Database\Factories;

use App\Models\FabaMonthlyApproval;
use App\Models\FabaMonthlyClosingSnapshot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\FabaMonthlyClosingSnapshot>
 */
class FabaMonthlyClosingSnapshotFactory extends Factory
{
    protected $model = FabaMonthlyClosingSnapshot::class;

    public function definition(): array
    {
        $year = fake()->numberBetween(2024, 2027);
        $month = fake()->numberBetween(1, 12);

        return [
            'year' => $year,
            'month' => $month,
            'status' => FabaMonthlyApproval::STATUS_APPROVED,
            'approved_by' => null,
            'approved_at' => now(),
            'snapshot_payload' => [
                'period_label' => sprintf('%02d/%04d', $month, $year),
            ],
            'warning_summary' => [],
        ];
    }
}
