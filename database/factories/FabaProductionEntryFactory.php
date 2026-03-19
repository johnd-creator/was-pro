<?php

namespace Database\Factories;

use App\Models\FabaProductionEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\FabaProductionEntry>
 */
class FabaProductionEntryFactory extends Factory
{
    protected $model = FabaProductionEntry::class;

    public function definition(): array
    {
        $date = fake()->dateTimeBetween('-60 days', 'today');

        return [
            'entry_number' => 'FP-'.now()->format('Ym').'-'.str_pad((string) fake()->unique()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT),
            'transaction_date' => $date->format('Y-m-d'),
            'material_type' => fake()->randomElement([
                FabaProductionEntry::MATERIAL_FLY_ASH,
                FabaProductionEntry::MATERIAL_BOTTOM_ASH,
            ]),
            'entry_type' => fake()->randomElement([
                FabaProductionEntry::TYPE_PRODUCTION,
                FabaProductionEntry::TYPE_POK,
                FabaProductionEntry::TYPE_WORKSHOP,
                FabaProductionEntry::TYPE_REJECT,
            ]),
            'quantity' => fake()->randomFloat(2, 1, 500),
            'unit' => 'ton',
            'note' => fake()->optional()->sentence(),
            'created_by' => null,
            'updated_by' => null,
        ];
    }
}
