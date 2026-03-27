<?php

namespace Database\Factories;

use App\Models\FabaMovement;
use App\Models\FabaOpeningBalance;
use Illuminate\Database\Eloquent\Factories\Factory;

class FabaOpeningBalanceFactory extends Factory
{
    protected $model = FabaOpeningBalance::class;

    public function definition(): array
    {
        return [
            'year' => (int) now()->format('Y'),
            'month' => fake()->numberBetween(1, 12),
            'material_type' => fake()->randomElement([
                FabaMovement::MATERIAL_FLY_ASH,
                FabaMovement::MATERIAL_BOTTOM_ASH,
            ]),
            'quantity' => fake()->randomFloat(2, 0, 1000),
            'note' => fake()->sentence(),
            'set_by' => null,
            'set_at' => now(),
        ];
    }
}
