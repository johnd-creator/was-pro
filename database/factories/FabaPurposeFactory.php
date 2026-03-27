<?php

namespace Database\Factories;

use App\Models\FabaPurpose;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\FabaPurpose>
 */
class FabaPurposeFactory extends Factory
{
    protected $model = FabaPurpose::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'name' => Str::title($name),
            'slug' => Str::slug($name),
            'description' => fake()->optional()->sentence(),
            'is_active' => true,
        ];
    }
}
