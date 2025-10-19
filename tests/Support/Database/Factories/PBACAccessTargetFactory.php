<?php

namespace Pbac\Tests\Support\Database\Factories;


use Illuminate\Database\Eloquent\Factories\Factory;
use Pbac\Models\PBACAccessTarget;

class PBACAccessTargetFactory extends Factory
{
    protected $model = PBACAccessTarget::class;

    public function definition(): array
    {
        return [
            'type' => $this->faker->unique()->word . 'Target', // e.g., 'App\Models\User'
            'description' => $this->faker->sentence,
            'is_active' => true,
        ];
    }

    public function inactive()
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
