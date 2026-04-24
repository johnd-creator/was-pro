<?php

namespace Database\Factories;

use App\Models\FabaInternalDestination;
use App\Models\FabaMovement;
use App\Models\FabaPurpose;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\FabaMovement>
 */
class FabaMovementFactory extends Factory
{
    protected $model = FabaMovement::class;

    public function definition(): array
    {
        $transactionDate = fake()->dateTimeBetween('-60 days', 'today');
        $movementType = fake()->randomElement([
            FabaMovement::TYPE_PRODUCTION,
            FabaMovement::TYPE_UTILIZATION_EXTERNAL,
            FabaMovement::TYPE_UTILIZATION_INTERNAL,
            FabaMovement::TYPE_REJECT,
        ]);
        $isExternal = $movementType === FabaMovement::TYPE_UTILIZATION_EXTERNAL;
        $isInternal = $movementType === FabaMovement::TYPE_UTILIZATION_INTERNAL;

        return [
            'transaction_date' => $transactionDate->format('Y-m-d'),
            'material_type' => fake()->randomElement(['fly_ash', 'bottom_ash']),
            'movement_type' => $movementType,
            'stock_effect' => in_array($movementType, [FabaMovement::TYPE_PRODUCTION], true)
                ? FabaMovement::STOCK_EFFECT_IN
                : FabaMovement::STOCK_EFFECT_OUT,
            'quantity' => fake()->randomFloat(2, 1, 500),
            'unit' => 'ton',
            'vendor_id' => $isExternal ? Vendor::factory() : null,
            'internal_destination_id' => $isInternal ? FabaInternalDestination::factory() : null,
            'purpose_id' => fake()->boolean(50) ? FabaPurpose::factory() : null,
            'document_number' => $isExternal ? fake()->bothify('DOC-####') : null,
            'document_date' => $isExternal ? $transactionDate->format('Y-m-d') : null,
            'attachment_path' => null,
            'reference_type' => null,
            'reference_id' => null,
            'period_year' => (int) $transactionDate->format('Y'),
            'period_month' => (int) $transactionDate->format('n'),
            'approval_status' => FabaMovement::STATUS_APPROVED,
            'submitted_by' => null,
            'submitted_at' => null,
            'approved_by' => null,
            'approved_at' => null,
            'rejected_by' => null,
            'rejected_at' => null,
            'rejection_note' => null,
            'created_by' => null,
            'updated_by' => null,
            'note' => fake()->optional()->sentence(),
        ];
    }
}
