<?php

namespace Modules\Pbac\Tests\Support\Database\Factories;


use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Arr;
use Modules\Pbac\Models\PBACAccessControl;
use Modules\Pbac\Models\PBACAccessGroup;
use Modules\Pbac\Models\PBACAccessResource;
use Modules\Pbac\Models\PBACAccessTarget;
use Modules\Pbac\Models\PBACAccessTeam;
use Modules\Pbac\Tests\Support\Models\TestUser;

class PBACAccessControlFactory extends Factory
{
    protected $model = PBACAccessControl::class;

    public function definition(): array
    {
        return [
            'pbac_access_target_id' => PBACAccessTarget::factory(),
            'target_id' => null,
            'pbac_access_resource_id' => PBACAccessResource::factory(),
            'resource_id' => null,
            'action' => $this->faker->randomElement(['view', 'create', 'update', 'delete']),
            'effect' => $this->faker->randomElement(['allow', 'deny']),
            'extras' => null,
            'priority' => 0,
        ];
    }

    public function allow(): PBACAccessControlFactory
    {
        return $this->state(fn (array $attributes) => [
            'effect' => 'allow',
        ]);
    }

    public function deny(): PBACAccessControlFactory
    {
        return $this->state(fn (array $attributes) => [
            'effect' => 'deny',
        ]);
    }

    public function forUser(TestUser $user): PBACAccessControlFactory
    {
        return $this->forTarget(get_class($user), $user->id);
    }

    public function forGroup(PBACAccessGroup $group): PBACAccessControlFactory
    {
        return $this->forTarget(PBACAccessGroup::class, $group->id);
    }

    public function forTeam(PBACAccessTeam $team): PBACAccessControlFactory
    {
        return $this->forTarget(PBACAccessTeam::class, $team->id);
    }

    public function forTarget(string $targetType, ?int $targetId = null): PBACAccessControlFactory
    {
        return $this->state(function (array $attributes) use ($targetType, $targetId) {
            $target = PBACAccessTarget::firstOrCreate(['type' => $targetType]);
            return [
                'pbac_access_target_id' => $target->id,
                'target_id' => $targetId,
            ];
        });
    }

    public function forResource(string $resourceType, ?int $resourceId = null): PBACAccessControlFactory
    {
        return $this->state(function (array $attributes) use ($resourceType, $resourceId) {
            $resource = PBACAccessResource::firstOrCreate(['type' => $resourceType]);
            return [
                'pbac_access_resource_id' => $resource->id,
                'resource_id' => $resourceId,
            ];
        });
    }

    public function withAction(array|string $action): PBACAccessControlFactory
    {
        return $this->state(fn (array $attributes) => [
            'action' => Arr::wrap($action), // Ensure it's a single action
        ]);
    }

    public function withPriority(int $priority): PBACAccessControlFactory
    {
        return $this->state(fn (array $attributes) => [
            'priority' => $priority,
        ]);
    }
}
