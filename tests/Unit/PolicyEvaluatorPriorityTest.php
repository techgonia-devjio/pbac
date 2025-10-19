<?php

namespace Modules\Pbac\Tests\Unit;

use Modules\Pbac\Models\PBACAccessControl;
use Modules\Pbac\Models\PBACAccessResource;
use Modules\Pbac\Models\PBACAccessTarget;
use Modules\Pbac\Services\PolicyEvaluator;
use Modules\Pbac\Tests\Support\Models\DummyPost;
use Modules\Pbac\Tests\Support\Models\TestUser;
use Modules\Pbac\Tests\TestCase;

class PolicyEvaluatorPriorityTest extends TestCase
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
    }

    protected function tearDown(): void
    {
        (new DummyPost())->getConnection()->getSchemaBuilder()->dropIfExists('dummy_posts');
        parent::tearDown();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_evaluates_deny_rules_before_allow_rules_regardless_of_priority(): void
    {
        $user = TestUser::factory()->create();
        $post = DummyPost::create(['title' => 'Priority Test']);

        // Allow rule with very high priority
        PBACAccessControl::factory()
            ->allow()
            ->forUser($user)
            ->forResource(DummyPost::class, $post->id)
            ->withAction('view')
            ->withPriority(1000)
            ->create();

        // Deny rule with low priority
        PBACAccessControl::factory()
            ->deny()
            ->forUser($user)
            ->forResource(DummyPost::class, $post->id)
            ->withAction('view')
            ->withPriority(1)
            ->create();

        // Deny always wins
        $this->assertFalse($this->evaluator->evaluate($user, 'view', $post));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_evaluates_higher_priority_deny_rules_first(): void
    {
        $user = TestUser::factory()->create();
        $post = DummyPost::create(['title' => 'Priority Test']);

        // Low priority deny with condition that would fail
        PBACAccessControl::factory()
            ->deny()
            ->forUser($user)
            ->forResource(DummyPost::class, $post->id)
            ->withAction('view')
            ->withPriority(1)
            ->create([
                'extras' => ['min_level' => 100], // Impossible condition
            ]);

        // High priority deny with no conditions
        PBACAccessControl::factory()
            ->deny()
            ->forUser($user)
            ->forResource(DummyPost::class, $post->id)
            ->withAction('view')
            ->withPriority(10)
            ->create();

        // High priority deny should be evaluated first and succeed
        $this->assertFalse($this->evaluator->evaluate($user, 'view', $post, ['level' => 1]));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_evaluates_higher_priority_allow_rules_first_when_no_denies(): void
    {
        $user = TestUser::factory()->create();
        $post = DummyPost::create(['title' => 'Priority Test']);

        // Low priority allow with no conditions
        PBACAccessControl::factory()
            ->allow()
            ->forUser($user)
            ->forResource(DummyPost::class, $post->id)
            ->withAction('view')
            ->withPriority(1)
            ->create();

        // High priority allow with impossible condition
        PBACAccessControl::factory()
            ->allow()
            ->forUser($user)
            ->forResource(DummyPost::class, $post->id)
            ->withAction('view')
            ->withPriority(10)
            ->create([
                'extras' => ['min_level' => 100],
            ]);

        // High priority is checked first, fails, then low priority succeeds
        $this->assertTrue($this->evaluator->evaluate($user, 'view', $post, ['level' => 1]));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_stops_at_first_matching_deny_rule(): void
    {
        $user = TestUser::factory()->create();
        $post = DummyPost::create(['title' => 'Priority Test']);

        // High priority deny
        PBACAccessControl::factory()
            ->deny()
            ->forUser($user)
            ->forResource(DummyPost::class, $post->id)
            ->withAction('view')
            ->withPriority(10)
            ->create();

        // Medium priority deny (should not be reached)
        PBACAccessControl::factory()
            ->deny()
            ->forUser($user)
            ->forResource(DummyPost::class, $post->id)
            ->withAction('view')
            ->withPriority(5)
            ->create();

        // Low priority deny (should not be reached)
        PBACAccessControl::factory()
            ->deny()
            ->forUser($user)
            ->forResource(DummyPost::class, $post->id)
            ->withAction('view')
            ->withPriority(1)
            ->create();

        // First deny rule should cause immediate denial
        $this->assertFalse($this->evaluator->evaluate($user, 'view', $post));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_stops_at_first_matching_allow_rule_when_no_denies(): void
    {
        $user = TestUser::factory()->create();
        $post = DummyPost::create(['title' => 'Priority Test']);

        // High priority allow
        PBACAccessControl::factory()
            ->allow()
            ->forUser($user)
            ->forResource(DummyPost::class, $post->id)
            ->withAction('view')
            ->withPriority(10)
            ->create();

        // Lower priority allow (should not be reached)
        PBACAccessControl::factory()
            ->allow()
            ->forUser($user)
            ->forResource(DummyPost::class, $post->id)
            ->withAction('view')
            ->withPriority(5)
            ->create();

        // First allow rule should grant access
        $this->assertTrue($this->evaluator->evaluate($user, 'view', $post));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_multiple_deny_rules_with_conditions_correctly(): void
    {
        $user = TestUser::factory()->create();
        $post = DummyPost::create(['title' => 'Priority Test']);

        // High priority deny with failing condition
        PBACAccessControl::factory()
            ->deny()
            ->forUser($user)
            ->forResource(DummyPost::class, $post->id)
            ->withAction('view')
            ->withPriority(10)
            ->create([
                'extras' => ['min_level' => 100],
            ]);

        // Medium priority deny with passing condition
        PBACAccessControl::factory()
            ->deny()
            ->forUser($user)
            ->forResource(DummyPost::class, $post->id)
            ->withAction('view')
            ->withPriority(5)
            ->create([
                'extras' => ['min_level' => 1],
            ]);

        // Low priority allow
        PBACAccessControl::factory()
            ->allow()
            ->forUser($user)
            ->forResource(DummyPost::class, $post->id)
            ->withAction('view')
            ->withPriority(1)
            ->create();

        // High priority deny fails, medium priority deny succeeds
        $this->assertFalse($this->evaluator->evaluate($user, 'view', $post, ['level' => 5]));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_allows_when_all_deny_rules_fail_conditions(): void
    {
        $user = TestUser::factory()->create();
        $post = DummyPost::create(['title' => 'Priority Test']);

        // High priority deny with failing condition
        PBACAccessControl::factory()
            ->deny()
            ->forUser($user)
            ->forResource(DummyPost::class, $post->id)
            ->withAction('view')
            ->withPriority(10)
            ->create([
                'extras' => ['min_level' => 100],
            ]);

        // Low priority deny with failing condition
        PBACAccessControl::factory()
            ->deny()
            ->forUser($user)
            ->forResource(DummyPost::class, $post->id)
            ->withAction('view')
            ->withPriority(1)
            ->create([
                'extras' => ['min_level' => 50],
            ]);

        // Allow rule
        PBACAccessControl::factory()
            ->allow()
            ->forUser($user)
            ->forResource(DummyPost::class, $post->id)
            ->withAction('view')
            ->withPriority(5)
            ->create();

        // All denies fail, allow succeeds
        $this->assertTrue($this->evaluator->evaluate($user, 'view', $post, ['level' => 10]));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_same_priority_rules_correctly(): void
    {
        $user = TestUser::factory()->create();
        $post = DummyPost::create(['title' => 'Same Priority Test']);

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

        // One of the deny rules should match
        $this->assertFalse($this->evaluator->evaluate($user, 'view', $post, ['level' => 5]));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_zero_and_negative_priorities(): void
    {
        $user = TestUser::factory()->create();
        $post = DummyPost::create(['title' => 'Priority Test']);

        // Negative priority allow
        PBACAccessControl::factory()
            ->allow()
            ->forUser($user)
            ->forResource(DummyPost::class, $post->id)
            ->withAction('view')
            ->withPriority(-10)
            ->create();

        // Zero priority allow
        PBACAccessControl::factory()
            ->allow()
            ->forUser($user)
            ->forResource(DummyPost::class, $post->id)
            ->withAction('view')
            ->withPriority(0)
            ->create();

        // Positive priority deny
        PBACAccessControl::factory()
            ->deny()
            ->forUser($user)
            ->forResource(DummyPost::class, $post->id)
            ->withAction('view')
            ->withPriority(1)
            ->create();

        // Deny should win
        $this->assertFalse($this->evaluator->evaluate($user, 'view', $post));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_very_large_priority_values(): void
    {
        $user = TestUser::factory()->create();
        $post = DummyPost::create(['title' => 'Large Priority Test']);

        // Very large priority allow
        PBACAccessControl::factory()
            ->allow()
            ->forUser($user)
            ->forResource(DummyPost::class, $post->id)
            ->withAction('view')
            ->withPriority(999999)
            ->create();

        // Even larger priority deny
        PBACAccessControl::factory()
            ->deny()
            ->forUser($user)
            ->forResource(DummyPost::class, $post->id)
            ->withAction('view')
            ->withPriority(1000000)
            ->create();

        // Deny should win (all denies checked first)
        $this->assertFalse($this->evaluator->evaluate($user, 'view', $post));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_evaluates_specific_rules_over_general_rules_with_priority(): void
    {
        $user = TestUser::factory()->create();
        $post = DummyPost::create(['title' => 'Specific vs General']);

        // General rule (any resource) with high priority
        PBACAccessControl::factory()
            ->allow()
            ->forUser($user)
            ->forResource(DummyPost::class, null) // Any post
            ->withAction('view')
            ->withPriority(100)
            ->create();

        // Specific rule (this resource) with low priority - DENY
        PBACAccessControl::factory()
            ->deny()
            ->forUser($user)
            ->forResource(DummyPost::class, $post->id)
            ->withAction('view')
            ->withPriority(1)
            ->create();

        // Specific deny should win
        $this->assertFalse($this->evaluator->evaluate($user, 'view', $post));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_allows_when_only_high_priority_allow_exists(): void
    {
        $user = TestUser::factory()->create();
        $post = DummyPost::create(['title' => 'High Priority Allow']);

        PBACAccessControl::factory()
            ->allow()
            ->forUser($user)
            ->forResource(DummyPost::class, $post->id)
            ->withAction('view')
            ->withPriority(1000)
            ->create();

        $this->assertTrue($this->evaluator->evaluate($user, 'view', $post));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_denies_when_only_high_priority_deny_exists(): void
    {
        $user = TestUser::factory()->create();
        $post = DummyPost::create(['title' => 'High Priority Deny']);

        PBACAccessControl::factory()
            ->deny()
            ->forUser($user)
            ->forResource(DummyPost::class, $post->id)
            ->withAction('view')
            ->withPriority(1000)
            ->create();

        $this->assertFalse($this->evaluator->evaluate($user, 'view', $post));
    }
}
