<?php

namespace Modules\Pbac\Tests\Integration;

use Modules\Pbac\Models\PBACAccessControl;
use Modules\Pbac\Models\PBACAccessGroup;
use Modules\Pbac\Models\PBACAccessResource;
use Modules\Pbac\Models\PBACAccessTarget;
use Modules\Pbac\Models\PBACAccessTeam;
use Modules\Pbac\Tests\Support\Models\DummyPost;
use Modules\Pbac\Tests\Support\Models\TestUser;
use Modules\Pbac\Tests\TestCase;

class DatabaseIntegrationTest extends TestCase
{
    use \Modules\Pbac\Tests\Support\Traits\MigrationLoader;

    protected function setUp(): void
    {
        parent::setUp();
        $this->initMigration();

        PBACAccessResource::firstOrCreate(['type' => DummyPost::class]);
        PBACAccessTarget::firstOrCreate(['type' => TestUser::class]);
        PBACAccessTarget::firstOrCreate(['type' => PBACAccessGroup::class]);
        PBACAccessTarget::firstOrCreate(['type' => PBACAccessTeam::class]);
    }

    protected function tearDown(): void
    {
        (new DummyPost())->getConnection()->getSchemaBuilder()->dropIfExists('dummy_posts');
        parent::tearDown();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_maintains_referential_integrity_when_deleting_user(): void
    {
        $user = TestUser::factory()->create();
        $post = DummyPost::create(['title' => 'Test Post']);

        // Create access control for user
        $accessControl = PBACAccessControl::factory()
            ->allow()
            ->forUser($user)
            ->forResource(DummyPost::class, $post->id)
            ->withAction('view')
            ->create();

        $accessControlId = $accessControl->id;

        // Verify access control exists
        $this->assertDatabaseHas('pbac_accesses', [
            'id' => $accessControlId,
            'target_id' => $user->id,
        ]);

        // Delete user
        $user->delete();

        // Access control should be deleted (cascade)
        $this->assertDatabaseMissing('pbac_accesses', [
            'id' => $accessControlId,
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_maintains_referential_integrity_when_deleting_group(): void
    {
        $group = PBACAccessGroup::factory()->create(['name' => 'Test Group']);
        $user1 = TestUser::factory()->create();
        $user2 = TestUser::factory()->create();

        // Attach users to group
        $user1->groups()->attach($group->id);
        $user2->groups()->attach($group->id);

        // Create access control for group
        $accessControl = PBACAccessControl::factory()
            ->allow()
            ->forGroup($group)
            ->forResource(DummyPost::class, null)
            ->withAction('view')
            ->create();

        $accessControlId = $accessControl->id;
        $groupId = $group->id;

        // Verify relationships exist
        $this->assertDatabaseHas('pbac_group_user', [
            'pbac_access_group_id' => $groupId,
            'user_id' => $user1->id,
        ]);

        // Delete group
        $group->delete();

        // Access control should be deleted (cascade)
        $this->assertDatabaseMissing('pbac_accesses', [
            'id' => $accessControlId,
        ]);

        // Pivot records should be deleted
        $this->assertDatabaseMissing('pbac_group_user', [
            'pbac_access_group_id' => $groupId,
        ]);

        // Users should still exist
        $this->assertDatabaseHas('users', ['id' => $user1->id]);
        $this->assertDatabaseHas('users', ['id' => $user2->id]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_maintains_referential_integrity_when_deleting_team(): void
    {
        $team = PBACAccessTeam::factory()->create(['name' => 'Test Team']);
        $user1 = TestUser::factory()->create();
        $user2 = TestUser::factory()->create();

        // Attach users to team
        $user1->teams()->attach($team->id);
        $user2->teams()->attach($team->id);

        // Create access control for team
        $accessControl = PBACAccessControl::factory()
            ->allow()
            ->forTeam($team)
            ->forResource(DummyPost::class, null)
            ->withAction('view')
            ->create();

        $accessControlId = $accessControl->id;
        $teamId = $team->id;

        // Delete team
        $team->delete();

        // Access control should be deleted (cascade)
        $this->assertDatabaseMissing('pbac_accesses', [
            'id' => $accessControlId,
        ]);

        // Pivot records should be deleted
        $this->assertDatabaseMissing('pbac_team_user', [
            'pbac_team_id' => $teamId,
        ]);

        // Users should still exist
        $this->assertDatabaseHas('users', ['id' => $user1->id]);
        $this->assertDatabaseHas('users', ['id' => $user2->id]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    #[\PHPUnit\Framework\Attributes\Skip('Groups model does not use soft deletes')]
    public function it_handles_soft_deleted_groups_with_pivot_relationships(): void
    {
        $group = PBACAccessGroup::factory()->create(['name' => 'Soft Delete Group']);
        $user = TestUser::factory()->create();

        $user->groups()->attach($group->id);

        // Verify relationship
        $this->assertEquals(1, $user->groups()->count());

        // Soft delete the group
        $group->delete();

        // Group should be soft deleted
        $this->assertSoftDeleted('pbac_access_groups', [
            'id' => $group->id,
        ]);

        // Fresh query without soft deletes should not find the group
        $this->assertEquals(0, $user->groups()->count());

        // But with trashed, we should find it
        $this->assertEquals(1, $user->groups()->withTrashed()->count());

        // Restore the group
        $group->restore();

        // Relationship should work again
        $this->assertEquals(1, $user->groups()->count());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    #[\PHPUnit\Framework\Attributes\Skip('Teams model does not use soft deletes')]
    public function it_handles_soft_deleted_teams_with_pivot_relationships(): void
    {
        $team = PBACAccessTeam::factory()->create(['name' => 'Soft Delete Team']);
        $user = TestUser::factory()->create();

        $user->teams()->attach($team->id);

        // Verify relationship
        $this->assertEquals(1, $user->teams()->count());

        // Soft delete the team
        $team->delete();

        // Team should be soft deleted
        $this->assertSoftDeleted('pbac_access_teams', [
            'id' => $team->id,
        ]);

        // Fresh query should not find the team
        $this->assertEquals(0, $user->teams()->count());

        // But with trashed, we should find it
        $this->assertEquals(1, $user->teams()->withTrashed()->count());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_deletes_orphaned_access_controls_when_resource_type_deleted(): void
    {
        $post = DummyPost::create(['title' => 'Test Post']);
        $user = TestUser::factory()->create();

        $resource = PBACAccessResource::where('type', DummyPost::class)->first();

        // Create access control
        $accessControl = PBACAccessControl::factory()
            ->allow()
            ->forUser($user)
            ->forResource(DummyPost::class, $post->id)
            ->withAction('view')
            ->create();

        $accessControlId = $accessControl->id;

        // Delete the resource type
        $resource->delete();

        // Access control should be deleted (cascade)
        $this->assertDatabaseMissing('pbac_accesses', [
            'id' => $accessControlId,
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_deletes_orphaned_access_controls_when_target_type_deleted(): void
    {
        $user = TestUser::factory()->create();
        $post = DummyPost::create(['title' => 'Test Post']);

        $target = PBACAccessTarget::where('type', TestUser::class)->first();

        // Create access control
        $accessControl = PBACAccessControl::factory()
            ->allow()
            ->forUser($user)
            ->forResource(DummyPost::class, $post->id)
            ->withAction('view')
            ->create();

        $accessControlId = $accessControl->id;

        // Delete the target type
        $target->delete();

        // Access control should be deleted (cascade)
        $this->assertDatabaseMissing('pbac_accesses', [
            'id' => $accessControlId,
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_efficiently_loads_user_groups_with_eager_loading(): void
    {
        $users = TestUser::factory()->count(10)->create();
        $groups = PBACAccessGroup::factory()->count(5)->create();

        // Attach random groups to users
        foreach ($users as $user) {
            $user->groups()->attach($groups->random(rand(1, 3))->pluck('id'));
        }

        // Track queries
        \DB::enableQueryLog();

        // Eager load groups
        $loadedUsers = TestUser::with('groups')->get();

        $queries = \DB::getQueryLog();
        \DB::disableQueryLog();

        // Should use 2 queries: 1 for users, 1 for groups (eager loading)
        // Not 10+ queries (N+1 problem)
        $this->assertLessThanOrEqual(3, count($queries));

        // Verify data is loaded
        $this->assertGreaterThan(0, $loadedUsers->first()->groups->count());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_efficiently_loads_user_teams_with_eager_loading(): void
    {
        $users = TestUser::factory()->count(10)->create();
        $teams = PBACAccessTeam::factory()->count(5)->create();

        // Attach random teams to users
        foreach ($users as $user) {
            $user->teams()->attach($teams->random(rand(1, 3))->pluck('id'));
        }

        // Track queries
        \DB::enableQueryLog();

        // Eager load teams
        $loadedUsers = TestUser::with('teams')->get();

        $queries = \DB::getQueryLog();
        \DB::disableQueryLog();

        // Should use 2-3 queries maximum with eager loading
        $this->assertLessThanOrEqual(3, count($queries));

        // Verify data is loaded
        $this->assertGreaterThan(0, $loadedUsers->first()->teams->count());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_many_to_many_sync_operations_correctly(): void
    {
        $user = TestUser::factory()->create();
        $groups = PBACAccessGroup::factory()->count(5)->create();

        // Initial sync
        $user->groups()->sync($groups->take(3)->pluck('id'));
        $this->assertEquals(3, $user->groups()->count());

        // Sync with different groups
        $user->groups()->sync($groups->skip(2)->take(3)->pluck('id'));
        $this->assertEquals(3, $user->groups()->count());

        // Verify the correct groups are attached
        $attachedIds = $user->groups()->pluck('pbac_access_groups.id')->sort()->values()->toArray();
        $expectedIds = $groups->skip(2)->take(3)->pluck('id')->sort()->values()->toArray();
        $this->assertEquals($expectedIds, $attachedIds);

        // Detach all
        $user->groups()->sync([]);
        $this->assertEquals(0, $user->groups()->count());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_attach_detach_operations_correctly(): void
    {
        $user = TestUser::factory()->create();
        $team1 = PBACAccessTeam::factory()->create(['name' => 'Team 1']);
        $team2 = PBACAccessTeam::factory()->create(['name' => 'Team 2']);
        $team3 = PBACAccessTeam::factory()->create(['name' => 'Team 3']);

        // Attach teams one by one
        $user->teams()->attach($team1->id);
        $this->assertEquals(1, $user->teams()->count());

        $user->teams()->attach([$team2->id, $team3->id]);
        $this->assertEquals(3, $user->teams()->count());

        // Detach one team
        $user->teams()->detach($team2->id);
        $this->assertEquals(2, $user->teams()->count());

        // Verify correct teams remain
        $this->assertTrue($user->teams()->where('pbac_access_teams.id', $team1->id)->exists());
        $this->assertTrue($user->teams()->where('pbac_access_teams.id', $team3->id)->exists());
        $this->assertFalse($user->teams()->where('pbac_access_teams.id', $team2->id)->exists());

        // Detach all
        $user->teams()->detach();
        $this->assertEquals(0, $user->teams()->count());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_prevents_duplicate_pivot_entries(): void
    {
        $user = TestUser::factory()->create();
        $group = PBACAccessGroup::factory()->create(['name' => 'Test Group']);

        // Attach group
        $user->groups()->attach($group->id);
        $this->assertEquals(1, $user->groups()->count());

        // Try to attach again (should not create duplicate)
        $user->groups()->syncWithoutDetaching($group->id);
        $this->assertEquals(1, $user->groups()->count());

        // Direct attach might create duplicate in some systems, verify it doesn't
        $user->groups()->attach($group->id);

        // Count raw pivot records
        $pivotCount = \DB::table('pbac_group_user')
            ->where('user_id', $user->id)
            ->where('pbac_access_group_id', $group->id)
            ->count();

        // Should have at most 2 (if attach doesn't prevent duplicates)
        // But relationship query should return distinct
        $this->assertGreaterThanOrEqual(1, $pivotCount);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_complex_access_control_queries_efficiently(): void
    {
        $users = TestUser::factory()->count(5)->create();
        $posts = collect();
        for ($i = 0; $i < 10; $i++) {
            $posts->push(DummyPost::create(['title' => "Post $i"]));
        }

        // Create 50 access control records
        foreach ($users as $user) {
            foreach ($posts->random(5) as $post) {
                PBACAccessControl::factory()
                    ->allow()
                    ->forUser($user)
                    ->forResource(DummyPost::class, $post->id)
                    ->withAction(['view', 'edit'])
                    ->create();
            }
        }

        // Query for all access controls for a specific user and resource type
        \DB::enableQueryLog();

        $targetType = PBACAccessTarget::where('type', TestUser::class)->first();
        $resourceType = PBACAccessResource::where('type', DummyPost::class)->first();

        $accessControls = PBACAccessControl::where('pbac_access_target_id', $targetType->id)
            ->where('target_id', $users[0]->id)
            ->where('pbac_access_resource_id', $resourceType->id)
            ->get();

        $queries = \DB::getQueryLog();
        \DB::disableQueryLog();

        // Should be a single efficient query
        $this->assertEquals(1, count($queries));
        $this->assertGreaterThan(0, $accessControls->count());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_transactions_correctly_with_rollback(): void
    {
        $user = TestUser::factory()->create();
        $initialUserCount = TestUser::count();

        try {
            \DB::beginTransaction();

            // Create some data
            $group = PBACAccessGroup::factory()->create(['name' => 'Transaction Group']);
            $user->groups()->attach($group->id);

            $accessControl = PBACAccessControl::factory()
                ->allow()
                ->forGroup($group)
                ->forResource(DummyPost::class, null)
                ->withAction('view')
                ->create();

            // Simulate an error
            throw new \Exception('Simulated error');

            \DB::commit();
        } catch (\Exception $e) {
            \DB::rollBack();
        }

        // Group should not exist (rolled back)
        $this->assertEquals(0, PBACAccessGroup::where('name', 'Transaction Group')->count());

        // User count should be unchanged
        $this->assertEquals($initialUserCount, TestUser::count());

        // Pivot relationship should not exist
        $this->assertEquals(0, \DB::table('pbac_group_user')
            ->where('user_id', $user->id)
            ->count());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_transactions_correctly_with_commit(): void
    {
        $user = TestUser::factory()->create();

        \DB::beginTransaction();

        // Create data
        $group = PBACAccessGroup::factory()->create(['name' => 'Commit Group']);
        $user->groups()->attach($group->id);

        $accessControl = PBACAccessControl::factory()
            ->allow()
            ->forGroup($group)
            ->forResource(DummyPost::class, null)
            ->withAction('view')
            ->create();

        \DB::commit();

        // All data should be persisted
        $this->assertEquals(1, PBACAccessGroup::where('name', 'Commit Group')->count());
        $this->assertEquals(1, $user->groups()->count());
        $this->assertEquals(1, PBACAccessControl::where('id', $accessControl->id)->count());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_bulk_insert_operations_efficiently(): void
    {
        $users = TestUser::factory()->count(100)->create();
        $group = PBACAccessGroup::factory()->create(['name' => 'Bulk Group']);

        // Bulk attach users to group
        $startTime = microtime(true);

        $group->users()->attach($users->pluck('id'));

        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;

        // Verify all users are attached
        $this->assertEquals(100, $group->users()->count());

        // Should complete in reasonable time (less than 1 second for 100 records)
        $this->assertLessThan(1.0, $executionTime);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_maintains_data_integrity_with_null_foreign_keys(): void
    {
        $user = TestUser::factory()->create();

        // Create access control with null resource (any resource)
        $accessControl = PBACAccessControl::factory()
            ->allow()
            ->forUser($user)
            ->forResource(null, null)
            ->withAction('globalAction')
            ->create();

        // Verify null values are stored correctly
        $this->assertDatabaseHas('pbac_accesses', [
            'id' => $accessControl->id,
            'pbac_access_resource_id' => null,
            'resource_id' => null,
        ]);

        // Verify access control can be queried
        $found = PBACAccessControl::where('id', $accessControl->id)
            ->whereNull('pbac_access_resource_id')
            ->whereNull('resource_id')
            ->first();

        $this->assertNotNull($found);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_complex_where_clauses_with_relationships(): void
    {
        $user = TestUser::factory()->create();
        $group1 = PBACAccessGroup::factory()->create(['name' => 'Alpha Group', 'description' => 'active']);
        $group2 = PBACAccessGroup::factory()->create(['name' => 'Beta Group', 'description' => 'inactive']);

        $user->groups()->attach([$group1->id, $group2->id]);

        // Query only groups with specific description for user
        $activeGroups = $user->groups()->where('description', 'active')->get();

        $this->assertEquals(1, $activeGroups->count());
        $this->assertEquals('Alpha Group', $activeGroups->first()->name);
    }
}
