<?php

namespace Modules\Pbac\Tests\Unit\Traits;

use Modules\Pbac\Models\PBACAccessTeam;
use Modules\Pbac\Tests\Support\Models\TestUser;
use Modules\Pbac\Tests\TestCase;

class HasPbacTeamsTest extends TestCase
{
    use \Modules\Pbac\Tests\Support\Traits\TestUsersWithTraits;
    use \Modules\Pbac\Tests\Support\Traits\MigrationLoader;
    #[\PHPUnit\Framework\Attributes\Test]
    public function a_user_can_belong_to_teams(): void
    {
        $user = $this->createUserWithPbacTraitsTeam();
        $team1 = PBACAccessTeam::factory()->create();
        $team2 = PBACAccessTeam::factory()->create();

        $user->teams()->attach($team1);
        $user->teams()->attach($team2->id);

        $this->assertCount(2, $user->teams);
        $this->assertTrue($user->teams->contains($team1));
        $this->assertTrue($user->teams->contains($team2));

        $this->assertDatabaseHas('pbac_team_user', [
            'user_id' => $user->id,
            'pbac_team_id' => $team1->id,
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function a_user_can_detach_from_teams(): void
    {
        $user = $this->createUserWithPbacTraitsTeam();
        $team = PBACAccessTeam::factory()->create();

        $user->teams()->attach($team);
        $this->assertCount(1, $user->teams);

        $user->teams()->detach($team);
        $user->load('teams'); // Reload the relationship

        $this->assertCount(0, $user->teams);
        $this->assertDatabaseMissing('pbac_team_user', [
            'user_id' => $user->id,
            'pbac_team_id' => $team->id,
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function deleting_user_detaches_from_teams(): void
    {
        $user = $this->createUserWithPbacTraitsTeam();
        $team = PBACAccessTeam::factory()->create();
        $user->teams()->attach($team);

        $this->assertDatabaseHas('pbac_team_user', [
            'user_id' => $user->id,
            'pbac_team_id' => $team->id,
        ]);

        $user->delete();

        $this->assertDatabaseMissing('pbac_team_user', [
            'user_id' => $user->id,
            'pbac_team_id' => $team->id,
        ]);
    }
}

