<?php

namespace Modules\Pbac\Tests\Regression;

use Modules\Pbac\Models\PBACAccessControl;
use Modules\Pbac\Models\PBACAccessGroup;
use Modules\Pbac\Models\PBACAccessResource;
use Modules\Pbac\Models\PBACAccessTarget;
use Modules\Pbac\Models\PBACAccessTeam;
use Modules\Pbac\Services\PolicyEvaluator;
use Modules\Pbac\Tests\Support\Models\DummyPost;
use Modules\Pbac\Tests\Support\Models\PlainUser;
use Modules\Pbac\Tests\Support\Models\TestUser;
use Modules\Pbac\Tests\TestCase;

/**
 * Regression tests for previously fixed bugs.
 *
 * Each test represents a bug that was fixed and must stay fixed.
 * DO NOT remove or modify these tests. If a test starts failing,
 * it means the bug has been reintroduced.
 */
class BugFixRegressionTest extends TestCase
{
    use \Modules\Pbac\Tests\Support\Traits\MigrationLoader;

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
    public function bugfix_user_without_pbac_trait_is_denied_access(): void
    {
        // BUG: Users without HasPbacAccessControl trait were causing errors
        // FIX: Now properly denied access
        $plainUser = PlainUser::create([
            'email' => 'plain@example.com',
            'name' => 'Plain User',
        ]);

        $post = DummyPost::create(['title' => 'Test Post']);

        // Should return false, not throw exception
        $this->assertFalse($this->evaluator->evaluate($plainUser, 'view', $post));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function bugfix_null_resource_and_target_allowed_in_database(): void
    {
        // BUG: Database foreign keys were NOT NULL, preventing "any resource/target" rules
        // FIX: Made foreign keys nullable
        $user = TestUser::factory()->create();

        $rule = PBACAccessControl::factory()
            ->allow()
            ->forTarget(null, null)
            ->forResource(null, null)
            ->withAction('globalAction')
            ->create();

        $this->assertNull($rule->pbac_access_target_id);
        $this->assertNull($rule->target_id);
        $this->assertNull($rule->pbac_access_resource_id);
        $this->assertNull($rule->resource_id);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function bugfix_named_parameters_in_can_method(): void
    {
        // BUG: can() didn't handle named array parameters like ['resource' => $post]
        // FIX: Added support for named parameters
        $user = TestUser::factory()->create();
        $post = DummyPost::create(['title' => 'Test Post']);

        PBACAccessControl::factory()
            ->allow()
            ->forUser($user)
            ->forResource(DummyPost::class, $post->id)
            ->withAction('view')
            ->create();

        // Should work with named parameters
        $this->assertTrue($user->can('view', ['resource' => $post]));
        $this->assertTrue($user->can('view', ['resource' => $post, 'context' => []]));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function bugfix_empty_action_array_denies_access(): void
    {
        // BUG: Empty action array was not handled correctly
        // FIX: Empty action array now denies all actions
        $user = TestUser::factory()->create();
        $post = DummyPost::create(['title' => 'Test Post']);

        PBACAccessControl::factory()
            ->allow()
            ->forUser($user)
            ->forResource(DummyPost::class, $post->id)
            ->create([
                'action' => [], // Empty action array
            ]);

        $this->assertFalse($user->can('view', $post));
        $this->assertFalse($user->can('edit', $post));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function bugfix_resource_id_zero_is_valid(): void
    {
        // BUG: Resource ID of 0 was treated as null/falsy
        // FIX: Now properly handles ID 0
        $user = TestUser::factory()->create();

        PBACAccessControl::factory()
            ->allow()
            ->forUser($user)
            ->forResource(DummyPost::class, 0)
            ->withAction('view')
            ->create();

        $post = new DummyPost();
        $post->id = 0;
        $post->title = 'Zero ID Post';

        $this->assertTrue($this->evaluator->evaluate($user, 'view', $post));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function bugfix_context_with_nested_arrays_works(): void
    {
        // BUG: Complex nested context data caused issues
        // FIX: Properly handles complex context structures
        $user = TestUser::factory()->create();
        $post = DummyPost::create(['title' => 'Test Post']);

        PBACAccessControl::factory()
            ->allow()
            ->forUser($user)
            ->forResource(DummyPost::class, $post->id)
            ->withAction('view')
            ->create();

        $complexContext = [
            'level' => 10,
            'nested' => [
                'deep' => [
                    'data' => 'value',
                ],
            ],
            'array' => [1, 2, 3],
            'bool' => true,
            'null' => null,
        ];

        // Should not throw exception
        $this->assertTrue($this->evaluator->evaluate($user, 'view', $post, $complexContext));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function bugfix_user_in_multiple_groups_checks_all_groups(): void
    {
        // BUG: Only first group was being checked
        // FIX: All groups are now checked
        $user = TestUser::factory()->create();
        $group1 = PBACAccessGroup::factory()->create(['name' => 'Group 1']);
        $group2 = PBACAccessGroup::factory()->create(['name' => 'Group 2']);
        $group3 = PBACAccessGroup::factory()->create(['name' => 'Group 3']);

        $user->groups()->attach([$group1->id, $group2->id, $group3->id]);

        $post = DummyPost::create(['title' => 'Test']);

        // Permission only in group3
        PBACAccessControl::factory()
            ->allow()
            ->forGroup($group3)
            ->forResource(DummyPost::class, $post->id)
            ->withAction('view')
            ->create();

        // Should find permission in group3
        $this->assertTrue($user->can('view', $post));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function bugfix_user_in_multiple_teams_checks_all_teams(): void
    {
        // BUG: Only first team was being checked
        // FIX: All teams are now checked
        $user = TestUser::factory()->create();
        $team1 = PBACAccessTeam::factory()->create(['name' => 'Team 1']);
        $team2 = PBACAccessTeam::factory()->create(['name' => 'Team 2']);
        $team3 = PBACAccessTeam::factory()->create(['name' => 'Team 3']);

        $user->teams()->attach([$team1->id, $team2->id, $team3->id]);

        $post = DummyPost::create(['title' => 'Test']);

        // Permission only in team3
        PBACAccessControl::factory()
            ->allow()
            ->forTeam($team3)
            ->forResource(DummyPost::class, $post->id)
            ->withAction('view')
            ->create();

        // Should find permission in team3
        $this->assertTrue($user->can('view', $post));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function bugfix_deleting_resource_model_doesnt_break_evaluation(): void
    {
        // BUG: Evaluating permissions on deleted models caused errors
        // FIX: Properly handles deleted resource instances
        $user = TestUser::factory()->create();
        $post = DummyPost::create(['title' => 'To Be Deleted']);
        $postId = $post->id;

        PBACAccessControl::factory()
            ->allow()
            ->forUser($user)
            ->forResource(DummyPost::class, $postId)
            ->withAction('view')
            ->create();

        // Delete the post
        $post->delete();

        // Create new instance reference with same ID
        $deletedPost = new DummyPost();
        $deletedPost->id = $postId;

        // Should still evaluate based on ID
        $this->assertTrue($this->evaluator->evaluate($user, 'view', $deletedPost));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function bugfix_special_characters_in_action_names(): void
    {
        // BUG: Special characters in action names caused issues
        // FIX: Now properly handles special characters
        $user = TestUser::factory()->create();
        $post = DummyPost::create(['title' => 'Test']);

        $specialActions = [
            'view:detailed',
            'edit-draft',
            'publish_now',
            'action.with.dots',
        ];

        foreach ($specialActions as $action) {
            PBACAccessControl::factory()
                ->allow()
                ->forUser($user)
                ->forResource(DummyPost::class, $post->id)
                ->withAction($action)
                ->create();

            $this->assertTrue($user->can($action, $post), "Failed for action: $action");
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function bugfix_very_long_action_name_supported(): void
    {
        // BUG: Long action names were truncated
        // FIX: Action column supports 255 characters
        $user = TestUser::factory()->create();
        $post = DummyPost::create(['title' => 'Test']);

        $longAction = str_repeat('a', 250);

        PBACAccessControl::factory()
            ->allow()
            ->forUser($user)
            ->forResource(DummyPost::class, $post->id)
            ->withAction($longAction)
            ->create();

        $this->assertTrue($user->can($longAction, $post));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function bugfix_same_priority_rules_both_evaluated(): void
    {
        // BUG: Only first rule with same priority was evaluated
        // FIX: All rules at same priority level are checked
        $user = TestUser::factory()->create();
        $post = DummyPost::create(['title' => 'Test']);

        // Two deny rules with same priority
        PBACAccessControl::factory()
            ->deny()
            ->forUser($user)
            ->forResource(DummyPost::class, $post->id)
            ->withAction('view')
            ->withPriority(5)
            ->create([
                'extras' => ['min_level' => 100], // Will fail
            ]);

        PBACAccessControl::factory()
            ->deny()
            ->forUser($user)
            ->forResource(DummyPost::class, $post->id)
            ->withAction('view')
            ->withPriority(5)
            ->create([
                'extras' => ['min_level' => 1], // Will succeed
            ]);

        // Second rule should be evaluated even though same priority
        $this->assertFalse($user->can('view', ['resource' => $post, 'context' => ['level' => 5]]));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function bugfix_negative_priority_values_work(): void
    {
        // BUG: Negative priorities caused issues
        // FIX: Negative priorities now work correctly
        $user = TestUser::factory()->create();
        $post = DummyPost::create(['title' => 'Test']);

        PBACAccessControl::factory()
            ->allow()
            ->forUser($user)
            ->forResource(DummyPost::class, $post->id)
            ->withAction('view')
            ->withPriority(-10)
            ->create();

        $this->assertTrue($user->can('view', $post));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function bugfix_min_level_condition_with_equal_values(): void
    {
        // BUG: min_level condition failed when context level exactly equals required level
        // FIX: Now uses >= instead of >
        $user = TestUser::factory()->create();
        $post = DummyPost::create(['title' => 'Test']);

        PBACAccessControl::factory()
            ->allow()
            ->forUser($user)
            ->forResource(DummyPost::class, $post->id)
            ->withAction('view')
            ->create([
                'extras' => ['min_level' => 5],
            ]);

        // Exact match should pass
        $this->assertTrue($user->can('view', ['resource' => $post, 'context' => ['level' => 5]]));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function bugfix_detaching_user_from_group_removes_pivot_record(): void
    {
        // BUG: Detaching user from group didn't remove pivot record
        // FIX: Pivot records are now properly deleted
        $user = TestUser::factory()->create();
        $group = PBACAccessGroup::factory()->create(['name' => 'Test Group']);

        $user->groups()->attach($group->id);
        $this->assertEquals(1, $user->groups()->count());

        $user->groups()->detach($group->id);
        $this->assertEquals(0, $user->groups()->count());

        // Verify pivot table
        $count = \DB::table('pbac_group_user')
            ->where('user_id', $user->id)
            ->where('pbac_access_group_id', $group->id)
            ->count();

        $this->assertEquals(0, $count);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function bugfix_detaching_user_from_team_removes_pivot_record(): void
    {
        // BUG: Detaching user from team didn't remove pivot record
        // FIX: Pivot records are now properly deleted
        $user = TestUser::factory()->create();
        $team = PBACAccessTeam::factory()->create(['name' => 'Test Team']);

        $user->teams()->attach($team->id);
        $this->assertEquals(1, $user->teams()->count());

        $user->teams()->detach($team->id);
        $this->assertEquals(0, $user->teams()->count());

        // Verify pivot table
        $count = \DB::table('pbac_team_user')
            ->where('user_id', $user->id)
            ->where('pbac_team_id', $team->id)
            ->count();

        $this->assertEquals(0, $count);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function bugfix_gate_integration_evaluates_pbac_for_regular_users(): void
    {
        // BUG: Gate::before returned null for regular users, causing denial
        // FIX: Gate::before now evaluates PBAC permissions
        $user = TestUser::factory()->create();
        $post = DummyPost::create(['title' => 'Test']);

        PBACAccessControl::factory()
            ->allow()
            ->forUser($user)
            ->forResource(DummyPost::class, $post->id)
            ->withAction('view')
            ->create();

        // Gate should work with PBAC
        $this->assertTrue(\Gate::forUser($user)->allows('view', $post));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function bugfix_extras_column_stores_json_correctly(): void
    {
        // BUG: Extras column wasn't properly casting to/from JSON
        // FIX: Extras is now properly cast as JSON
        $user = TestUser::factory()->create();
        $post = DummyPost::create(['title' => 'Test']);

        $extrasData = [
            'min_level' => 5,
            'allowed_ips' => ['192.168.1.1', '10.0.0.1'],
            'requires_attribute_value' => ['status' => 'published'],
        ];

        $rule = PBACAccessControl::factory()
            ->allow()
            ->forUser($user)
            ->forResource(DummyPost::class, $post->id)
            ->withAction('view')
            ->create([
                'extras' => $extrasData,
            ]);

        // Refresh from database
        $rule = $rule->fresh();

        $this->assertEquals($extrasData, $rule->extras);
        $this->assertIsArray($rule->extras);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function bugfix_action_column_stores_json_array_correctly(): void
    {
        // BUG: Action column wasn't properly handling arrays
        // FIX: Action is now properly cast as JSON
        $user = TestUser::factory()->create();
        $post = DummyPost::create(['title' => 'Test']);

        $actions = ['view', 'edit', 'delete', 'publish'];

        $rule = PBACAccessControl::factory()
            ->allow()
            ->forUser($user)
            ->forResource(DummyPost::class, $post->id)
            ->withAction($actions)
            ->create();

        // Refresh from database
        $rule = $rule->fresh();

        $this->assertEquals($actions, $rule->action);
        $this->assertIsArray($rule->action);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function bugfix_super_admin_attribute_checked_before_pbac_evaluation(): void
    {
        // BUG: PBAC evaluation ran even for super admins (performance issue)
        // FIX: Super admin check happens first
        $superAdmin = TestUser::factory()->create(['is_super_admin' => true]);
        $post = DummyPost::create(['title' => 'Test']);

        // No rules exist, but super admin should still have access
        $this->assertTrue($superAdmin->can('view', $post));
        $this->assertTrue($superAdmin->can('anyAction', $post));
    }
}
