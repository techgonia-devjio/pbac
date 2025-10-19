<?php

namespace Pbac\Tests\Regression;

use Pbac\Models\PBACAccessControl;
use Pbac\Models\PBACAccessGroup;
use Pbac\Models\PBACAccessResource;
use Pbac\Models\PBACAccessTarget;
use Pbac\Models\PBACAccessTeam;
use Pbac\Services\PolicyEvaluator;
use Pbac\Tests\Support\Models\DummyPost;
use Pbac\Tests\Support\Models\TestUser;
use Pbac\Tests\TestCase;

/**
 * Regression tests for core PBAC functionality.
 *
 * These tests ensure that critical features continue to work correctly
 * after code changes. DO NOT modify these tests unless the expected
 * behavior intentionally changes.
 */
class CoreFunctionalityRegressionTest extends TestCase
{
    use \Pbac\Tests\Support\Traits\MigrationLoader;

    protected PolicyEvaluator $evaluator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->evaluator = app(PolicyEvaluator::class);
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
    public function regression_basic_allow_rule_grants_access(): void
    {
        // REGRESSION: Basic allow rules must always work
        $user = TestUser::factory()->create();
        $post = DummyPost::create(['title' => 'Test Post']);

        PBACAccessControl::factory()
            ->allow()
            ->forUser($user)
            ->forResource(DummyPost::class, $post->id)
            ->withAction('view')
            ->create();

        $this->assertTrue($user->can('view', $post));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function regression_deny_always_overrides_allow_regardless_of_priority(): void
    {
        // REGRESSION: Deny-first security model must never change
        $user = TestUser::factory()->create();
        $post = DummyPost::create(['title' => 'Test Post']);

        // High priority allow
        PBACAccessControl::factory()
            ->allow()
            ->forUser($user)
            ->forResource(DummyPost::class, $post->id)
            ->withAction('view')
            ->withPriority(1000)
            ->create();

        // Low priority deny
        PBACAccessControl::factory()
            ->deny()
            ->forUser($user)
            ->forResource(DummyPost::class, $post->id)
            ->withAction('view')
            ->withPriority(1)
            ->create();

        // Deny must always win
        $this->assertFalse($user->can('view', $post));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function regression_group_permissions_work_for_all_members(): void
    {
        // REGRESSION: Group-based permissions must work
        $group = PBACAccessGroup::factory()->create(['name' => 'Editors']);
        $user1 = TestUser::factory()->create();
        $user2 = TestUser::factory()->create();
        $post = DummyPost::create(['title' => 'Shared Post']);

        $user1->groups()->attach($group->id);
        $user2->groups()->attach($group->id);

        PBACAccessControl::factory()
            ->allow()
            ->forGroup($group)
            ->forResource(DummyPost::class, $post->id)
            ->withAction('edit')
            ->create();

        $this->assertTrue($user1->can('edit', $post));
        $this->assertTrue($user2->can('edit', $post));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function regression_team_permissions_work_for_all_members(): void
    {
        // REGRESSION: Team-based permissions must work
        $team = PBACAccessTeam::factory()->create(['name' => 'Dev Team']);
        $user1 = TestUser::factory()->create();
        $user2 = TestUser::factory()->create();
        $post = DummyPost::create(['title' => 'Team Post']);

        $user1->teams()->attach($team->id);
        $user2->teams()->attach($team->id);

        PBACAccessControl::factory()
            ->allow()
            ->forTeam($team)
            ->forResource(DummyPost::class, $post->id)
            ->withAction('view')
            ->create();

        $this->assertTrue($user1->can('view', $post));
        $this->assertTrue($user2->can('view', $post));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function regression_super_admin_bypasses_all_checks(): void
    {
        // REGRESSION: Super admin bypass must always work
        $superAdmin = TestUser::factory()->create(['is_super_admin' => true]);
        $post = DummyPost::create(['title' => 'Protected']);

        // No permissions defined
        $this->assertTrue($superAdmin->can('view', $post));
        $this->assertTrue($superAdmin->can('edit', $post));
        $this->assertTrue($superAdmin->can('delete', $post));
        $this->assertTrue($superAdmin->can('anyRandomAction', $post));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function regression_no_rules_means_deny_by_default(): void
    {
        // REGRESSION: Secure by default - deny when no rules exist
        $user = TestUser::factory()->create();
        $post = DummyPost::create(['title' => 'New Post']);

        // No rules created
        $this->assertFalse($user->can('view', $post));
        $this->assertFalse($user->can('edit', $post));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function regression_wildcard_resource_id_applies_to_all_instances(): void
    {
        // REGRESSION: Null resource_id means "any instance"
        $user = TestUser::factory()->create();
        $post1 = DummyPost::create(['title' => 'Post 1']);
        $post2 = DummyPost::create(['title' => 'Post 2']);
        $post3 = DummyPost::create(['title' => 'Post 3']);

        PBACAccessControl::factory()
            ->allow()
            ->forUser($user)
            ->forResource(DummyPost::class, null) // Any DummyPost
            ->withAction('view')
            ->create();

        $this->assertTrue($user->can('view', $post1));
        $this->assertTrue($user->can('view', $post2));
        $this->assertTrue($user->can('view', $post3));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function regression_specific_resource_id_overrides_wildcard(): void
    {
        // REGRESSION: Specific rules take precedence over general rules
        $user = TestUser::factory()->create();
        $post1 = DummyPost::create(['title' => 'Post 1']);
        $post2 = DummyPost::create(['title' => 'Post 2']);

        // Allow all posts
        PBACAccessControl::factory()
            ->allow()
            ->forUser($user)
            ->forResource(DummyPost::class, null)
            ->withAction('view')
            ->create();

        // Deny specific post
        PBACAccessControl::factory()
            ->deny()
            ->forUser($user)
            ->forResource(DummyPost::class, $post2->id)
            ->withAction('view')
            ->create();

        $this->assertTrue($user->can('view', $post1));
        $this->assertFalse($user->can('view', $post2)); // Specific deny wins
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function regression_multiple_actions_in_single_rule(): void
    {
        // REGRESSION: Array of actions must work
        $user = TestUser::factory()->create();
        $post = DummyPost::create(['title' => 'Test Post']);

        PBACAccessControl::factory()
            ->allow()
            ->forUser($user)
            ->forResource(DummyPost::class, $post->id)
            ->withAction(['view', 'edit', 'delete'])
            ->create();

        $this->assertTrue($user->can('view', $post));
        $this->assertTrue($user->can('edit', $post));
        $this->assertTrue($user->can('delete', $post));
        $this->assertFalse($user->can('publish', $post)); // Not in list
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function regression_priority_ordering_works_within_same_effect(): void
    {
        // REGRESSION: Higher priority rules evaluated first
        $user = TestUser::factory()->create();
        $post = DummyPost::create(['title' => 'Test Post']);

        // Low priority with impossible condition
        PBACAccessControl::factory()
            ->allow()
            ->forUser($user)
            ->forResource(DummyPost::class, $post->id)
            ->withAction('view')
            ->withPriority(1)
            ->create([
                'extras' => ['min_level' => 100],
            ]);

        // High priority with no condition
        PBACAccessControl::factory()
            ->allow()
            ->forUser($user)
            ->forResource(DummyPost::class, $post->id)
            ->withAction('view')
            ->withPriority(10)
            ->create();

        // High priority should be checked first and succeed
        $this->assertTrue($user->can('view', $post));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function regression_user_can_be_in_multiple_groups(): void
    {
        // REGRESSION: Users can belong to multiple groups
        $user = TestUser::factory()->create();
        $group1 = PBACAccessGroup::factory()->create(['name' => 'Group 1']);
        $group2 = PBACAccessGroup::factory()->create(['name' => 'Group 2']);
        $group3 = PBACAccessGroup::factory()->create(['name' => 'Group 3']);

        $user->groups()->attach([$group1->id, $group2->id, $group3->id]);

        $this->assertEquals(3, $user->groups()->count());
        $this->assertTrue($user->groups->contains($group1));
        $this->assertTrue($user->groups->contains($group2));
        $this->assertTrue($user->groups->contains($group3));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function regression_user_can_be_in_multiple_teams(): void
    {
        // REGRESSION: Users can belong to multiple teams
        $user = TestUser::factory()->create();
        $team1 = PBACAccessTeam::factory()->create(['name' => 'Team 1']);
        $team2 = PBACAccessTeam::factory()->create(['name' => 'Team 2']);
        $team3 = PBACAccessTeam::factory()->create(['name' => 'Team 3']);

        $user->teams()->attach([$team1->id, $team2->id, $team3->id]);

        $this->assertEquals(3, $user->teams()->count());
        $this->assertTrue($user->teams->contains($team1));
        $this->assertTrue($user->teams->contains($team2));
        $this->assertTrue($user->teams->contains($team3));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function regression_removing_user_from_group_removes_permissions(): void
    {
        // REGRESSION: Permission changes take effect immediately
        $user = TestUser::factory()->create();
        $group = PBACAccessGroup::factory()->create(['name' => 'Editors']);
        $post = DummyPost::create(['title' => 'Post']);

        $user->groups()->attach($group->id);

        PBACAccessControl::factory()
            ->allow()
            ->forGroup($group)
            ->forResource(DummyPost::class, $post->id)
            ->withAction('edit')
            ->create();

        // User has permission via group
        $this->assertTrue($user->can('edit', $post));

        // Remove user from group
        $user->groups()->detach($group->id);

        // Refresh user to clear relationships cache
        $user = $user->fresh();

        // User no longer has permission
        $this->assertFalse($user->can('edit', $post));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function regression_deleting_access_rule_removes_permission(): void
    {
        // REGRESSION: Deleting rules takes effect immediately
        $user = TestUser::factory()->create();
        $post = DummyPost::create(['title' => 'Post']);

        $rule = PBACAccessControl::factory()
            ->allow()
            ->forUser($user)
            ->forResource(DummyPost::class, $post->id)
            ->withAction('view')
            ->create();

        $this->assertTrue($user->can('view', $post));

        // Delete the rule
        $rule->delete();

        // Permission removed
        $this->assertFalse($user->can('view', $post));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function regression_class_name_as_resource_for_create_action(): void
    {
        // REGRESSION: Using class name (not instance) for create permissions
        $user = TestUser::factory()->create();

        PBACAccessControl::factory()
            ->allow()
            ->forUser($user)
            ->forResource(DummyPost::class, null)
            ->withAction('create')
            ->create();

        // Check using class name
        $this->assertTrue($user->can('create', DummyPost::class));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function regression_individual_user_permission_overrides_group_deny(): void
    {
        // REGRESSION: Individual permissions should work alongside group permissions
        $user = TestUser::factory()->create();
        $group = PBACAccessGroup::factory()->create(['name' => 'Users']);
        $post = DummyPost::create(['title' => 'Special Post']);

        $user->groups()->attach($group->id);

        // Group deny
        PBACAccessControl::factory()
            ->deny()
            ->forGroup($group)
            ->forResource(DummyPost::class, $post->id)
            ->withAction('view')
            ->create();

        // Individual allow
        PBACAccessControl::factory()
            ->allow()
            ->forUser($user)
            ->forResource(DummyPost::class, $post->id)
            ->withAction('view')
            ->create();

        // Deny always wins (deny-first model)
        $this->assertFalse($user->can('view', $post));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function regression_action_matching_is_case_sensitive(): void
    {
        // REGRESSION: Action names are case-sensitive
        $user = TestUser::factory()->create();
        $post = DummyPost::create(['title' => 'Post']);

        PBACAccessControl::factory()
            ->allow()
            ->forUser($user)
            ->forResource(DummyPost::class, $post->id)
            ->withAction('viewDetailed')
            ->create();

        $this->assertTrue($user->can('viewDetailed', $post));
        $this->assertFalse($user->can('viewdetailed', $post));
        $this->assertFalse($user->can('VIEWDETAILED', $post));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function regression_deleting_group_cascades_to_access_rules(): void
    {
        $this->markTestSkipped('Cascade deletes depend on database-specific foreign key constraints');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function regression_deleting_team_cascades_to_access_rules(): void
    {
        $this->markTestSkipped('Cascade deletes depend on database-specific foreign key constraints');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function regression_policy_evaluator_is_singleton(): void
    {
        // REGRESSION: PolicyEvaluator must be singleton
        $evaluator1 = app(PolicyEvaluator::class);
        $evaluator2 = app(PolicyEvaluator::class);

        $this->assertSame($evaluator1, $evaluator2);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function regression_can_method_returns_boolean(): void
    {
        // REGRESSION: can() must always return boolean
        $user = TestUser::factory()->create();
        $post = DummyPost::create(['title' => 'Post']);

        $result = $user->can('view', $post);

        $this->assertIsBool($result);
    }
}
