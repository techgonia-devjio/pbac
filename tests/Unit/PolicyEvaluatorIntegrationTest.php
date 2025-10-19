<?php

namespace Modules\Pbac\Tests\Unit;

use Illuminate\Support\Facades\Config;
use Modules\Pbac\Models\PBACAccessControl;
use Modules\Pbac\Models\PBACAccessGroup;
use Modules\Pbac\Models\PBACAccessResource;
use Modules\Pbac\Models\PBACAccessTarget;
use Modules\Pbac\Models\PBACAccessTeam;
use Modules\Pbac\Services\PolicyEvaluator;
use Modules\Pbac\Tests\Support\Models\DummyPost;
use Modules\Pbac\Tests\Support\Models\TestUser;
use Modules\Pbac\Tests\TestCase;

class PolicyEvaluatorIntegrationTest extends TestCase
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

    /**
     * Real-world scenario: Blog platform with editors and contributors
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_blog_platform_editor_scenario(): void
    {
        // Setup: Create groups
        $editors = PBACAccessGroup::factory()->create(['name' => 'Editors']);
        $contributors = PBACAccessGroup::factory()->create(['name' => 'Contributors']);

        // Create users
        $editor = TestUser::factory()->create(['email' => 'editor@blog.com']);
        $contributor = TestUser::factory()->create(['email' => 'contributor@blog.com']);

        $editor->groups()->attach($editors->id);
        $contributor->groups()->attach($contributors->id);

        // Create posts
        $publishedPost = DummyPost::create(['title' => 'Published', 'status' => 'published']);
        $draftPost = DummyPost::create(['title' => 'Draft', 'status' => 'draft']);

        // Rules: Editors can view/edit any post
        PBACAccessControl::factory()
            ->allow()
            ->forGroup($editors)
            ->forResource(DummyPost::class, null)
            ->withAction(['view', 'edit', 'delete', 'publish'])
            ->create();

        // Rules: Contributors can only view published posts
        PBACAccessControl::factory()
            ->allow()
            ->forGroup($contributors)
            ->forResource(DummyPost::class, null)
            ->withAction('view')
            ->create([
                'extras' => ['requires_attribute_value' => ['status' => 'published']],
            ]);

        // Assertions
        $this->assertTrue($this->evaluator->evaluate($editor, 'view', $publishedPost));
        $this->assertTrue($this->evaluator->evaluate($editor, 'view', $draftPost));
        $this->assertTrue($this->evaluator->evaluate($editor, 'edit', $publishedPost));
        $this->assertTrue($this->evaluator->evaluate($editor, 'delete', $draftPost));

        $this->assertTrue($this->evaluator->evaluate($contributor, 'view', $publishedPost));
        $this->assertFalse($this->evaluator->evaluate($contributor, 'view', $draftPost));
        $this->assertFalse($this->evaluator->evaluate($contributor, 'edit', $publishedPost));
    }

    /**
     * Real-world scenario: Multi-tenant SaaS application
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_multi_tenant_saas_scenario(): void
    {
        // Setup: Create teams (tenants)
        $companyA = PBACAccessTeam::factory()->create(['name' => 'Company A']);
        $companyB = PBACAccessTeam::factory()->create(['name' => 'Company B']);

        // Create users
        $userA = TestUser::factory()->create(['email' => 'user@companya.com']);
        $userB = TestUser::factory()->create(['email' => 'user@companyb.com']);

        $userA->teams()->attach($companyA->id);
        $userB->teams()->attach($companyB->id);

        // Create documents
        $docA = DummyPost::create(['title' => 'Company A Doc']);
        $docB = DummyPost::create(['title' => 'Company B Doc']);

        // Rule: Team members can only access their own team's documents
        PBACAccessControl::factory()
            ->allow()
            ->forTeam($companyA)
            ->forResource(DummyPost::class, $docA->id)
            ->withAction(['view', 'edit'])
            ->create();

        PBACAccessControl::factory()
            ->allow()
            ->forTeam($companyB)
            ->forResource(DummyPost::class, $docB->id)
            ->withAction(['view', 'edit'])
            ->create();

        // Assertions: Users can only access their team's documents
        $this->assertTrue($this->evaluator->evaluate($userA, 'view', $docA));
        $this->assertFalse($this->evaluator->evaluate($userA, 'view', $docB));

        $this->assertTrue($this->evaluator->evaluate($userB, 'view', $docB));
        $this->assertFalse($this->evaluator->evaluate($userB, 'view', $docA));
    }

    /**
     * Real-world scenario: IP-restricted admin access
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_ip_restricted_admin_access(): void
    {
        $admin = TestUser::factory()->create(['email' => 'admin@example.com']);
        $adminGroup = PBACAccessGroup::factory()->create(['name' => 'Admins']);
        $admin->groups()->attach($adminGroup->id);

        $post = DummyPost::create(['title' => 'Sensitive Data']);

        // Rule: Admins can delete, but only from office IPs
        PBACAccessControl::factory()
            ->allow()
            ->forGroup($adminGroup)
            ->forResource(DummyPost::class, null)
            ->withAction('delete')
            ->create([
                'extras' => ['allowed_ips' => ['10.0.0.0/24', '192.168.1.100']],
            ]);

        // From office IP
        $this->assertTrue($this->evaluator->evaluate($admin, 'delete', $post, ['ip' => '10.0.0.50']));
        $this->assertTrue($this->evaluator->evaluate($admin, 'delete', $post, ['ip' => '192.168.1.100']));

        // From home/external IP
        $this->assertFalse($this->evaluator->evaluate($admin, 'delete', $post, ['ip' => '203.0.113.1']));
    }

    /**
     * Real-world scenario: Hierarchical permissions with priority
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_hierarchical_permissions_with_deny_override(): void
    {
        // Setup: Create user and groups
        $user = TestUser::factory()->create(['email' => 'user@example.com']);
        $allUsers = PBACAccessGroup::factory()->create(['name' => 'All Users']);
        $restrictedUsers = PBACAccessGroup::factory()->create(['name' => 'Restricted Users']);

        $user->groups()->attach([$allUsers->id, $restrictedUsers->id]);

        $sensitivePost = DummyPost::create(['title' => 'Sensitive Info']);

        // General rule: All users can view posts
        PBACAccessControl::factory()
            ->allow()
            ->forGroup($allUsers)
            ->forResource(DummyPost::class, null)
            ->withAction('view')
            ->withPriority(1)
            ->create();

        // Specific rule: Restricted users CANNOT view sensitive posts
        PBACAccessControl::factory()
            ->deny()
            ->forGroup($restrictedUsers)
            ->forResource(DummyPost::class, $sensitivePost->id)
            ->withAction('view')
            ->withPriority(10)
            ->create();

        // Deny should override allow
        $this->assertFalse($this->evaluator->evaluate($user, 'view', $sensitivePost));

        // But user can view other posts
        $normalPost = DummyPost::create(['title' => 'Normal Post']);
        $this->assertTrue($this->evaluator->evaluate($user, 'view', $normalPost));
    }

    /**
     * Real-world scenario: Time-based access control with level
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_level_based_content_access(): void
    {
        $user = TestUser::factory()->create(['email' => 'user@example.com']);

        $basicPost = DummyPost::create(['title' => 'Basic Content']);
        $premiumPost = DummyPost::create(['title' => 'Premium Content']);
        $vipPost = DummyPost::create(['title' => 'VIP Content']);

        // Basic: Level 1+
        PBACAccessControl::factory()
            ->allow()
            ->forUser($user)
            ->forResource(DummyPost::class, $basicPost->id)
            ->withAction('view')
            ->create([
                'extras' => ['min_level' => 1],
            ]);

        // Premium: Level 5+
        PBACAccessControl::factory()
            ->allow()
            ->forUser($user)
            ->forResource(DummyPost::class, $premiumPost->id)
            ->withAction('view')
            ->create([
                'extras' => ['min_level' => 5],
            ]);

        // VIP: Level 10+
        PBACAccessControl::factory()
            ->allow()
            ->forUser($user)
            ->forResource(DummyPost::class, $vipPost->id)
            ->withAction('view')
            ->create([
                'extras' => ['min_level' => 10],
            ]);

        // User with level 5
        $context = ['level' => 5];

        $this->assertTrue($this->evaluator->evaluate($user, 'view', $basicPost, $context));
        $this->assertTrue($this->evaluator->evaluate($user, 'view', $premiumPost, $context));
        $this->assertFalse($this->evaluator->evaluate($user, 'view', $vipPost, $context));
    }

    /**
     * Real-world scenario: Owner-based permissions
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_owner_based_permissions(): void
    {
        $owner = TestUser::factory()->create(['email' => 'owner@example.com']);
        $otherUser = TestUser::factory()->create(['email' => 'other@example.com']);

        $ownedPost = DummyPost::create(['title' => 'My Post', 'user_id' => $owner->id]);

        // Rule: User can edit their own posts
        PBACAccessControl::factory()
            ->allow()
            ->forUser($owner)
            ->forResource(DummyPost::class, $ownedPost->id)
            ->withAction(['view', 'edit', 'delete'])
            ->create();

        // Owner can do everything
        $this->assertTrue($this->evaluator->evaluate($owner, 'view', $ownedPost));
        $this->assertTrue($this->evaluator->evaluate($owner, 'edit', $ownedPost));
        $this->assertTrue($this->evaluator->evaluate($owner, 'delete', $ownedPost));

        // Other user cannot
        $this->assertFalse($this->evaluator->evaluate($otherUser, 'view', $ownedPost));
        $this->assertFalse($this->evaluator->evaluate($otherUser, 'edit', $ownedPost));
    }

    /**
     * Real-world scenario: Complex team + group + individual rules
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_complex_team_group_individual_rules(): void
    {
        // Create organizational structure
        $engineering = PBACAccessTeam::factory()->create(['name' => 'Engineering']);
        $admins = PBACAccessGroup::factory()->create(['name' => 'Admins']);

        $teamLead = TestUser::factory()->create(['email' => 'lead@example.com']);
        $teamMember = TestUser::factory()->create(['email' => 'member@example.com']);

        $teamLead->teams()->attach($engineering->id);
        $teamLead->groups()->attach($admins->id);

        $teamMember->teams()->attach($engineering->id);

        $project = DummyPost::create(['title' => 'Project Docs']);

        // Team rule: Engineering can view
        PBACAccessControl::factory()
            ->allow()
            ->forTeam($engineering)
            ->forResource(DummyPost::class, $project->id)
            ->withAction('view')
            ->withPriority(1)
            ->create();

        // Group rule: Admins can edit
        PBACAccessControl::factory()
            ->allow()
            ->forGroup($admins)
            ->forResource(DummyPost::class, $project->id)
            ->withAction(['view', 'edit'])
            ->withPriority(5)
            ->create();

        // Individual rule: Team lead can delete
        PBACAccessControl::factory()
            ->allow()
            ->forUser($teamLead)
            ->forResource(DummyPost::class, $project->id)
            ->withAction(['view', 'edit', 'delete'])
            ->withPriority(10)
            ->create();

        // Team lead has all permissions (individual rule)
        $this->assertTrue($this->evaluator->evaluate($teamLead, 'view', $project));
        $this->assertTrue($this->evaluator->evaluate($teamLead, 'edit', $project));
        $this->assertTrue($this->evaluator->evaluate($teamLead, 'delete', $project));

        // Team member can only view (team rule)
        $this->assertTrue($this->evaluator->evaluate($teamMember, 'view', $project));
        $this->assertFalse($this->evaluator->evaluate($teamMember, 'edit', $project));
        $this->assertFalse($this->evaluator->evaluate($teamMember, 'delete', $project));
    }

    /**
     * Real-world scenario: Temporary emergency access
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_emergency_access_with_conditions(): void
    {
        $support = TestUser::factory()->create(['email' => 'support@example.com']);
        $supportGroup = PBACAccessGroup::factory()->create(['name' => 'Support']);
        $support->groups()->attach($supportGroup->id);

        $customerData = DummyPost::create(['title' => 'Customer PII']);

        // Normal: Support can view with level >= 3
        PBACAccessControl::factory()
            ->allow()
            ->forGroup($supportGroup)
            ->forResource(DummyPost::class, $customerData->id)
            ->withAction('view')
            ->create([
                'extras' => ['min_level' => 3],
            ]);

        // Emergency: Support can edit/delete with level >= 10
        PBACAccessControl::factory()
            ->allow()
            ->forGroup($supportGroup)
            ->forResource(DummyPost::class, $customerData->id)
            ->withAction(['edit', 'delete'])
            ->create([
                'extras' => ['min_level' => 10],
            ]);

        // Normal support (level 5) can view but not edit
        $this->assertTrue($this->evaluator->evaluate($support, 'view', $customerData, ['level' => 5]));
        $this->assertFalse($this->evaluator->evaluate($support, 'edit', $customerData, ['level' => 5]));

        // Emergency escalation (level 10) can do everything
        $this->assertTrue($this->evaluator->evaluate($support, 'view', $customerData, ['level' => 10]));
        $this->assertTrue($this->evaluator->evaluate($support, 'edit', $customerData, ['level' => 10]));
        $this->assertTrue($this->evaluator->evaluate($support, 'delete', $customerData, ['level' => 10]));
    }

    /**
     * Real-world scenario: Read-write separation
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_read_write_separation(): void
    {
        $readers = PBACAccessGroup::factory()->create(['name' => 'Readers']);
        $writers = PBACAccessGroup::factory()->create(['name' => 'Writers']);

        $reader = TestUser::factory()->create(['email' => 'reader@example.com']);
        $writer = TestUser::factory()->create(['email' => 'writer@example.com']);

        $reader->groups()->attach($readers->id);
        $writer->groups()->attach([$readers->id, $writers->id]);

        $doc = DummyPost::create(['title' => 'Shared Document']);

        // Readers can only view
        PBACAccessControl::factory()
            ->allow()
            ->forGroup($readers)
            ->forResource(DummyPost::class, null)
            ->withAction(['view', 'viewAny'])
            ->create();

        // Writers can write (but inherit read from readers)
        PBACAccessControl::factory()
            ->allow()
            ->forGroup($writers)
            ->forResource(DummyPost::class, null)
            ->withAction(['create', 'update', 'delete'])
            ->create();

        // Reader: can only read
        $this->assertTrue($this->evaluator->evaluate($reader, 'view', $doc));
        $this->assertFalse($this->evaluator->evaluate($reader, 'update', $doc));

        // Writer: can read and write
        $this->assertTrue($this->evaluator->evaluate($writer, 'view', $doc));
        $this->assertTrue($this->evaluator->evaluate($writer, 'update', $doc));
        $this->assertTrue($this->evaluator->evaluate($writer, 'delete', $doc));
    }

    /**
     * Real-world scenario: Super admin with attribute bypass
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_super_admin_with_complex_rules(): void
    {
        Config::set('pbac.super_admin_attribute', 'is_super_admin');

        $superAdmin = TestUser::factory()->create([
            'email' => 'super@example.com',
            'is_super_admin' => true,
        ]);

        $regularUser = TestUser::factory()->create([
            'email' => 'regular@example.com',
            'is_super_admin' => false,
        ]);

        $restrictedPost = DummyPost::create(['title' => 'Highly Restricted']);

        // Explicit deny for everyone (even with impossible level)
        PBACAccessControl::factory()
            ->deny()
            ->forTarget(TestUser::class, null)
            ->forResource(DummyPost::class, $restrictedPost->id)
            ->withAction(['view', 'edit', 'delete'])
            ->withPriority(1000)
            ->create([
                'extras' => ['min_level' => 1], // Everyone should be denied
            ]);

        // Super admin bypasses all rules (use can() method which checks super admin attribute)
        $this->assertTrue($superAdmin->can('view', [$restrictedPost, ['level' => 0]]));
        $this->assertTrue($superAdmin->can('edit', [$restrictedPost, ['level' => 0]]));
        $this->assertTrue($superAdmin->can('delete', [$restrictedPost, ['level' => 0]]));

        // Regular user is denied
        $this->assertFalse($regularUser->can('view', [$restrictedPost, ['level' => 100]]));
    }
}
