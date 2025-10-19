<?php

namespace Pbac\Tests\Unit\Traits;

use Pbac\Models\PBACAccessGroup;
use Pbac\Tests\Support\Models\TestUser;
use Pbac\Tests\TestCase;

class HasPbacGroupsTest extends TestCase
{
    use \Pbac\Tests\Support\Traits\MigrationLoader;
    use \Pbac\Tests\Support\Traits\TestUsersWithTraits;

    #[\PHPUnit\Framework\Attributes\Test]
    public function a_user_can_belong_to_groups(): void
    {
        $user = $this->createUserWithPbacTraitsGroup();
        $group1 = PBACAccessGroup::factory()->create();
        $group2 = PBACAccessGroup::factory()->create();

        $user->groups()->attach($group1);
        $user->groups()->attach($group2->id);

        $this->assertCount(2, $user->groups);
        $this->assertTrue($user->groups->contains($group1));
        $this->assertTrue($user->groups->contains($group2));

        $this->assertDatabaseHas('pbac_group_user', [
            'user_id' => $user->id,
            'pbac_access_group_id' => $group1->id,
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function a_user_can_detach_from_groups(): void
    {
        $user = $this->createUserWithPbacTraitsGroup();
        $group = PBACAccessGroup::factory()->create();

        $user->groups()->attach($group);
        $this->assertCount(1, $user->groups);

        $user->groups()->detach($group);
        $user->load('groups'); // Reload the relationship

        $this->assertCount(0, $user->groups);
        $this->assertDatabaseMissing('pbac_group_user', [
            'user_id' => $user->id,
            'pbac_access_group_id' => $group->id,
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function deleting_user_detaches_from_groups(): void
    {
        $user = $this->createUserWithPbacTraitsGroup();
        $group = PBACAccessGroup::factory()->create();
        $user->groups()->attach($group);

        $this->assertDatabaseHas('pbac_group_user', [
            'user_id' => $user->id,
            'pbac_access_group_id' => $group->id,
        ]);

        $user->delete();

        $this->assertDatabaseMissing('pbac_group_user', [
            'user_id' => $user->id,
            'pbac_access_group_id' => $group->id,
        ]);
    }
}

