<?php

namespace Database\Factories;

use App\Models\FabaAuditLog;
use Illuminate\Database\Eloquent\Factories\Factory;

class FabaAuditLogFactory extends Factory
{
    protected $model = FabaAuditLog::class;

    public function definition(): array
    {
        return [
            'actor_id' => null,
            'action' => fake()->randomElement(['create', 'update', 'submit', 'approve', 'reject', 'reopen']),
            'module' => fake()->randomElement([
                FabaAuditLog::MODULE_PRODUCTION,
                FabaAuditLog::MODULE_UTILIZATION,
                FabaAuditLog::MODULE_APPROVAL,
                FabaAuditLog::MODULE_BALANCE,
            ]),
            'reference_type' => null,
            'reference_id' => null,
            'year' => (int) now()->format('Y'),
            'month' => fake()->numberBetween(1, 12),
            'summary' => fake()->sentence(),
            'details' => ['note' => fake()->sentence()],
        ];
    }
}
