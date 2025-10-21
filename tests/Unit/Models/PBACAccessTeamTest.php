<?php

namespace Pbac\Tests\Unit\Models;

use Pbac\Models\PBACAccessTeam;
use Pbac\Tests\Support\Models\TestUser;
use Pbac\Tests\TestCase;

class PBACAccessTeamTest extends TestCase
{
    use \Pbac\Tests\Support\Traits\MigrationLoader;

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_can_be_created(): void
    {
        $owner = TestUser::factory()->create();
        $team = PBACAccessTeam::create([
            'name' => 'Sales Team',
            'description' => 'Team for sales department',
            'owner_id' => $owner->id,
        ]);

        $this->assertDatabaseHas('pbac_access_teams', [
            'name' => 'Sales Team',
            'owner_id' => $owner->id,
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_can_be_updated(): void
    {
        $team = PBACAccessTeam::factory()->create();
        $team->update(['name' => 'Marketing Team']);

        $this->assertDatabaseHas('pbac_access_teams', [
            'id' => $team->id,
            'name' => 'Marketing Team',
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_can_be_deleted(): void
    {
        $team = PBACAccessTeam::factory()->create();
        $team->delete();

        $this->assertDatabaseMissing('pbac_access_teams', [
            'id' => $team->id,
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_can_have_users(): void
    {
        $team = PBACAccessTeam::factory()->create();
        $user1 = TestUser::factory()->create();
        $user2 = TestUser::factory()->create();

        $team->users()->attach($user1);
        $team->users()->attach($user2->id); // Test with ID

        $this->assertCount(2, $team->users);
        $this->assertTrue($team->users->contains($user1));
        $this->assertTrue($team->users->contains($user2));

        $this->assertDatabaseHas('pbac_team_user', [
            'pbac_team_id' => $team->id,
            'user_id' => $user1->id,
        ]);
        $this->assertDatabaseHas('pbac_team_user', [
            'pbac_team_id' => $team->id,
            'user_id' => $user2->id,
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_can_detach_users(): void
    {
        $team = PBACAccessTeam::factory()->create();
        $user = TestUser::factory()->create();
        $team->users()->attach($user);

        $this->assertCount(1, $team->users);

        $team->users()->detach($user);
        $team->load('users'); // Reload the relationship

        $this->assertCount(0, $team->users);
        $this->assertDatabaseMissing('pbac_team_user', [
            'pbac_team_id' => $team->id,
            'user_id' => $user->id,
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function deleting_team_detaches_users(): void
    {
        $team = PBACAccessTeam::factory()->create();
        $user = TestUser::factory()->create();
        $team->users()->attach($user);

        $this->assertDatabaseHas('pbac_team_user', [
            'pbac_team_id' => $team->id,
            'user_id' => $user->id,
        ]);

        $team->delete();

        $this->assertDatabaseMissing('pbac_team_user', [
            'pbac_team_id' => $team->id,
            'user_id' => $user->id,
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_has_an_owner(): void
    {
        $owner = TestUser::factory()->create();
        $team = PBACAccessTeam::factory()->forOwner($owner)->create();

        $this->assertTrue($team->owner->is($owner));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function owner_is_set_to_null_on_owner_deletion(): void
    {
        $owner = TestUser::factory()->create();
        $team = PBACAccessTeam::factory()->forOwner($owner)->create();

        $owner->delete();
        $team->refresh(); // Reload the team to get updated owner_id

        $this->assertNull($team->owner_id);
        $this->assertDatabaseHas('pbac_access_teams', [
            'id' => $team->id,
            'owner_id' => null,
        ]);
    }
}
