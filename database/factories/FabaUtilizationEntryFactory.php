<?php

namespace Database\Factories;

use App\Models\FabaUtilizationEntry;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\FabaUtilizationEntry>
 */
class FabaUtilizationEntryFactory extends Factory
{
    protected $model = FabaUtilizationEntry::class;

    public function definition(): array
    {
        $type = fake()->randomElement([
            FabaUtilizationEntry::TYPE_EXTERNAL,
            FabaUtilizationEntry::TYPE_INTERNAL,
        ]);

        return [
            'entry_number' => 'FU-'.now()->format('Ym').'-'.str_pad((string) fake()->unique()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT),
            'transaction_date' => fake()->dateTimeBetween('-60 days', 'today')->format('Y-m-d'),
            'material_type' => fake()->randomElement([
                FabaUtilizationEntry::MATERIAL_FLY_ASH,
                FabaUtilizationEntry::MATERIAL_BOTTOM_ASH,
            ]),
            'utilization_type' => $type,
            'vendor_id' => $type === FabaUtilizationEntry::TYPE_EXTERNAL ? Vendor::factory() : null,
            'quantity' => fake()->randomFloat(2, 1, 500),
            'unit' => 'ton',
            'document_number' => fake()->optional()->bothify('DOC-####'),
            'document_date' => fake()->optional()->dateTimeBetween('-60 days', 'today')?->format('Y-m-d'),
            'attachment_path' => null,
            'note' => fake()->optional()->sentence(),
            'created_by' => null,
            'updated_by' => null,
        ];
    }
}
