<?php

namespace Pbac\Tests\Integration;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Gate;
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
 * Full System Integration Tests
 *
 * These tests verify the entire PBAC system works together:
 * - Database migrations
 * - Service provider registration
 * - Config loading
 * - Policy evaluation
 * - User authentication flows
 * - Complete workflows from start to finish
 */
class FullSystemIntegrationTest extends TestCase
{
    use \Pbac\Tests\Support\Traits\MigrationLoader;

    protected function setUp(): void
    {
        parent::setUp();
        $this->initMigration();
    }

    protected function tearDown(): void
    {
        (new DummyPost())->getConnection()->getSchemaBuilder()->dropIfExists('dummy_posts');
        parent::tearDown();
    }

    /**
     * INTEGRATION TEST: Complete user registration to content access workflow
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_complete_user_onboarding_workflow(): void
    {
        // Step 1: System setup - Register resource and target types
        $postResource = PBACAccessResource::create([
            'type' => DummyPost::class,
            'description' => 'Blog posts',
            'is_active' => true,
        ]);

        $userTarget = PBACAccessTarget::create([
            'type' => TestUser::class,
            'description' => 'Application users',
            'is_active' => true,
        ]);

        $groupTarget = PBACAccessTarget::create([
            'type' => PBACAccessGroup::class,
            'description' => 'User groups',
            'is_active' => true,
        ]);

        // Step 2: Create organizational structure
        $freeUsersGroup = PBACAccessGroup::create(['name' => 'Free Users', 'description' => 'Free tier']);
        $premiumUsersGroup = PBACAccessGroup::create(['name' => 'Premium Users', 'description' => 'Premium tier']);

        // Step 3: Define access rules
        // Free users can only view published posts
        PBACAccessControl::create([
            'pbac_access_target_id' => $groupTarget->id,
            'target_id' => $freeUsersGroup->id,
            'pbac_access_resource_id' => $postResource->id,
            'resource_id' => null,
            'action' => ['view'],
            'effect' => 'allow',
            'extras' => ['requires_attribute_value' => ['status' => 'published']],
            'priority' => 10,
        ]);

        // Premium users can view all posts and create new ones
        PBACAccessControl::create([
            'pbac_access_target_id' => $groupTarget->id,
            'target_id' => $premiumUsersGroup->id,
            'pbac_access_resource_id' => $postResource->id,
            'resource_id' => null,
            'action' => ['view', 'create'],
            'effect' => 'allow',
            'priority' => 10,
        ]);

        // Step 4: User registration - Create new users
        $freeUser = TestUser::create([
            'email' => 'free@example.com',
            'name' => 'Free User',
            'password' => bcrypt('password'),
        ]);

        $premiumUser = TestUser::create([
            'email' => 'premium@example.com',
            'name' => 'Premium User',
            'password' => bcrypt('password'),
        ]);

        // Step 5: Assign users to groups (onboarding)
        $freeUser->groups()->attach($freeUsersGroup->id);
        $premiumUser->groups()->attach($premiumUsersGroup->id);

        // Step 6: Create content
        $publishedPost = DummyPost::create(['title' => 'Published Post', 'status' => 'published']);
        $draftPost = DummyPost::create(['title' => 'Draft Post', 'status' => 'draft']);

        // Step 7: Verify access control works
        // Free user
        $this->assertTrue($freeUser->can('view', $publishedPost), 'Free user should view published posts');
        $this->assertFalse($freeUser->can('view', $draftPost), 'Free user should NOT view draft posts');
        $this->assertFalse($freeUser->can('create', DummyPost::class), 'Free user should NOT create posts');

        // Premium user
        $this->assertTrue($premiumUser->can('view', $publishedPost), 'Premium user should view published posts');
        $this->assertTrue($premiumUser->can('view', $draftPost), 'Premium user should view draft posts');
        $this->assertTrue($premiumUser->can('create', DummyPost::class), 'Premium user should create posts');

        // Step 8: User upgrade - Move free user to premium
        $freeUser->groups()->detach($freeUsersGroup->id);
        $freeUser->groups()->attach($premiumUsersGroup->id);
        $freeUser->refresh();

        // Step 9: Verify upgraded access
        $this->assertTrue($freeUser->can('view', $draftPost), 'Upgraded user should now view draft posts');
        $this->assertTrue($freeUser->can('create', DummyPost::class), 'Upgraded user should now create posts');
    }

    /**
     * INTEGRATION TEST: Multi-tenant application with team isolation
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_multi_tenant_application_with_complete_isolation(): void
    {
        // Setup resources and targets
        PBACAccessResource::firstOrCreate(['type' => DummyPost::class]);
        PBACAccessTarget::firstOrCreate(['type' => TestUser::class]);
        PBACAccessTarget::firstOrCreate(['type' => PBACAccessTeam::class]);

        // Create two companies (tenants)
        $companyA = PBACAccessTeam::create(['name' => 'Acme Corp', 'description' => 'Company A']);
        $companyB = PBACAccessTeam::create(['name' => 'Beta LLC', 'description' => 'Company B']);

        // Create users for each company
        $acmeAdmin = TestUser::create(['email' => 'admin@acme.com', 'name' => 'Acme Admin']);
        $acmeUser = TestUser::create(['email' => 'user@acme.com', 'name' => 'Acme User']);
        $betaAdmin = TestUser::create(['email' => 'admin@beta.com', 'name' => 'Beta Admin']);
        $betaUser = TestUser::create(['email' => 'user@beta.com', 'name' => 'Beta User']);

        // Assign users to teams
        $acmeAdmin->teams()->attach($companyA->id);
        $acmeUser->teams()->attach($companyA->id);
        $betaAdmin->teams()->attach($companyB->id);
        $betaUser->teams()->attach($companyB->id);

        // Create documents for each company
        $acmeDoc1 = DummyPost::create(['title' => 'Acme Q1 Report', 'user_id' => $acmeAdmin->id]);
        $acmeDoc2 = DummyPost::create(['title' => 'Acme Strategy', 'user_id' => $acmeUser->id]);
        $betaDoc1 = DummyPost::create(['title' => 'Beta Q1 Report', 'user_id' => $betaAdmin->id]);
        $betaDoc2 = DummyPost::create(['title' => 'Beta Strategy', 'user_id' => $betaUser->id]);

        // Define access rules: Team members can only access their team's documents
        $teamTarget = PBACAccessTarget::where('type', PBACAccessTeam::class)->first();
        $postResource = PBACAccessResource::where('type', DummyPost::class)->first();

        // Company A rules
        PBACAccessControl::create([
            'pbac_access_target_id' => $teamTarget->id,
            'target_id' => $companyA->id,
            'pbac_access_resource_id' => $postResource->id,
            'resource_id' => $acmeDoc1->id,
            'action' => ['view', 'edit'],
            'effect' => 'allow',
            'priority' => 10,
        ]);

        PBACAccessControl::create([
            'pbac_access_target_id' => $teamTarget->id,
            'target_id' => $companyA->id,
            'pbac_access_resource_id' => $postResource->id,
            'resource_id' => $acmeDoc2->id,
            'action' => ['view', 'edit'],
            'effect' => 'allow',
            'priority' => 10,
        ]);

        // Company B rules
        PBACAccessControl::create([
            'pbac_access_target_id' => $teamTarget->id,
            'target_id' => $companyB->id,
            'pbac_access_resource_id' => $postResource->id,
            'resource_id' => $betaDoc1->id,
            'action' => ['view', 'edit'],
            'effect' => 'allow',
            'priority' => 10,
        ]);

        PBACAccessControl::create([
            'pbac_access_target_id' => $teamTarget->id,
            'target_id' => $companyB->id,
            'pbac_access_resource_id' => $postResource->id,
            'resource_id' => $betaDoc2->id,
            'action' => ['view', 'edit'],
            'effect' => 'allow',
            'priority' => 10,
        ]);

        // Test isolation: Users can only access their own company's documents
        // Acme users
        $this->assertTrue($acmeAdmin->can('view', $acmeDoc1), 'Acme admin should access Acme doc 1');
        $this->assertTrue($acmeAdmin->can('view', $acmeDoc2), 'Acme admin should access Acme doc 2');
        $this->assertFalse($acmeAdmin->can('view', $betaDoc1), 'Acme admin should NOT access Beta doc 1');
        $this->assertFalse($acmeAdmin->can('view', $betaDoc2), 'Acme admin should NOT access Beta doc 2');

        $this->assertTrue($acmeUser->can('view', $acmeDoc1), 'Acme user should access Acme doc 1');
        $this->assertTrue($acmeUser->can('view', $acmeDoc2), 'Acme user should access Acme doc 2');
        $this->assertFalse($acmeUser->can('view', $betaDoc1), 'Acme user should NOT access Beta doc 1');
        $this->assertFalse($acmeUser->can('view', $betaDoc2), 'Acme user should NOT access Beta doc 2');

        // Beta users
        $this->assertTrue($betaAdmin->can('view', $betaDoc1), 'Beta admin should access Beta doc 1');
        $this->assertTrue($betaAdmin->can('view', $betaDoc2), 'Beta admin should access Beta doc 2');
        $this->assertFalse($betaAdmin->can('view', $acmeDoc1), 'Beta admin should NOT access Acme doc 1');
        $this->assertFalse($betaAdmin->can('view', $acmeDoc2), 'Beta admin should NOT access Acme doc 2');

        $this->assertTrue($betaUser->can('view', $betaDoc1), 'Beta user should access Beta doc 1');
        $this->assertTrue($betaUser->can('view', $betaDoc2), 'Beta user should access Beta doc 2');
        $this->assertFalse($betaUser->can('view', $acmeDoc1), 'Beta user should NOT access Acme doc 1');
        $this->assertFalse($betaUser->can('view', $acmeDoc2), 'Beta user should NOT access Acme doc 2');

        // Test complete data isolation
        $this->assertCount(2, $acmeAdmin->teams, 'Should handle team relationships');
        $this->assertCount(2, $betaAdmin->teams, 'Should handle team relationships');
    }

    /**
     * INTEGRATION TEST: Role-based access with hierarchical permissions
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_hierarchical_role_based_access_control(): void
    {
        // Setup
        PBACAccessResource::firstOrCreate(['type' => DummyPost::class]);
        PBACAccessTarget::firstOrCreate(['type' => TestUser::class]);
        PBACAccessTarget::firstOrCreate(['type' => PBACAccessGroup::class]);

        // Create role hierarchy: Admin > Moderator > User
        $admins = PBACAccessGroup::create(['name' => 'Administrators', 'description' => 'System admins']);
        $moderators = PBACAccessGroup::create(['name' => 'Moderators', 'description' => 'Content moderators']);
        $users = PBACAccessGroup::create(['name' => 'Users', 'description' => 'Regular users']);

        // Create users for each role
        $admin = TestUser::create(['email' => 'admin@site.com', 'name' => 'Admin']);
        $moderator = TestUser::create(['email' => 'mod@site.com', 'name' => 'Moderator']);
        $regularUser = TestUser::create(['email' => 'user@site.com', 'name' => 'User']);

        $admin->groups()->attach($admins->id);
        $moderator->groups()->attach($moderators->id);
        $regularUser->groups()->attach($users->id);

        // Create test content
        $post = DummyPost::create(['title' => 'Test Post', 'status' => 'published']);

        $groupTarget = PBACAccessTarget::where('type', PBACAccessGroup::class)->first();
        $postResource = PBACAccessResource::where('type', DummyPost::class)->first();

        // Define hierarchical rules
        // Users: Can only view
        PBACAccessControl::create([
            'pbac_access_target_id' => $groupTarget->id,
            'target_id' => $users->id,
            'pbac_access_resource_id' => $postResource->id,
            'resource_id' => null,
            'action' => ['view'],
            'effect' => 'allow',
            'priority' => 1,
        ]);

        // Moderators: Can view, edit, and delete
        PBACAccessControl::create([
            'pbac_access_target_id' => $groupTarget->id,
            'target_id' => $moderators->id,
            'pbac_access_resource_id' => $postResource->id,
            'resource_id' => null,
            'action' => ['view', 'edit', 'delete'],
            'effect' => 'allow',
            'priority' => 5,
        ]);

        // Admins: Can do everything including publish
        PBACAccessControl::create([
            'pbac_access_target_id' => $groupTarget->id,
            'target_id' => $admins->id,
            'pbac_access_resource_id' => $postResource->id,
            'resource_id' => null,
            'action' => ['view', 'edit', 'delete', 'publish', 'restore'],
            'effect' => 'allow',
            'priority' => 10,
        ]);

        // Verify hierarchical access
        // Regular user
        $this->assertTrue($regularUser->can('view', $post), 'User can view');
        $this->assertFalse($regularUser->can('edit', $post), 'User cannot edit');
        $this->assertFalse($regularUser->can('delete', $post), 'User cannot delete');
        $this->assertFalse($regularUser->can('publish', $post), 'User cannot publish');

        // Moderator
        $this->assertTrue($moderator->can('view', $post), 'Moderator can view');
        $this->assertTrue($moderator->can('edit', $post), 'Moderator can edit');
        $this->assertTrue($moderator->can('delete', $post), 'Moderator can delete');
        $this->assertFalse($moderator->can('publish', $post), 'Moderator cannot publish');

        // Admin
        $this->assertTrue($admin->can('view', $post), 'Admin can view');
        $this->assertTrue($admin->can('edit', $post), 'Admin can edit');
        $this->assertTrue($admin->can('delete', $post), 'Admin can delete');
        $this->assertTrue($admin->can('publish', $post), 'Admin can publish');
        $this->assertTrue($admin->can('restore', $post), 'Admin can restore');

        // Test role changes
        $regularUser->groups()->detach($users->id);
        $regularUser->groups()->attach($moderators->id);
        $regularUser->refresh();

        $this->assertTrue($regularUser->can('edit', $post), 'Promoted user can now edit');
        $this->assertTrue($regularUser->can('delete', $post), 'Promoted user can now delete');
    }

    /**
     * INTEGRATION TEST: Complex permission with deny overrides
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_complex_permissions_with_explicit_denies(): void
    {
        PBACAccessResource::firstOrCreate(['type' => DummyPost::class]);
        PBACAccessTarget::firstOrCreate(['type' => TestUser::class]);
        PBACAccessTarget::firstOrCreate(['type' => PBACAccessGroup::class]);

        // Create groups
        $allStaff = PBACAccessGroup::create(['name' => 'All Staff']);
        $suspended = PBACAccessGroup::create(['name' => 'Suspended']);

        // Create user in both groups (staff but suspended)
        $user = TestUser::create(['email' => 'suspended.staff@example.com', 'name' => 'Suspended Staff']);
        $user->groups()->attach([$allStaff->id, $suspended->id]);

        $post = DummyPost::create(['title' => 'Company Post']);

        $groupTarget = PBACAccessTarget::where('type', PBACAccessGroup::class)->first();
        $postResource = PBACAccessResource::where('type', DummyPost::class)->first();

        // Rule 1: All staff can view posts
        PBACAccessControl::create([
            'pbac_access_target_id' => $groupTarget->id,
            'target_id' => $allStaff->id,
            'pbac_access_resource_id' => $postResource->id,
            'resource_id' => null,
            'action' => ['view', 'edit'],
            'effect' => 'allow',
            'priority' => 5,
        ]);

        // Rule 2: Suspended users are explicitly denied
        PBACAccessControl::create([
            'pbac_access_target_id' => $groupTarget->id,
            'target_id' => $suspended->id,
            'pbac_access_resource_id' => $postResource->id,
            'resource_id' => null,
            'action' => ['view', 'edit', 'delete'],
            'effect' => 'deny',
            'priority' => 10,
        ]);

        // Deny should override allow
        $this->assertFalse($user->can('view', $post), 'Suspended user denied despite staff membership');
        $this->assertFalse($user->can('edit', $post), 'Suspended user cannot edit');

        // Unsuspend user
        $user->groups()->detach($suspended->id);
        $user->refresh();

        // Now should have access
        $this->assertTrue($user->can('view', $post), 'Unsuspended user can now view');
        $this->assertTrue($user->can('edit', $post), 'Unsuspended user can now edit');
    }

    /**
     * INTEGRATION TEST: Dynamic permission changes and caching
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_dynamic_permission_changes_in_real_time(): void
    {
        PBACAccessResource::firstOrCreate(['type' => DummyPost::class]);
        PBACAccessTarget::firstOrCreate(['type' => TestUser::class]);

        $user = TestUser::create(['email' => 'user@example.com', 'name' => 'Test User']);
        $post = DummyPost::create(['title' => 'Dynamic Post']);

        $userTarget = PBACAccessTarget::where('type', TestUser::class)->first();
        $postResource = PBACAccessResource::where('type', DummyPost::class)->first();

        // Initially no permissions
        $this->assertFalse($user->can('view', $post), 'User has no permissions initially');

        // Grant permission dynamically
        $rule = PBACAccessControl::create([
            'pbac_access_target_id' => $userTarget->id,
            'target_id' => $user->id,
            'pbac_access_resource_id' => $postResource->id,
            'resource_id' => $post->id,
            'action' => ['view'],
            'effect' => 'allow',
            'priority' => 10,
        ]);

        // Should immediately have access
        $this->assertTrue($user->can('view', $post), 'User has access after rule creation');

        // Update rule to add more actions
        $rule->update(['action' => ['view', 'edit', 'delete']]);

        // Should have new permissions
        $this->assertTrue($user->can('edit', $post), 'User has edit access after rule update');
        $this->assertTrue($user->can('delete', $post), 'User has delete access after rule update');

        // Revoke permission
        $rule->delete();

        // Should lose access
        $this->assertFalse($user->can('view', $post), 'User loses access after rule deletion');
        $this->assertFalse($user->can('edit', $post), 'User cannot edit after rule deletion');
    }

    /**
     * INTEGRATION TEST: Service provider and configuration integration
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function it_integrates_with_service_provider_and_configuration(): void
    {
        // Verify service provider registered services
        $this->assertTrue($this->app->bound(PolicyEvaluator::class), 'PolicyEvaluator should be bound');
        $this->assertTrue($this->app->bound(\Pbac\Support\PbacLogger::class), 'PbacLogger should be bound');
        $this->assertTrue($this->app->bound(\Pbac\Services\PbacService::class), 'PbacService should be bound');

        // Verify singletons
        $evaluator1 = app(PolicyEvaluator::class);
        $evaluator2 = app(PolicyEvaluator::class);
        $this->assertSame($evaluator1, $evaluator2, 'PolicyEvaluator should be singleton');

        // Verify configuration
        $this->assertNotNull(Config::get('pbac.user_model'), 'Config pbac.user_model should exist');
        $this->assertNotNull(Config::get('pbac.super_admin_attribute'), 'Config pbac.super_admin_attribute should exist');
        $this->assertIsArray(Config::get('pbac.traits'), 'Config pbac.traits should be array');
        $this->assertIsArray(Config::get('pbac.models'), 'Config pbac.models should be array');
        $this->assertIsArray(Config::get('pbac.supported_actions'), 'Config pbac.supported_actions should be array');

        // Verify trait configs
        $this->assertEquals(
            \Pbac\Traits\HasPbacAccessControl::class,
            Config::get('pbac.traits.access_control'),
            'Access control trait should be configured'
        );
    }

    /**
     * INTEGRATION TEST: Database relationships and cascading deletes
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_database_relationships_and_cascading_operations(): void
    {
        PBACAccessResource::firstOrCreate(['type' => DummyPost::class]);
        PBACAccessTarget::firstOrCreate(['type' => TestUser::class]);
        PBACAccessTarget::firstOrCreate(['type' => PBACAccessGroup::class]);

        $group = PBACAccessGroup::create(['name' => 'Test Group']);
        $user = TestUser::create(['email' => 'test@example.com', 'name' => 'Test']);
        $user->groups()->attach($group->id);

        $post = DummyPost::create(['title' => 'Test Post']);

        $groupTarget = PBACAccessTarget::where('type', PBACAccessGroup::class)->first();
        $postResource = PBACAccessResource::where('type', DummyPost::class)->first();

        // Create rules
        $rule = PBACAccessControl::create([
            'pbac_access_target_id' => $groupTarget->id,
            'target_id' => $group->id,
            'pbac_access_resource_id' => $postResource->id,
            'resource_id' => $post->id,
            'action' => ['view'],
            'effect' => 'allow',
            'priority' => 10,
        ]);

        // Verify relationships exist
        $this->assertNotNull($rule->targetType, 'Rule should have targetType relationship');
        $this->assertNotNull($rule->resourceType, 'Rule should have resourceType relationship');
        $this->assertEquals(PBACAccessGroup::class, $rule->targetType->type);

        // Test user-group relationship
        $this->assertCount(1, $user->groups, 'User should have 1 group');
        $this->assertEquals('Test Group', $user->groups->first()->name);

        // Test group-user relationship
        $this->assertCount(1, $group->users, 'Group should have 1 user');

        // Delete group - should detach users
        $group->delete();
        $user->refresh();

        // Verify cascading behavior
        $this->assertCount(0, $user->groups, 'User should have no groups after group deletion');
    }

    /**
     * INTEGRATION TEST: Super admin bypass integration
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function it_integrates_super_admin_bypass_with_gate(): void
    {
        Config::set('pbac.super_admin_attribute', 'is_super_admin');

        PBACAccessResource::firstOrCreate(['type' => DummyPost::class]);
        PBACAccessTarget::firstOrCreate(['type' => TestUser::class]);

        $superAdmin = TestUser::create([
            'email' => 'super@example.com',
            'name' => 'Super Admin',
            'is_super_admin' => true,
        ]);

        $regularUser = TestUser::create([
            'email' => 'user@example.com',
            'name' => 'Regular User',
            'is_super_admin' => false,
        ]);

        $post = DummyPost::create(['title' => 'Protected Post']);

        // No rules created - regular user should be denied
        $this->assertFalse($regularUser->can('view', $post), 'Regular user denied without rules');
        $this->assertFalse($regularUser->can('edit', $post), 'Regular user denied without rules');
        $this->assertFalse($regularUser->can('delete', $post), 'Regular user denied without rules');

        // Super admin bypasses all checks
        $this->assertTrue($superAdmin->can('view', $post), 'Super admin can view without rules');
        $this->assertTrue($superAdmin->can('edit', $post), 'Super admin can edit without rules');
        $this->assertTrue($superAdmin->can('delete', $post), 'Super admin can delete without rules');
        $this->assertTrue($superAdmin->can('any-action', $post), 'Super admin can perform any action');
    }
}
