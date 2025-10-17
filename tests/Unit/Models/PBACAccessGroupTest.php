<?php

namespace Modules\Pbac\Tests\Unit\Models;


use Modules\Pbac\Models\PBACAccessGroup;
use Modules\Pbac\Tests\Support\Models\TestUser;
use Modules\Pbac\Tests\Support\Traits\MigrationLoader;
use Modules\Pbac\Tests\TestCase;

class PBACAccessGroupTest extends TestCase
{
    use MigrationLoader;

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_can_be_created(): void
    {
        $group = PBACAccessGroup::create([
            'name' => 'Administrators',
            'description' => 'Users with administrative privileges',
        ]);

        $this->assertDatabaseHas('pbac_access_groups', [
            'name' => 'Administrators',
            'description' => 'Users with administrative privileges',
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_can_be_updated(): void
    {
        $group = PBACAccessGroup::factory()->create();
        $group->update(['name' => 'Updated Group Name']);

        $this->assertDatabaseHas('pbac_access_groups', [
            'id' => $group->id,
            'name' => 'Updated Group Name',
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_can_be_deleted(): void
    {
        $group = PBACAccessGroup::factory()->create();
        $group->delete();

        $this->assertDatabaseMissing('pbac_access_groups', [
            'id' => $group->id,
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_can_have_users(): void
    {
        $group = PBACAccessGroup::factory()->create();
        $user1 = TestUser::factory()->create();
        $user2 = TestUser::factory()->create();

        $group->users()->attach($user1);
        $group->users()->attach($user2->id); // Test with ID

        $this->assertCount(2, $group->users);
        $this->assertTrue($group->users->contains($user1));
        $this->assertTrue($group->users->contains($user2));

        $this->assertDatabaseHas('pbac_group_user', [
            'pbac_access_group_id' => $group->id,
            'user_id' => $user1->id,
        ]);
        $this->assertDatabaseHas('pbac_group_user', [
            'pbac_access_group_id' => $group->id,
            'user_id' => $user2->id,
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_can_detach_users(): void
    {
        $group = PBACAccessGroup::factory()->create();
        $user = TestUser::factory()->create();
        $group->users()->attach($user);

        $this->assertCount(1, $group->users);

        $group->users()->detach($user);
        $group->load('users'); // Reload the relationship

        $this->assertCount(0, $group->users);
        $this->assertDatabaseMissing('pbac_group_user', [
            'pbac_access_group_id' => $group->id,
            'user_id' => $user->id,
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function deleting_group_detaches_users(): void
    {
        $group = PBACAccessGroup::factory()->create();
        $user = TestUser::factory()->create();
        $group->users()->attach($user);

        $this->assertDatabaseHas('pbac_group_user', [
            'pbac_access_group_id' => $group->id,
            'user_id' => $user->id,
        ]);

        $group->delete();

        $this->assertDatabaseMissing('pbac_group_user', [
            'pbac_access_group_id' => $group->id,
            'user_id' => $user->id,
        ]);
    }

}
