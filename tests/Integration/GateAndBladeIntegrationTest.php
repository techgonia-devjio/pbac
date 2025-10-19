<?php

namespace Pbac\Tests\Integration;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Pbac\Models\PBACAccessControl;
use Pbac\Models\PBACAccessResource;
use Pbac\Models\PBACAccessTarget;
use Pbac\Tests\Support\Models\DummyPost;
use Pbac\Tests\Support\Models\TestUser;
use Pbac\Tests\TestCase;

class GateAndBladeIntegrationTest extends TestCase
{
    use \Pbac\Tests\Support\Traits\MigrationLoader;

    protected function setUp(): void
    {
        parent::setUp();
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
    public function it_integrates_with_laravel_gate_for_super_admin_bypass(): void
    {
        $superAdmin = TestUser::factory()->create([
            'is_super_admin' => true,
        ]);

        $post = DummyPost::create(['title' => 'Protected Post']);

        // No access rules defined, but super admin should bypass
        $this->assertTrue(Gate::forUser($superAdmin)->allows('view', $post));
        $this->assertTrue(Gate::forUser($superAdmin)->allows('edit', $post));
        $this->assertTrue(Gate::forUser($superAdmin)->allows('delete', $post));
        $this->assertTrue(Gate::forUser($superAdmin)->allows('anyAction', $post));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_integrates_with_laravel_gate_for_regular_users(): void
    {
        $user = TestUser::factory()->create(['is_super_admin' => false]);
        $post = DummyPost::create(['title' => 'Test Post']);

        // Create access control
        PBACAccessControl::factory()
            ->allow()
            ->forUser($user)
            ->forResource(DummyPost::class, $post->id)
            ->withAction('view')
            ->create();

        // Gate should use PBAC for permission check
        $this->assertTrue(Gate::forUser($user)->allows('view', $post));
        $this->assertFalse(Gate::forUser($user)->allows('edit', $post));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_denies_access_through_gate_when_no_permissions(): void
    {
        $user = TestUser::factory()->create();
        $post = DummyPost::create(['title' => 'Test Post']);

        // No access rules defined
        $this->assertFalse(Gate::forUser($user)->allows('view', $post));
        $this->assertTrue(Gate::forUser($user)->denies('view', $post));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_integrates_gate_check_with_user_can_method(): void
    {
        $user = TestUser::factory()->create();
        $post = DummyPost::create(['title' => 'Test Post']);

        PBACAccessControl::factory()
            ->allow()
            ->forUser($user)
            ->forResource(DummyPost::class, $post->id)
            ->withAction('edit')
            ->create();

        // Both Gate and user->can should work
        $this->assertTrue(Gate::forUser($user)->allows('edit', $post));
        $this->assertTrue($user->can('edit', $post));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_compiles_pbac_can_blade_directive_with_model_instance(): void
    {
        $blade = "@pbacCan('view', \$post) Content @endpbacCan";
        $compiled = Blade::compileString($blade);

        // Should compile to proper PHP
        $this->assertStringContainsString("auth()->check()", $compiled);
        $this->assertStringContainsString("auth()->user()->can('view', \$post)", $compiled);
        $this->assertStringContainsString("endif", $compiled);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_compiles_pbac_can_blade_directive_with_class_name(): void
    {
        $blade = "@pbacCan('create', App\\Models\\Post::class) Content @endpbacCan";
        $compiled = Blade::compileString($blade);

        // Should compile to proper PHP
        $this->assertStringContainsString("auth()->check()", $compiled);
        $this->assertStringContainsString("auth()->user()->can('create', App\\Models\\Post::class)", $compiled);
        $this->assertStringContainsString("endif", $compiled);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_properly_nests_pbac_can_directives(): void
    {
        $blade = "@pbacCan('view', \$post) Outer @pbacCan('edit', \$post) Inner @endpbacCan @endpbacCan";
        $compiled = Blade::compileString($blade);

        // Should have two if statements
        $this->assertEquals(2, substr_count($compiled, "auth()->check()"));
        $this->assertEquals(2, substr_count($compiled, "endif"));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_pbac_can_with_multiple_arguments(): void
    {
        $blade = "@pbacCan('view', \$post, ['context' => \$data]) Content @endpbacCan";
        $compiled = Blade::compileString($blade);

        // Should compile with all arguments
        $this->assertStringContainsString("auth()->check()", $compiled);
        $this->assertStringContainsString("auth()->user()->can", $compiled);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_integrates_gate_before_with_non_pbac_users(): void
    {
        // Create a plain user without PBAC trait (using PlainUser model)
        $plainUser = \Pbac\Tests\Support\Models\PlainUser::create([
            'name' => 'Plain User',
            'email' => 'plain@example.com',
        ]);

        $post = DummyPost::create(['title' => 'Test Post']);

        // Gate::before should return null for non-PBAC users, letting other checks proceed
        // Since there are no other policies/gates, it should ultimately deny
        $this->assertFalse(Gate::forUser($plainUser)->allows('view', $post));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_integrates_super_admin_bypass_through_user_can_method(): void
    {
        $superAdmin = TestUser::factory()->create([
            'is_super_admin' => true,
        ]);

        $post = DummyPost::create(['title' => 'Protected Post']);

        // Super admin bypasses all checks
        $this->assertTrue($superAdmin->can('view', $post));
        $this->assertTrue($superAdmin->can('edit', $post));
        $this->assertTrue($superAdmin->can('delete', $post));
        $this->assertTrue($superAdmin->can('publish', $post));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_respects_deny_rules_through_gate(): void
    {
        $user = TestUser::factory()->create();
        $post = DummyPost::create(['title' => 'Denied Post']);

        // Allow rule
        PBACAccessControl::factory()
            ->allow()
            ->forUser($user)
            ->forResource(DummyPost::class, $post->id)
            ->withAction('view')
            ->create();

        // Deny rule (should take precedence)
        PBACAccessControl::factory()
            ->deny()
            ->forUser($user)
            ->forResource(DummyPost::class, $post->id)
            ->withAction('view')
            ->create();

        // Gate should respect deny
        $this->assertFalse(Gate::forUser($user)->allows('view', $post));
        $this->assertTrue(Gate::forUser($user)->denies('view', $post));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_gate_any_check(): void
    {
        $user = TestUser::factory()->create();
        $post = DummyPost::create(['title' => 'Test Post']);

        PBACAccessControl::factory()
            ->allow()
            ->forUser($user)
            ->forResource(DummyPost::class, $post->id)
            ->withAction('view')
            ->create();

        // Any should pass if at least one ability is allowed
        $this->assertTrue(Gate::forUser($user)->any(['view', 'edit'], $post));
        $this->assertFalse(Gate::forUser($user)->any(['edit', 'delete'], $post));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_gate_none_check(): void
    {
        $user = TestUser::factory()->create();
        $post = DummyPost::create(['title' => 'Test Post']);

        PBACAccessControl::factory()
            ->allow()
            ->forUser($user)
            ->forResource(DummyPost::class, $post->id)
            ->withAction('view')
            ->create();

        // None should pass only if all abilities are denied
        $this->assertFalse(Gate::forUser($user)->none(['view', 'edit'], $post));
        $this->assertTrue(Gate::forUser($user)->none(['edit', 'delete'], $post));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_authorization_exception_on_denial(): void
    {
        $user = TestUser::factory()->create();
        $post = DummyPost::create(['title' => 'Protected Post']);

        // No permissions defined
        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);

        Gate::forUser($user)->authorize('view', $post);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_allows_authorization_when_permitted(): void
    {
        $user = TestUser::factory()->create();
        $post = DummyPost::create(['title' => 'Test Post']);

        PBACAccessControl::factory()
            ->allow()
            ->forUser($user)
            ->forResource(DummyPost::class, $post->id)
            ->withAction('view')
            ->create();

        // Should not throw exception
        Gate::forUser($user)->authorize('view', $post);

        $this->assertTrue(true); // If we reach here, authorization passed
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_works_with_gate_check_method(): void
    {
        $user = TestUser::factory()->create();
        $post = DummyPost::create(['title' => 'Test Post']);

        PBACAccessControl::factory()
            ->allow()
            ->forUser($user)
            ->forResource(DummyPost::class, $post->id)
            ->withAction('view')
            ->create();

        // Gate::check is an alias for allows
        $this->assertTrue(Gate::forUser($user)->check('view', $post));
        $this->assertFalse(Gate::forUser($user)->check('edit', $post));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_integrates_with_resource_class_permission_check(): void
    {
        $user = TestUser::factory()->create();

        // Permission to create any DummyPost
        PBACAccessControl::factory()
            ->allow()
            ->forUser($user)
            ->forResource(DummyPost::class, null)
            ->withAction('create')
            ->create();

        // Check permission using class name instead of instance
        $this->assertTrue(Gate::forUser($user)->allows('create', DummyPost::class));
        $this->assertTrue($user->can('create', DummyPost::class));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_properly_handles_gate_before_return_values(): void
    {
        // Super admin - Gate::before returns true
        $superAdmin = TestUser::factory()->create(['is_super_admin' => true]);
        $post = DummyPost::create(['title' => 'Post']);

        $this->assertTrue(Gate::forUser($superAdmin)->allows('anything', $post));

        // Regular user with permission - Gate::before returns null, proceeds to can() check
        $regularUser = TestUser::factory()->create();
        PBACAccessControl::factory()
            ->allow()
            ->forUser($regularUser)
            ->forResource(DummyPost::class, $post->id)
            ->withAction('view')
            ->create();

        $this->assertTrue(Gate::forUser($regularUser)->allows('view', $post));

        // Regular user without permission - Gate::before returns null, can() returns false
        $this->assertFalse(Gate::forUser($regularUser)->allows('delete', $post));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_context_based_permissions_through_gate(): void
    {
        $user = TestUser::factory()->create();
        $post = DummyPost::create(['title' => 'Context Post']);

        PBACAccessControl::factory()
            ->allow()
            ->forUser($user)
            ->forResource(DummyPost::class, $post->id)
            ->withAction('view')
            ->create([
                'extras' => ['min_level' => 5],
            ]);

        // Using can() with context (Gate doesn't directly support context, but can() does)
        $this->assertTrue($user->can('view', ['resource' => $post, 'context' => ['level' => 10]]));
        $this->assertFalse($user->can('view', ['resource' => $post, 'context' => ['level' => 3]]));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_works_with_multiple_concurrent_users(): void
    {
        $user1 = TestUser::factory()->create();
        $user2 = TestUser::factory()->create();
        $post = DummyPost::create(['title' => 'Shared Post']);

        // User 1 can view
        PBACAccessControl::factory()
            ->allow()
            ->forUser($user1)
            ->forResource(DummyPost::class, $post->id)
            ->withAction('view')
            ->create();

        // User 2 can edit
        PBACAccessControl::factory()
            ->allow()
            ->forUser($user2)
            ->forResource(DummyPost::class, $post->id)
            ->withAction('edit')
            ->create();

        // Check both users independently
        $this->assertTrue(Gate::forUser($user1)->allows('view', $post));
        $this->assertFalse(Gate::forUser($user1)->allows('edit', $post));

        $this->assertFalse(Gate::forUser($user2)->allows('view', $post));
        $this->assertTrue(Gate::forUser($user2)->allows('edit', $post));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_maintains_gate_functionality_with_trait_check(): void
    {
        $user = TestUser::factory()->create();
        $post = DummyPost::create(['title' => 'Test Post']);

        // Verify that Gate::before correctly checks for trait
        // User has trait, so it should proceed to PBAC evaluation
        $this->assertTrue(in_array(
            \Pbac\Traits\HasPbacAccessControl::class,
            class_uses($user)
        ));

        // Without permissions, should deny
        $this->assertFalse(Gate::forUser($user)->allows('view', $post));

        // With permissions, should allow
        PBACAccessControl::factory()
            ->allow()
            ->forUser($user)
            ->forResource(DummyPost::class, $post->id)
            ->withAction('view')
            ->create();

        $this->assertTrue(Gate::forUser($user)->allows('view', $post));
    }
}
