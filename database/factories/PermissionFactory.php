<?php

namespace Database\Factories;

use App\Models\Permission;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Permission>
 */
class PermissionFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = Permission::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $module = fake()->randomElement(['organizations', 'users', 'waste_records', 'transportation', 'dashboard']);
        $action = fake()->randomElement(['view', 'create', 'edit', 'delete', 'approve', 'reject']);

        return [
            'name' => ucfirst($action).' '.str_replace('_', ' ', $module),
            'slug' => $module.'.'.$action.'_'.fake()->unique()->randomNumber(3),
            'module' => $module,
            'description' => fake()->sentence(),
            'is_active' => true,
        ];
    }

    /**
     * Indicate that the permission is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
