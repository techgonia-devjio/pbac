<?php

namespace Pbac\Tests\Unit\Traits;

use Pbac\Models\PBACAccessControl;
use Pbac\Models\PBACAccessGroup;
use Pbac\Models\PBACAccessResource;
use Pbac\Models\PBACAccessTarget;
use Pbac\Tests\Support\Models\PbacAccessControlUser;
use Pbac\Tests\TestCase;

class HasPbacAccessControlTest extends TestCase
{
    use \Pbac\Tests\Support\Traits\MigrationLoader;
    use \Pbac\Tests\Support\Traits\TestUsersWithTraits;

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_can_give_any_access_to_super_admin(): void
    {
        $superAdmin = PbacAccessControlUser::factory()->forSuperAdmin()->create();
        $resource = PBACAccessResource::factory()->create();
        // we have the resource createed but target?
        $rule = PBACAccessControl::factory()->forResource($resource->type, 1)->create([
            'pbac_access_resource_id' => $resource->id,
        ]);
        $actions = [ 'view', 'create', 'update', 'delete', 'list', 'restore', 'forceDelete' ];
        $resources = PBACAccessResource::all()->map(fn ($resource) => $resource->type);
        foreach ($actions as $action) {
            foreach ($resources as $resource) {
                $this->assertTrue($superAdmin->can($action, $resource));
            }
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_denies_access_to_normal_users(): void
    {
        $user = $this->createUserWithPbacTraitsAccessControl(); // a non super admin
        $resource = PBACAccessResource::factory()->create();
        // we have the resource createed but target?
        $rule = PBACAccessControl::factory()->forResource($resource->type, 1)->create([
            'pbac_access_resource_id' => $resource->id,
        ]);
        $actions = [ 'view', 'create', 'update', 'delete', 'list', 'restore', 'forceDelete' ];
        $resources = PBACAccessResource::all()->map(fn ($resource) => $resource->type);
        foreach ($actions as $action) {
            foreach ($resources as $resource) {
                $this->assertFalse($user->can($action, $resource));
            }
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_allows_access_to_any_normal_users_which_resources_are_allowed_and_can_act_only_on_those(): void
    {
        $user = $this->createUserWithPbacTraitsAccessControl(); // a non super admin
        $user2 = $this->createUserWithPbacTraitsAccessControl(); // a non super admin
        $resource = PBACAccessResource::factory()->create();
        $target = PBACAccessTarget::factory()->create(['type' => get_class($user)]);

        // So, current user can access all the resources of that type but only for provided actions
        $rule = PBACAccessControl::factory()->create([
            'pbac_access_resource_id' => $resource->id,
            'resource_id' => null, // by null we mean all resources of that type can be accessed
            'pbac_access_target_id' => $target->id,
            'target_id' => null, // we mean **any** user can access the resource
            'action' => ['view', 'list'], // a user can only view and list
            'effect' => 'allow',
        ]);

        // So, current user can access all the resources of that type but only for provided actions
        $this->assertTrue($user->can('view', $resource->type));
        $this->assertTrue($user->can('list', $resource->type));
        $this->assertTrue($user2->can('view', $resource->type));
        $this->assertTrue($user2->can('list', $resource->type));
        $this->assertFalse($user->can('non-action-defined', $resource->type));
        $this->assertFalse($user2->can('non-action-defined', $resource->type));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_allows_access_to_any_normal_users_with_specified_resource_and_action(): void
    {
        $user = $this->createUserWithPbacTraitsAccessControl(); // a non super admin
        $user2 = $this->createUserWithPbacTraitsAccessControl(); // a non super admin
        $resource = PBACAccessResource::factory()->create();
        $target = PBACAccessTarget::factory()->create(['type' => get_class($user)]);

        $rule = PBACAccessControl::factory()->create([
            'pbac_access_resource_id' => $resource->id,
            'resource_id' => 1, // specific resource_id, users/targets can access only this specific resource
            'pbac_access_target_id' => $target->id,
            'target_id' => null, // we mean **any** user can access the resource
            'action' => ['view', 'list'], // a user can only view and list
            'effect' => 'allow',
        ]);

        // So, current user can access all the resources of that type but only for provided actions
        $resourceClassName = $resource->type;
        $resourceInstance = $resourceClassName::find(1);
        $this->assertTrue($user->can('view', $resourceInstance));
        $this->assertTrue($user->can('list', $resourceInstance));
        $this->assertTrue($user2->can('view', $resourceInstance));
        $this->assertTrue($user2->can('list', $resourceInstance));
        $this->assertFalse($user->can('non-action-defined', $resourceInstance));
        $this->assertFalse($user2->can('non-action-defined', $resourceInstance));
        // try access to another resource which is not allowed
        $resourceInstance = $resourceClassName::find(3);
        $this->assertFalse($user->can('view', $resourceInstance));
        $this->assertFalse($user->can('list', $resourceInstance));
        $this->assertFalse($user2->can('view', $resourceInstance));
        $this->assertFalse($user2->can('list', $resourceInstance));
        $this->assertFalse($user->can('non-action-defined', $resourceInstance));
        $this->assertFalse($user2->can('non-action-defined', $resourceInstance));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_allows_access_to_normal_users_which_resources_are_allowed(): void
    {
        $totalResources = 3;
        $user = $this->createUserWithPbacTraitsAccessControl(); // a non super admin
        $resources = PBACAccessResource::factory($totalResources)->create();
        // add target as users
        $target = PBACAccessTarget::factory()->create([
            'type' => get_class($user),
        ]);
        // So, current user can access all the resources of that type but only for provided actions
        foreach (range(1, $totalResources) as $i) {
            PBACAccessControl::factory()->create([
                'pbac_access_resource_id' => $resources[$i - 1]->id,
                'resource_id' => null, // by null we mean all resources of that type can be accessed
                'pbac_access_target_id' => $target->id,
                'target_id' => $user->id, // we mean **this** user can access the resource
                'action' => ['view', 'create', 'update', 'delete', 'list' ],
                'effect' => 'allow',
            ]);
        }
        $actions = [ 'view', 'create', 'update', 'delete', 'list', 'restore', 'forceDelete' ];
        $resources = PBACAccessResource::all()->map(fn ($resource) => $resource->type);
        foreach ($actions as $action) {
            foreach ($resources as $resource) {
                if (in_array($action, ['restore', 'forceDelete'])) {
                    $this->assertFalse($user->can($action, $resource));
                } else {
                    $this->assertTrue($user->can($action, $resource));
                }
            }
        }
    }

}

