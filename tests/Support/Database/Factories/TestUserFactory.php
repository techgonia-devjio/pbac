<?php

namespace Modules\Pbac\Tests\Support\Database\Factories;


use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Pbac\Tests\Support\Models\TestUser;

class TestUserFactory extends Factory
{
    protected $model = TestUser::class;

    public function definition(): array
    {
        return [
            'email' => $this->faker->unique()->safeEmail,
            'password' => bcrypt('password'),
            'is_super_admin' => false,
        ];
    }

    public function superAdmin()
    {
        return $this->state(fn (array $attributes) => [
            'is_super_admin' => true,
        ]);
    }

    public function forSuperAdmin()
    {
        return $this->state(fn (array $attributes) => [
            'is_super_admin' => true,
        ]);
    }
}
