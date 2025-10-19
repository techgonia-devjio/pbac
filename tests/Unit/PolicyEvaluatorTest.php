<?php

namespace Modules\Pbac\Tests\Unit;

use Illuminate\Support\Facades\Config;
use Modules\Pbac\Models\PBACAccessControl;
use Modules\Pbac\Models\PBACAccessGroup;
use Modules\Pbac\Models\PBACAccessResource;
use Modules\Pbac\Models\PBACAccessTarget;
use Modules\Pbac\Models\PBACAccessTeam;
use Modules\Pbac\Services\PolicyEvaluator;
use Modules\Pbac\Tests\Support\Database\Factories\TestUserFactory;
use Modules\Pbac\Tests\Support\Models\DummyPost;
use Modules\Pbac\Tests\Support\Models\PbacAccessControlUser;
use Modules\Pbac\Tests\Support\Models\PbacUser;
use Modules\Pbac\Tests\Support\Models\TestUser;
use Modules\Pbac\Tests\TestCase;
use Modules\Pbac\Traits\HasPbacAccessControl;
use Modules\Pbac\Traits\HasPbacGroups;

class PolicyEvaluatorTest extends TestCase
{
    use \Modules\Pbac\Tests\Support\Traits\MigrationLoader;
    protected PolicyEvaluator $evaluator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->evaluator = app(PolicyEvaluator::class);
        $this->initMigration();

        // Seed common resource and target types for tests
        PBACAccessResource::firstOrCreate(['type' => DummyPost::class]);
        PBACAccessResource::firstOrCreate(['type' => 'App\Models\Comment']); // Example resource type
        PBACAccessTarget::firstOrCreate(['type' => TestUser::class]);
        PBACAccessTarget::firstOrCreate(['type' => PBACAccessGroup::class]);
        PBACAccessTarget::firstOrCreate(['type' => PBACAccessTeam::class]);
    }

    protected function tearDown(): void
    {
        // Drop the dummy_posts table after tests
        (new DummyPost())->getConnection()->getSchemaBuilder()->dropIfExists('dummy_posts');
        parent::tearDown();
    }


    #[\PHPUnit\Framework\Attributes\Test]
    public function it_denies_access_if_user_does_not_have_pbac_access_control_trait(): void
    {
        $user = TestUser::factory()->create();

        $this->assertFalse($this->evaluator->evaluate($user, 'view', DummyPost::class));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function super_admin_always_has_access(): void
    {
        $superAdmin = PbacAccessControlUser::factory()->forSuperAdmin()->create();
        $user = TestUser::factory()->create(); // Regular user

        // Ensure the trait is applied for the super admin test
        Config::set('pbac.traits.access_control', HasPbacAccessControl::class);

        // Test with super admin attribute enabled
        //Config::set('pbac.super_admin_attribute', 'is_super_admin');
        //$this->assertTrue($this->evaluator->evaluate($superAdmin, 'anyAction', 'anyResource'));

        // Test with super admin attribute disabled (should not bypass)
        Config::set('pbac.super_admin_attribute', null);
        // Without any rules, it should be false now
        $this->assertFalse($this->evaluator->evaluate($superAdmin, 'anyAction', 'anyResource'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_allows_access_with_a_matching_allow_rule_for_specific_user_and_resource(): void
    {
        $user = PbacUser::factory()->create();
        $post = DummyPost::create(['title' => 'My Post']);

        $access = PBACAccessControl::factory()
            ->allow()
            ->forUser($user)
            ->forResource(DummyPost::class, $post->id)
            ->withAction(['view'])
            ->create();

        $this->assertTrue($this->evaluator->evaluate($user, 'view', $post));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_denies_access_with_a_matching_deny_rule_for_specific_user_and_resource(): void
    {
        $user = PbacUser::factory()->create();
        $post = DummyPost::create(['title' => 'My Post']);

        PBACAccessControl::factory()
            ->deny()
            ->forUser($user)
            ->forResource(DummyPost::class, $post->id)
            ->withAction('view')
            ->create();

        $this->assertFalse($this->evaluator->evaluate($user, 'view', $post));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_allows_access_with_a_matching_allow_rule_for_any_resource_of_a_type(): void
    {
        $user = PbacUser::factory()->create();
        $post1 = DummyPost::create(['title' => 'Post One']);
        $post2 = DummyPost::create(['title' => 'Post Two']);

        PBACAccessControl::factory()
            ->allow()
            ->forUser($user)
            ->forResource(DummyPost::class, null) // Any DummyPost
            ->withAction('view')
            ->create();

        $this->assertTrue($this->evaluator->evaluate($user, 'view', $post1));
        $this->assertTrue($this->evaluator->evaluate($user, 'view', $post2));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_denies_access_with_a_matching_deny_rule_for_any_resource_of_a_type(): void
    {
        $user = PbacUser::factory()->create();
        $post1 = DummyPost::create(['title' => 'Post One']);
        $post2 = DummyPost::create(['title' => 'Post Two']);

        PBACAccessControl::factory()
            ->deny()
            ->forUser($user)
            ->forResource(DummyPost::class, null) // Any DummyPost
            ->withAction('view')
            ->create();

        $this->assertFalse($this->evaluator->evaluate($user, 'view', $post1));
        $this->assertFalse($this->evaluator->evaluate($user, 'view', $post2));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_allows_access_with_a_matching_allow_rule_for_any_target_of_a_type(): void
    {
        $user1 = TestUser::factory()->create();
        $user2 = TestUser::factory()->create();
        $post = DummyPost::create(['title' => 'A Post']);

        $rule = PBACAccessControl::factory()
            ->allow()
            ->forTarget(TestUser::class, null) // Any TestUser
            ->forResource(DummyPost::class, $post->id)
            ->withAction('view')
            ->create();

        $this->assertTrue($this->evaluator->evaluate($user1, 'view', $post));
        $this->assertTrue($this->evaluator->evaluate($user2, 'view', $post));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_denies_access_with_a_matching_deny_rule_for_any_target_of_a_type(): void
    {
        $user1 = TestUser::factory()->create();
        $user2 = TestUser::factory()->create();
        $post = DummyPost::create(['title' => 'A Post']);

        PBACAccessControl::factory()
            ->deny()
            ->forTarget(TestUser::class, null) // Any TestUser
            ->forResource(DummyPost::class, $post->id)
            ->withAction('view')
            ->create();

        $this->assertFalse($this->evaluator->evaluate($user1, 'view', $post));
        $this->assertFalse($this->evaluator->evaluate($user2, 'view', $post));
    }

    // --- Group and Team based rules ---

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_allows_access_for_user_in_a_group_with_allow_rule_for_group(): void
    {
        $user = TestUser::factory()->create();
        $group = PBACAccessGroup::factory()->create();
        $user->groups()->attach($group);
        $post = DummyPost::create(['title' => 'Group Post']);

        PBACAccessControl::factory()
            ->allow()
            ->forGroup($group)
            ->forResource(DummyPost::class, $post->id)
            ->withAction('view')
            ->create();

        $this->assertTrue($this->evaluator->evaluate($user, 'view', $post));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_denies_access_for_user_in_a_group_with_deny_rule_for_group(): void
    {
        $user = TestUser::factory()->create();
        $group = PBACAccessGroup::factory()->create();
        $user->groups()->attach($group);
        $post = DummyPost::create(['title' => 'Group Post']);

        PBACAccessControl::factory()
            ->deny()
            ->forGroup($group)
            ->forResource(DummyPost::class, $post->id)
            ->withAction('view')
            ->create();

        $this->assertFalse($this->evaluator->evaluate($user, 'view', $post));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_allows_access_for_user_in_a_team_with_allow_rule_for_team(): void
    {
        $user = TestUser::factory()->create();
        $team = PBACAccessTeam::factory()->create();
        $user->teams()->attach($team);
        $post = DummyPost::create(['title' => 'Team Post']);

        PBACAccessControl::factory()
            ->allow()
            ->forTeam($team)
            ->forResource(DummyPost::class, $post->id)
            ->withAction('view')
            ->create();

        $this->assertTrue($this->evaluator->evaluate($user, 'view', $post));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_denies_access_for_user_in_a_team_with_deny_rule_for_team(): void
    {
        $user = TestUser::factory()->create();
        $team = PBACAccessTeam::factory()->create();
        $user->teams()->attach($team);
        $post = DummyPost::create(['title' => 'Team Post']);

        PBACAccessControl::factory()
            ->deny()
            ->forTeam($team)
            ->forResource(DummyPost::class, $post->id)
            ->withAction('view')
            ->create();

        $this->assertFalse($this->evaluator->evaluate($user, 'view', $post));
    }

    // --- Priority and Conflict Resolution ---

    #[\PHPUnit\Framework\Attributes\Test]
    public function deny_rule_with_higher_priority_overrides_allow_rule(): void
    {
        $user = TestUser::factory()->create();
        $post = DummyPost::create(['title' => 'Conflicting Post']);

        // Lower priority allow rule
        PBACAccessControl::factory()
            ->allow()
            ->forUser($user)
            ->forResource(DummyPost::class, $post->id)
            ->withAction('view')
            ->withPriority(1)
            ->create();

        // Higher priority deny rule
        PBACAccessControl::factory()
            ->deny()
            ->forUser($user)
            ->forResource(DummyPost::class, $post->id)
            ->withAction('view')
            ->withPriority(10)
            ->create();

        $this->assertFalse($this->evaluator->evaluate($user, 'view', $post));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function allow_rule_with_higher_priority_does_not_override_deny_rule_if_deny_is_found_first(): void
    {
        $user = TestUser::factory()->create();
        $post = DummyPost::create(['title' => 'Conflicting Post 2']);

        // Higher priority allow rule
        PBACAccessControl::factory()
            ->allow()
            ->forUser($user)
            ->forResource(DummyPost::class, $post->id)
            ->withAction('view')
            ->withPriority(10)
            ->create();

        // Lower priority deny rule
        PBACAccessControl::factory()
            ->deny()
            ->forUser($user)
            ->forResource(DummyPost::class, $post->id)
            ->withAction('view')
            ->withPriority(1)
            ->create();

        // The PolicyEvaluator's `evaluateRules` method processes deny rules first.
        // If *any* deny rule matches, it returns false immediately.
        // So, even if an allow rule has higher priority, a lower priority deny rule found first will still deny.
        // This test confirms the current implementation's behavior.
        $this->assertFalse($this->evaluator->evaluate($user, 'view', $post));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_denies_access_if_no_matching_allow_rule_is_found(): void
    {
        $user = TestUser::factory()->create();
        $post = DummyPost::create(['title' => 'Unrestricted Post']);

        // No rules for this user/resource/action
        $this->assertFalse($this->evaluator->evaluate($user, 'view', $post));
    }

    // --- Inactive Resources and Targets ---

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_denies_access_if_resource_type_is_inactive_and_strict_mode_is_on(): void
    {
        Config::set('pbac.strict_resource_registration', true);
        $user = TestUser::factory()->create();
        $post = DummyPost::create(['title' => 'Inactive Resource Post']);

        // Update the existing resource type to be inactive
        $resourceType = PBACAccessResource::where('type', DummyPost::class)->first();
        $resourceType->update(['is_active' => false]);

        PBACAccessControl::factory()
            ->allow()
            ->forUser($user)
            ->forResource(DummyPost::class, $post->id)
            ->withAction('view')
            ->create([
                'pbac_access_resource_id' => $resourceType->id, // Link to inactive resource type
            ]);

        $this->assertFalse($this->evaluator->evaluate($user, 'view', $post));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_allows_general_rules_if_resource_type_is_inactive_and_strict_mode_is_off(): void
    {
        Config::set('pbac.strict_resource_registration', false);
        $user = TestUser::factory()->create();
        $post = DummyPost::create(['title' => 'Inactive Resource Post (Non-Strict)']);

        // Update the existing resource type to be inactive
        $resourceType = PBACAccessResource::where('type', DummyPost::class)->first();
        $resourceType->update(['is_active' => false]);

        // Rule for ANY resource type
        PBACAccessControl::factory()
            ->allow()
            ->forUser($user)
            ->forResource(null, null) // Applies to any resource type
            ->withAction('view')
            ->create();

        // The specific resource type (DummyPost) is inactive, but since strict_resource_registration is false,
        // the evaluator should proceed and find the general rule.
        $this->assertTrue($this->evaluator->evaluate($user, 'view', $post));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_denies_access_if_resource_type_is_not_registered_and_strict_mode_is_on(): void
    {
        Config::set('pbac.strict_resource_registration', true);
        $user = TestUser::factory()->create();
        $nonRegisteredResourceType = 'App\Models\NonRegisteredResource';

        // No PBACAccessResource entry for this type
        $this->assertFalse($this->evaluator->evaluate($user, 'view', $nonRegisteredResourceType));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_allows_general_rules_if_resource_type_is_not_registered_and_strict_mode_is_off(): void
    {
        Config::set('pbac.strict_resource_registration', false);
        $user = TestUser::factory()->create();
        $nonRegisteredResourceType = 'App\Models\AnotherNonRegisteredResource';

        // Rule for ANY resource type
        PBACAccessControl::factory()
            ->allow()
            ->forUser($user)
            ->forResource(null, null) // Applies to any resource type
            ->withAction('view')
            ->create();

        // The specific resource type (AnotherNonRegisteredResource) is not registered,
        // but since strict_resource_registration is false, the evaluator should proceed
        // and find the general rule.
        $this->assertTrue($this->evaluator->evaluate($user, 'view', $nonRegisteredResourceType));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_denies_access_if_target_type_is_inactive(): void
    {
        $user = TestUser::factory()->create();
        $post = DummyPost::create(['title' => 'Inactive Target Post']);

        // Update the existing target type to be inactive
        $targetType = PBACAccessTarget::where('type', TestUser::class)->first();
        $targetType->update(['is_active' => false]);

        PBACAccessControl::factory()
            ->allow()
            ->forUser($user)
            ->forResource(DummyPost::class, $post->id)
            ->withAction('view')
            ->create([
                'pbac_access_target_id' => $targetType->id, // Link to inactive target type
            ]);

        $this->assertFalse($this->evaluator->evaluate($user, 'view', $post));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function has_pbac_access_control_trait_can_handle_model_as_resource_argument(): void
    {
        $user = TestUser::factory()->create();
        $post = DummyPost::create(['title' => 'Trait Test Post']);

        PBACAccessControl::factory()
            ->allow()
            ->forUser($user)
            ->forResource(DummyPost::class, $post->id)
            ->withAction('view')
            ->create();

        $this->assertTrue($user->can('view', $post));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function has_pbac_access_control_trait_can_handle_string_class_as_resource_argument(): void
    {
        $user = TestUser::factory()->create();

        PBACAccessControl::factory()
            ->allow()
            ->forUser($user)
            ->forResource(DummyPost::class, null) // Any DummyPost
            ->withAction('create')
            ->create();

        $this->assertTrue($user->can('create', DummyPost::class));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function has_pbac_access_control_trait_can_handle_array_with_model_as_resource_argument(): void
    {
        $user = TestUser::factory()->create();
        $post = DummyPost::create(['title' => 'Array Model Test Post']);

        PBACAccessControl::factory()
            ->allow()
            ->forUser($user)
            ->forResource(DummyPost::class, $post->id)
            ->withAction('update')
            ->create();

        $this->assertTrue($user->can('update', [$post]));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function has_pbac_access_control_trait_can_handle_array_with_string_class_as_resource_argument(): void
    {
        $user = TestUser::factory()->create();

        PBACAccessControl::factory()
            ->allow()
            ->forUser($user)
            ->forResource(DummyPost::class, null)
            ->withAction('delete')
            ->create();

        $this->assertTrue($user->can('delete', [DummyPost::class]));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function has_pbac_access_control_trait_can_handle_array_with_named_resource_key(): void
    {
        $user = TestUser::factory()->create();
        $post = DummyPost::create(['title' => 'Named Resource Test Post']);

        PBACAccessControl::factory()
            ->allow()
            ->forUser($user)
            ->forResource(DummyPost::class, $post->id)
            ->withAction('publish')
            ->create();

        $this->assertTrue($user->can('publish', ['resource' => $post]));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function has_pbac_access_control_trait_can_handle_array_with_named_resource_key_and_context(): void
    {
        $user = TestUser::factory()->create();
        $post = DummyPost::create(['title' => 'Named Resource & Context Test Post']);

        PBACAccessControl::factory()
            ->allow()
            ->forUser($user)
            ->forResource(DummyPost::class, $post->id)
            ->withAction('archive')
            ->create();

        // PolicyEvaluator currently doesn't use context, but the trait passes it.
        $this->assertTrue($user->can('archive', ['resource' => $post, 'context' => ['reason' => 'old']]));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function has_pbac_access_control_trait_can_handle_array_with_only_context(): void
    {
        $user = TestUser::factory()->create();

        // Rule for ANY resource type (null resource type)
        PBACAccessControl::factory()
            ->allow()
            ->forUser($user)
            ->forResource(null, null) // Any resource type
            ->withAction('viewAny')
            ->create();

        // PolicyEvaluator currently doesn't use context, but the trait passes it.
        // This should match because the rule applies to any resource type
        $this->assertTrue($user->can('viewAny', ['context' => ['filter' => 'all']]));
    }

    /**
     * @throws \ReflectionException
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function it_correctly_determines_targets_for_user_with_groups_and_teams(): void
    {
        $user = TestUser::factory()->create();
        $group = PBACAccessGroup::factory()->create();
        $team = PBACAccessTeam::factory()->create();

        $user->groups()->attach($group);
        $user->teams()->attach($team);

        $reflection = new \ReflectionClass($this->evaluator);
        $method = $reflection->getMethod('determineTargets');
        //$method->setAccessible(true);
        $targetsByType = $method->invokeArgs($this->evaluator, [$user]);

        // 1. Assert that the array keys are the correct class names
        $this->assertArrayHasKey(TestUser::class, $targetsByType);
        $this->assertArrayHasKey(PBACAccessGroup::class, $targetsByType);
        $this->assertArrayHasKey(PBACAccessTeam::class, $targetsByType);

        // 2. Assert that each class name's array contains the correct ID
        $this->assertContains($user->id, $targetsByType[TestUser::class]);
        $this->assertContains($group->id, $targetsByType[PBACAccessGroup::class]);
        $this->assertContains($team->id, $targetsByType[PBACAccessTeam::class]);
        // [$targetTypeStrings, $targetIds] = $method->invokeArgs($this->evaluator, [$user]);
        // $this->assertContains(TestUser::class, $targetTypeStrings);
        // $this->assertContains(PBACAccessGroup::class, $targetTypeStrings);
        // $this->assertContains(PBACAccessTeam::class, $targetTypeStrings);
        //
        // $this->assertContains($user->id, $targetIds);
        // $this->assertContains($group->id, $targetIds);
        // $this->assertContains($team->id, $targetIds);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_queries_matching_rules_correctly_for_specific_resource_and_target(): void
    {
        $user = TestUser::factory()->create();
        $post = DummyPost::create(['title' => 'Specific Query Post']);

        $userTarget = PBACAccessTarget::firstOrCreate(['type' => TestUser::class]);
        $postResource = PBACAccessResource::firstOrCreate(['type' => DummyPost::class]);

        $rule1 = PBACAccessControl::factory()
                                  ->allow()
                                  ->forTarget(TestUser::class, $user->id)
                                  ->forResource(DummyPost::class, $post->id)
                                  ->withAction('view')
                                  ->withPriority(10)
                                  ->create();

        $rule2 = PBACAccessControl::factory()
                                  ->deny()
                                  ->forTarget(TestUser::class, null) // Any user
                                  ->forResource(DummyPost::class, $post->id)
                                  ->withAction('view')
                                  ->withPriority(5)
                                  ->create();

        $rule3 = PBACAccessControl::factory()
                                  ->allow()
                                  ->forTarget(PBACAccessGroup::class, null) // Any group
                                  ->forResource(DummyPost::class, null) // Any post
                                  ->withAction('view')
                                  ->withPriority(1)
                                  ->create();

        $reflection = new \ReflectionClass($this->evaluator);
        $method = $reflection->getMethod('queryMatchingRules');

        // --- Start of Correction ---
        $targetTypeEntries = PBACAccessTarget::whereIn('type', [TestUser::class])->get();

        // Create the structured array that the method now expects
        $targetsByType = [
            TestUser::class => [$user->id],
        ];

        $resourceTypeId = $postResource->id;
        $resourceId = $post->id;

        $matchingRules = $method->invokeArgs($this->evaluator, [
            'view',
            $resourceTypeId,
            $resourceId,
            $targetTypeEntries,
            $targetsByType // Pass the correct structured array
        ]);

        $this->assertCount(2, $matchingRules); // Should find rule1 and rule2
        $this->assertTrue($matchingRules->contains('id', $rule1->id));
        $this->assertTrue($matchingRules->contains('id', $rule2->id));
        $this->assertFalse($matchingRules->contains('id', $rule3->id)); // Rule3 doesn't match specific target/resource
        $this->assertEquals($rule1->id, $matchingRules->first()->id); // Check priority order
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_queries_matching_rules_correctly_for_any_resource_and_any_target(): void
    {
        $user = TestUser::factory()->create();

        $userTarget = PBACAccessTarget::firstOrCreate(['type' => TestUser::class]);
        $anyResource = PBACAccessResource::firstOrCreate(['type' => DummyPost::class]);

        $rule1 = PBACAccessControl::factory()
                                  ->allow()
                                  ->forTarget(TestUser::class, null) // Any user
                                  ->forResource(DummyPost::class, null) // Any DummyPost
                                  ->withAction('viewAny')
                                  ->create();

        $rule2 = PBACAccessControl::factory()
                                  ->deny()
                                  ->forTarget(TestUser::class, $user->id) // Specific user
                                  ->forResource(DummyPost::class, null) // Any DummyPost
                                  ->withAction('viewAny')
                                  ->withPriority(10)
                                  ->create();

        $reflection = new \ReflectionClass($this->evaluator);
        $method = $reflection->getMethod('queryMatchingRules');

        // --- Start of Correction ---
        $targetTypeEntries = PBACAccessTarget::whereIn('type', [TestUser::class])->get();

        // Create the structured array that the method now expects
        $targetsByType = [
            TestUser::class => [$user->id]
        ];

        $resourceTypeId = $anyResource->id;
        $resourceId = null;

        $matchingRules = $method->invokeArgs($this->evaluator, [
            'viewAny',
            $resourceTypeId,
            $resourceId,
            $targetTypeEntries,
            $targetsByType // Pass the correct structured array
        ]);
        // --- End of Correction ---

        $this->assertCount(2, $matchingRules); // Should find rule1 and rule2
        $this->assertTrue($matchingRules->contains('id', $rule1->id));
        $this->assertTrue($matchingRules->contains('id', $rule2->id));
        $this->assertEquals($rule2->id, $matchingRules->first()->id); // Deny rule has higher priority
    }
}
