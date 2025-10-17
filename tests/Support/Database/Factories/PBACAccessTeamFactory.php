<?php

namespace Modules\Pbac\Tests\Support\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Pbac\Models\PBACAccessTeam;
use Modules\Pbac\Tests\Support\Models\TestUser;

class PBACAccessTeamFactory extends Factory
{
    protected $model = PBACAccessTeam::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->word . ' Team',
            'description' => $this->faker->sentence,
            //'owner_id' => //TestUser::factory(), // Assign an owner from TestUser
        ];
    }

    public function forOwner(TestUser $owner)
    {
        return $this->state(fn (array $attributes) => [
            'owner_id' => $owner->id,
        ]);
    }
}
