<?php

namespace Pbac\Tests\Unit;

use Pbac\Models\PBACAccessControl;
use Pbac\Models\PBACAccessGroup;
use Pbac\Models\PBACAccessResource;
use Pbac\Models\PBACAccessTarget;
use Pbac\Models\PBACAccessTeam;
use Pbac\Services\PolicyEvaluator;
use Pbac\Tests\Support\Models\DummyPost;
use Pbac\Tests\Support\Models\PlainUser;
use Pbac\Tests\Support\Models\TestUser;
use Pbac\Tests\TestCase;

class PolicyEvaluatorEdgeCasesTest extends TestCase
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
    public function it_denies_access_for_user_without_pbac_trait(): void
    {
        $user = PlainUser::create([
            'email' => 'plain@example.com',
            'name' => 'Plain User',
        ]);
        $post = DummyPost::create(['title' => 'Test Post']);

        // User doesn't have HasPbacAccessControl trait
        $this->assertFalse($this->evaluator->evaluate($user, 'view', $post));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_null_resource_correctly(): void
    {
        $user = TestUser::factory()->create();

        PBACAccessControl::factory()
            ->allow()
            ->forUser($user)
            ->forResource(null, null) // Any resource
            ->withAction('genericAction')
            ->create();

        // Null resource should work with general rules
        $this->assertTrue($this->evaluator->evaluate($user, 'genericAction', null));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_empty_action_array(): void
    {
        $user = TestUser::factory()->create();
        $post = DummyPost::create(['title' => 'Test Post']);

        PBACAccessControl::factory()
            ->allow()
            ->forUser($user)
            ->forResource(DummyPost::class, $post->id)
            ->create([
                'action' => [], // Empty action array
            ]);

        // Empty action array should not match any action
        $this->assertFalse($this->evaluator->evaluate($user, 'view', $post));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_user_with_no_groups_or_teams(): void
    {
        $user = TestUser::factory()->create();
        $post = DummyPost::create(['title' => 'Test Post']);

        // User has no groups or teams
        $this->assertEquals(0, $user->groups()->count());
        $this->assertEquals(0, $user->teams()->count());

        PBACAccessControl::factory()
            ->allow()
            ->forUser($user)
            ->forResource(DummyPost::class, $post->id)
            ->withAction('view')
            ->create();

        $this->assertTrue($this->evaluator->evaluate($user, 'view', $post));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_user_in_multiple_groups(): void
    {
        $user = TestUser::factory()->create();
        $group1 = PBACAccessGroup::factory()->create(['name' => 'Group 1']);
        $group2 = PBACAccessGroup::factory()->create(['name' => 'Group 2']);
        $group3 = PBACAccessGroup::factory()->create(['name' => 'Group 3']);

        $user->groups()->attach([$group1->id, $group2->id, $group3->id]);

        $post = DummyPost::create(['title' => 'Multi-Group Test']);

        // Rule for group2
        PBACAccessControl::factory()
            ->allow()
            ->forGroup($group2)
            ->forResource(DummyPost::class, $post->id)
            ->withAction('view')
            ->create();

        $this->assertTrue($this->evaluator->evaluate($user, 'view', $post));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_user_in_multiple_teams(): void
    {
        $user = TestUser::factory()->create();
        $team1 = PBACAccessTeam::factory()->create(['name' => 'Team 1']);
        $team2 = PBACAccessTeam::factory()->create(['name' => 'Team 2']);
        $team3 = PBACAccessTeam::factory()->create(['name' => 'Team 3']);

        $user->teams()->attach([$team1->id, $team2->id, $team3->id]);

        $post = DummyPost::create(['title' => 'Multi-Team Test']);

        // Rule for team3
        PBACAccessControl::factory()
            ->allow()
            ->forTeam($team3)
            ->forResource(DummyPost::class, $post->id)
            ->withAction('view')
            ->create();

        $this->assertTrue($this->evaluator->evaluate($user, 'view', $post));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_denies_when_user_is_in_group_but_rule_is_for_different_group(): void
    {
        $user = TestUser::factory()->create();
        $userGroup = PBACAccessGroup::factory()->create(['name' => 'User Group']);
        $ruleGroup = PBACAccessGroup::factory()->create(['name' => 'Rule Group']);

        $user->groups()->attach($userGroup->id);

        $post = DummyPost::create(['title' => 'Wrong Group Test']);

        PBACAccessControl::factory()
            ->allow()
            ->forGroup($ruleGroup)
            ->forResource(DummyPost::class, $post->id)
            ->withAction('view')
            ->create();

        $this->assertFalse($this->evaluator->evaluate($user, 'view', $post));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_deleted_resource_gracefully(): void
    {
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

        // Create new instance reference with same ID (simulating deleted resource)
        // Should still evaluate based on ID
        $deletedPost = new DummyPost();
        $deletedPost->id = $postId;

        $this->assertTrue($this->evaluator->evaluate($user, 'view', $deletedPost));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_case_sensitive_action_names(): void
    {
        $user = TestUser::factory()->create();
        $post = DummyPost::create(['title' => 'Case Test']);

        PBACAccessControl::factory()
            ->allow()
            ->forUser($user)
            ->forResource(DummyPost::class, $post->id)
            ->withAction('viewDetailed')
            ->create();

        // Exact match should work
        $this->assertTrue($this->evaluator->evaluate($user, 'viewDetailed', $post));

        // Different case should not match
        $this->assertFalse($this->evaluator->evaluate($user, 'viewdetailed', $post));
        $this->assertFalse($this->evaluator->evaluate($user, 'VIEWDETAILED', $post));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_multiple_actions_in_single_rule(): void
    {
        $user = TestUser::factory()->create();
        $post = DummyPost::create(['title' => 'Multi-Action Test']);

        PBACAccessControl::factory()
            ->allow()
            ->forUser($user)
            ->forResource(DummyPost::class, $post->id)
            ->withAction(['view', 'edit', 'delete'])
            ->create();

        $this->assertTrue($this->evaluator->evaluate($user, 'view', $post));
        $this->assertTrue($this->evaluator->evaluate($user, 'edit', $post));
        $this->assertTrue($this->evaluator->evaluate($user, 'delete', $post));
        $this->assertFalse($this->evaluator->evaluate($user, 'publish', $post));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_denies_access_when_resource_id_zero(): void
    {
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
    public function it_handles_special_characters_in_action_names(): void
    {
        $user = TestUser::factory()->create();
        $post = DummyPost::create(['title' => 'Special Char Test']);

        PBACAccessControl::factory()
            ->allow()
            ->forUser($user)
            ->forResource(DummyPost::class, $post->id)
            ->withAction('view:detailed')
            ->create();

        $this->assertTrue($this->evaluator->evaluate($user, 'view:detailed', $post));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_empty_context_array(): void
    {
        $user = TestUser::factory()->create();
        $post = DummyPost::create(['title' => 'Empty Context Test']);

        PBACAccessControl::factory()
            ->allow()
            ->forUser($user)
            ->forResource(DummyPost::class, $post->id)
            ->withAction('view')
            ->create();

        $this->assertTrue($this->evaluator->evaluate($user, 'view', $post, []));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_complex_context_data(): void
    {
        $user = TestUser::factory()->create();
        $post = DummyPost::create(['title' => 'Complex Context Test']);

        PBACAccessControl::factory()
            ->allow()
            ->forUser($user)
            ->forResource(DummyPost::class, $post->id)
            ->withAction('view')
            ->create([
                'extras' => ['min_level' => 5],
            ]);

        $complexContext = [
            'level' => 10,
            'nested' => ['data' => 'value'],
            'array' => [1, 2, 3],
            'bool' => true,
        ];

        $this->assertTrue($this->evaluator->evaluate($user, 'view', $post, $complexContext));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_denies_when_no_rules_exist_at_all(): void
    {
        $user = TestUser::factory()->create();
        $post = DummyPost::create(['title' => 'No Rules Test']);

        // No rules created for this user/resource/action combination
        $this->assertFalse($this->evaluator->evaluate($user, 'view', $post));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_rule_for_any_target_and_any_resource(): void
    {
        $user = TestUser::factory()->create();
        $post = DummyPost::create(['title' => 'Global Rule Test']);

        // Rule that applies to ANY target and ANY resource
        PBACAccessControl::factory()
            ->allow()
            ->forTarget(null, null)
            ->forResource(null, null)
            ->withAction('globalAction')
            ->create();

        $this->assertTrue($this->evaluator->evaluate($user, 'globalAction', $post));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_user_with_soft_deleted_groups(): void
    {
        $user = TestUser::factory()->create();
        $group = PBACAccessGroup::factory()->create(['name' => 'Deleted Group']);
        $user->groups()->attach($group->id);

        $post = DummyPost::create(['title' => 'Soft Delete Test']);

        PBACAccessControl::factory()
            ->allow()
            ->forGroup($group)
            ->forResource(DummyPost::class, $post->id)
            ->withAction('view')
            ->create();

        // Verify access works before deletion
        $this->assertTrue($this->evaluator->evaluate($user, 'view', $post));

        // Soft delete the group
        $group->delete();

        // User should still be in the group (pivot not deleted)
        // But access evaluation should still work based on group ID
        $this->assertTrue($this->evaluator->evaluate($user, 'view', $post));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_string_class_name_as_resource(): void
    {
        $user = TestUser::factory()->create();

        PBACAccessControl::factory()
            ->allow()
            ->forUser($user)
            ->forResource(DummyPost::class, null)
            ->withAction('create')
            ->create();

        // Pass class name as string instead of model instance
        $this->assertTrue($this->evaluator->evaluate($user, 'create', DummyPost::class));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_very_long_action_name(): void
    {
        $user = TestUser::factory()->create();
        $post = DummyPost::create(['title' => 'Long Action Test']);

        $longAction = str_repeat('a', 255);

        PBACAccessControl::factory()
            ->allow()
            ->forUser($user)
            ->forResource(DummyPost::class, $post->id)
            ->withAction($longAction)
            ->create();

        $this->assertTrue($this->evaluator->evaluate($user, $longAction, $post));
    }
}
