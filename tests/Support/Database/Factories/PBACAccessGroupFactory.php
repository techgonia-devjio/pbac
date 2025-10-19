<?php

namespace Pbac\Tests\Support\Database\Factories;


use Illuminate\Database\Eloquent\Factories\Factory;
use Pbac\Models\PBACAccessGroup;

class PBACAccessGroupFactory extends Factory
{
    protected $model = PBACAccessGroup::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->word . ' Group',
            'description' => $this->faker->sentence,
        ];
    }
}
