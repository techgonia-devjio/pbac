<?php

namespace Modules\Pbac\Tests\Unit;

use Illuminate\Support\Facades\Request;
use Modules\Pbac\Models\PBACAccessControl;
use Modules\Pbac\Models\PBACAccessResource;
use Modules\Pbac\Models\PBACAccessTarget;
use Modules\Pbac\Services\PolicyEvaluator;
use Modules\Pbac\Tests\Support\Models\DummyPost;
use Modules\Pbac\Tests\Support\Models\TestUser;
use Modules\Pbac\Tests\TestCase;

class PolicyEvaluatorConditionsTest extends TestCase
{
    use \Modules\Pbac\Tests\Support\Traits\MigrationLoader;

    protected PolicyEvaluator $evaluator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->evaluator = app(PolicyEvaluator::class);
        $this->initMigration();

        // Seed common resource and target types
        PBACAccessResource::firstOrCreate(['type' => DummyPost::class]);
        PBACAccessTarget::firstOrCreate(['type' => TestUser::class]);
    }

    protected function tearDown(): void
    {
        (new DummyPost())->getConnection()->getSchemaBuilder()->dropIfExists('dummy_posts');
        parent::tearDown();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_allows_access_when_min_level_condition_is_met(): void
    {
        $user = TestUser::factory()->create();
        $post = DummyPost::create(['title' => 'Level Test Post']);

        PBACAccessControl::factory()
            ->allow()
            ->forUser($user)
            ->forResource(DummyPost::class, $post->id)
            ->withAction('view')
            ->create([
                'extras' => ['min_level' => 5],
            ]);

        // Context with sufficient level
        $context = ['level' => 10];
        $this->assertTrue($this->evaluator->evaluate($user, 'view', $post, $context));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_denies_access_when_min_level_condition_is_not_met(): void
    {
        $user = TestUser::factory()->create();
        $post = DummyPost::create(['title' => 'Level Test Post']);

        PBACAccessControl::factory()
            ->allow()
            ->forUser($user)
            ->forResource(DummyPost::class, $post->id)
            ->withAction('view')
            ->create([
                'extras' => ['min_level' => 5],
            ]);

        // Context with insufficient level
        $context = ['level' => 3];
        $this->assertFalse($this->evaluator->evaluate($user, 'view', $post, $context));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_denies_access_when_min_level_is_required_but_not_provided_in_context(): void
    {
        $user = TestUser::factory()->create();
        $post = DummyPost::create(['title' => 'Level Test Post']);

        PBACAccessControl::factory()
            ->allow()
            ->forUser($user)
            ->forResource(DummyPost::class, $post->id)
            ->withAction('view')
            ->create([
                'extras' => ['min_level' => 5],
            ]);

        // No context provided
        $this->assertFalse($this->evaluator->evaluate($user, 'view', $post, []));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_allows_access_when_ip_is_in_allowed_ips_list(): void
    {
        $user = TestUser::factory()->create();
        $post = DummyPost::create(['title' => 'IP Test Post']);

        PBACAccessControl::factory()
            ->allow()
            ->forUser($user)
            ->forResource(DummyPost::class, $post->id)
            ->withAction('view')
            ->create([
                'extras' => ['allowed_ips' => ['192.168.1.100', '10.0.0.1']],
            ]);

        // Context with allowed IP
        $context = ['ip' => '192.168.1.100'];
        $this->assertTrue($this->evaluator->evaluate($user, 'view', $post, $context));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_denies_access_when_ip_is_not_in_allowed_ips_list(): void
    {
        $user = TestUser::factory()->create();
        $post = DummyPost::create(['title' => 'IP Test Post']);

        PBACAccessControl::factory()
            ->allow()
            ->forUser($user)
            ->forResource(DummyPost::class, $post->id)
            ->withAction('view')
            ->create([
                'extras' => ['allowed_ips' => ['192.168.1.100', '10.0.0.1']],
            ]);

        // Context with disallowed IP
        $context = ['ip' => '192.168.1.200'];
        $this->assertFalse($this->evaluator->evaluate($user, 'view', $post, $context));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_allows_access_when_ip_matches_cidr_range(): void
    {
        $user = TestUser::factory()->create();
        $post = DummyPost::create(['title' => 'CIDR Test Post']);

        PBACAccessControl::factory()
            ->allow()
            ->forUser($user)
            ->forResource(DummyPost::class, $post->id)
            ->withAction('view')
            ->create([
                'extras' => ['allowed_ips' => ['192.168.1.0/24']],
            ]);

        // IP within CIDR range
        $context = ['ip' => '192.168.1.50'];
        $this->assertTrue($this->evaluator->evaluate($user, 'view', $post, $context));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_denies_access_when_ip_is_outside_cidr_range(): void
    {
        $user = TestUser::factory()->create();
        $post = DummyPost::create(['title' => 'CIDR Test Post']);

        PBACAccessControl::factory()
            ->allow()
            ->forUser($user)
            ->forResource(DummyPost::class, $post->id)
            ->withAction('view')
            ->create([
                'extras' => ['allowed_ips' => ['192.168.1.0/24']],
            ]);

        // IP outside CIDR range
        $context = ['ip' => '192.168.2.50'];
        $this->assertFalse($this->evaluator->evaluate($user, 'view', $post, $context));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_allows_access_when_resource_attributes_match_requirements(): void
    {
        $user = TestUser::factory()->create();
        $post = DummyPost::create(['title' => 'Published Post', 'status' => 'published']);

        PBACAccessControl::factory()
            ->allow()
            ->forUser($user)
            ->forResource(DummyPost::class, null) // Any post
            ->withAction('view')
            ->create([
                'extras' => ['requires_attribute_value' => ['status' => 'published']],
            ]);

        $this->assertTrue($this->evaluator->evaluate($user, 'view', $post));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_denies_access_when_resource_attributes_dont_match_requirements(): void
    {
        $user = TestUser::factory()->create();
        $post = DummyPost::create(['title' => 'Draft Post', 'status' => 'draft']);

        PBACAccessControl::factory()
            ->allow()
            ->forUser($user)
            ->forResource(DummyPost::class, null) // Any post
            ->withAction('view')
            ->create([
                'extras' => ['requires_attribute_value' => ['status' => 'published']],
            ]);

        $this->assertFalse($this->evaluator->evaluate($user, 'view', $post));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_allows_access_when_multiple_conditions_are_all_met(): void
    {
        $user = TestUser::factory()->create();
        $post = DummyPost::create(['title' => 'Multi-Condition Post', 'status' => 'published']);

        PBACAccessControl::factory()
            ->allow()
            ->forUser($user)
            ->forResource(DummyPost::class, $post->id)
            ->withAction('view')
            ->create([
                'extras' => [
                    'min_level' => 5,
                    'allowed_ips' => ['192.168.1.0/24'],
                    'requires_attribute_value' => ['status' => 'published'],
                ],
            ]);

        $context = ['level' => 10, 'ip' => '192.168.1.50'];
        $this->assertTrue($this->evaluator->evaluate($user, 'view', $post, $context));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_denies_access_when_any_condition_in_multiple_conditions_fails(): void
    {
        $user = TestUser::factory()->create();
        $post = DummyPost::create(['title' => 'Multi-Condition Post', 'status' => 'published']);

        PBACAccessControl::factory()
            ->allow()
            ->forUser($user)
            ->forResource(DummyPost::class, $post->id)
            ->withAction('view')
            ->create([
                'extras' => [
                    'min_level' => 5,
                    'allowed_ips' => ['192.168.1.0/24'],
                    'requires_attribute_value' => ['status' => 'published'],
                ],
            ]);

        // Level is sufficient but IP is wrong
        $context = ['level' => 10, 'ip' => '10.0.0.1'];
        $this->assertFalse($this->evaluator->evaluate($user, 'view', $post, $context));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_allows_access_when_rule_has_no_conditions(): void
    {
        $user = TestUser::factory()->create();
        $post = DummyPost::create(['title' => 'Unrestricted Post']);

        PBACAccessControl::factory()
            ->allow()
            ->forUser($user)
            ->forResource(DummyPost::class, $post->id)
            ->withAction('view')
            ->create([
                'extras' => null, // No conditions
            ]);

        $this->assertTrue($this->evaluator->evaluate($user, 'view', $post, []));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_allows_access_when_rule_has_empty_extras_array(): void
    {
        $user = TestUser::factory()->create();
        $post = DummyPost::create(['title' => 'Unrestricted Post']);

        PBACAccessControl::factory()
            ->allow()
            ->forUser($user)
            ->forResource(DummyPost::class, $post->id)
            ->withAction('view')
            ->create([
                'extras' => [], // Empty array
            ]);

        $this->assertTrue($this->evaluator->evaluate($user, 'view', $post, []));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_denies_access_with_condition_even_if_resource_is_null(): void
    {
        $user = TestUser::factory()->create();

        PBACAccessControl::factory()
            ->allow()
            ->forUser($user)
            ->forResource(DummyPost::class, null) // Any DummyPost
            ->withAction('viewAny')
            ->create([
                'extras' => ['min_level' => 5],
            ]);

        // Level too low
        $context = ['level' => 2];
        $this->assertFalse($this->evaluator->evaluate($user, 'viewAny', DummyPost::class, $context));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_allows_access_with_exact_min_level_value(): void
    {
        $user = TestUser::factory()->create();
        $post = DummyPost::create(['title' => 'Exact Level Post']);

        PBACAccessControl::factory()
            ->allow()
            ->forUser($user)
            ->forResource(DummyPost::class, $post->id)
            ->withAction('view')
            ->create([
                'extras' => ['min_level' => 5],
            ]);

        // Exactly the minimum level
        $context = ['level' => 5];
        $this->assertTrue($this->evaluator->evaluate($user, 'view', $post, $context));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_multiple_attribute_requirements_correctly(): void
    {
        $user = TestUser::factory()->create();
        $post = DummyPost::create([
            'title' => 'Multi-Attr Post',
            'status' => 'published',
            'is_featured' => true,
        ]);

        PBACAccessControl::factory()
            ->allow()
            ->forUser($user)
            ->forResource(DummyPost::class, null)
            ->withAction('view')
            ->create([
                'extras' => [
                    'requires_attribute_value' => [
                        'status' => 'published',
                        'is_featured' => true,
                    ],
                ],
            ]);

        $this->assertTrue($this->evaluator->evaluate($user, 'view', $post));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_denies_when_one_of_multiple_attribute_requirements_fails(): void
    {
        $user = TestUser::factory()->create();
        $post = DummyPost::create([
            'title' => 'Multi-Attr Post',
            'status' => 'published',
            'is_featured' => false, // This doesn't match
        ]);

        PBACAccessControl::factory()
            ->allow()
            ->forUser($user)
            ->forResource(DummyPost::class, null)
            ->withAction('view')
            ->create([
                'extras' => [
                    'requires_attribute_value' => [
                        'status' => 'published',
                        'is_featured' => true,
                    ],
                ],
            ]);

        $this->assertFalse($this->evaluator->evaluate($user, 'view', $post));
    }
}
