<?php

namespace Pbac\Tests\Regression;

use Illuminate\Support\Facades\Config;
use Pbac\Models\PBACAccessControl;
use Pbac\Models\PBACAccessGroup;
use Pbac\Models\PBACAccessResource;
use Pbac\Models\PBACAccessTarget;
use Pbac\Models\PBACAccessTeam;
use Pbac\Services\PbacService;
use Pbac\Services\PolicyEvaluator;
use Pbac\Support\PbacLogger;
use Pbac\Tests\Support\Models\DummyPost;
use Pbac\Tests\Support\Models\TestUser;
use Pbac\Tests\TestCase;
use Pbac\Traits\HasPbacAccessControl;
use Pbac\Traits\HasPbacGroups;
use Pbac\Traits\HasPbacTeams;

/**
 * Regression tests for API stability.
 *
 * These tests ensure that the public API remains backwards compatible.
 * Breaking changes to these APIs should increment the major version number.
 *
 * DO NOT modify these tests without careful consideration of backwards compatibility.
 */
class APIStabilityRegressionTest extends TestCase
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
    public function api_user_can_method_signature(): void
    {
        // API: User::can($ability, $arguments = []) must remain stable
        $user = TestUser::factory()->create();
        $post = DummyPost::create(['title' => 'Test']);

        PBACAccessControl::factory()
            ->allow()
            ->forUser($user)
            ->forResource(DummyPost::class, $post->id)
            ->withAction('view')
            ->create();

        // Test all supported signatures
        $this->assertTrue($user->can('view', $post));
        $this->assertTrue($user->can('view', ['resource' => $post]));
        $this->assertTrue($user->can('view', ['resource' => $post, 'context' => []]));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function api_policy_evaluator_evaluate_method_signature(): void
    {
        // API: PolicyEvaluator::evaluate($user, $action, $resource, $context) must remain stable
        $user = TestUser::factory()->create();
        $post = DummyPost::create(['title' => 'Test']);

        PBACAccessControl::factory()
            ->allow()
            ->forUser($user)
            ->forResource(DummyPost::class, $post->id)
            ->withAction('view')
            ->create();

        // Test method signature
        $this->assertTrue($this->evaluator->evaluate($user, 'view', $post));
        $this->assertTrue($this->evaluator->evaluate($user, 'view', $post, []));
        $this->assertTrue($this->evaluator->evaluate($user, 'view', $post, ['key' => 'value']));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function api_factory_method_chaining(): void
    {
        // API: Factory fluent interface must remain stable
        $user = TestUser::factory()->create();
        $post = DummyPost::create(['title' => 'Test']);

        // Test fluent interface
        $rule = PBACAccessControl::factory()
            ->allow()
            ->forUser($user)
            ->forResource(DummyPost::class, $post->id)
            ->withAction('view')
            ->withPriority(10)
            ->create();

        $this->assertInstanceOf(PBACAccessControl::class, $rule);
        $this->assertEquals('allow', $rule->effect);
        $this->assertEquals(10, $rule->priority);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function api_factory_deny_method(): void
    {
        // API: Factory::deny() method must exist
        $user = TestUser::factory()->create();
        $post = DummyPost::create(['title' => 'Test']);

        $rule = PBACAccessControl::factory()
            ->deny()
            ->forUser($user)
            ->forResource(DummyPost::class, $post->id)
            ->withAction('view')
            ->create();

        $this->assertEquals('deny', $rule->effect);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function api_factory_for_group_method(): void
    {
        // API: Factory::forGroup() method must exist
        $group = PBACAccessGroup::factory()->create(['name' => 'Test']);
        $post = DummyPost::create(['title' => 'Test']);

        $rule = PBACAccessControl::factory()
            ->allow()
            ->forGroup($group)
            ->forResource(DummyPost::class, $post->id)
            ->withAction('view')
            ->create();

        $this->assertNotNull($rule);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function api_factory_for_team_method(): void
    {
        // API: Factory::forTeam() method must exist
        $team = PBACAccessTeam::factory()->create(['name' => 'Test']);
        $post = DummyPost::create(['title' => 'Test']);

        $rule = PBACAccessControl::factory()
            ->allow()
            ->forTeam($team)
            ->forResource(DummyPost::class, $post->id)
            ->withAction('view')
            ->create();

        $this->assertNotNull($rule);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function api_trait_has_pbac_access_control_methods(): void
    {
        // API: HasPbacAccessControl trait methods must exist
        $this->assertTrue(trait_exists(HasPbacAccessControl::class));

        $user = TestUser::factory()->create();

        // Verify can() method exists
        $this->assertTrue(method_exists($user, 'can'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function api_trait_has_pbac_groups_methods(): void
    {
        // API: HasPbacGroups trait methods must exist
        $this->assertTrue(trait_exists(HasPbacGroups::class));

        $user = TestUser::factory()->create();

        // Verify groups() method exists
        $this->assertTrue(method_exists($user, 'groups'));
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsToMany::class, $user->groups());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function api_trait_has_pbac_teams_methods(): void
    {
        // API: HasPbacTeams trait methods must exist
        $this->assertTrue(trait_exists(HasPbacTeams::class));

        $user = TestUser::factory()->create();

        // Verify teams() method exists
        $this->assertTrue(method_exists($user, 'teams'));
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsToMany::class, $user->teams());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function api_pbac_access_control_model_attributes(): void
    {
        // API: PBACAccessControl model attributes must remain accessible
        $user = TestUser::factory()->create();
        $post = DummyPost::create(['title' => 'Test']);

        $rule = PBACAccessControl::factory()
            ->allow()
            ->forUser($user)
            ->forResource(DummyPost::class, $post->id)
            ->withAction('view')
            ->create();

        // These attributes must always be accessible
        $this->assertNotNull($rule->id);
        $this->assertNotNull($rule->effect);
        $this->assertNotNull($rule->action);
        $this->assertNotNull($rule->priority);
        $this->assertIsArray($rule->action);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function api_pbac_access_group_model_relationships(): void
    {
        // API: PBACAccessGroup relationships must exist
        $group = PBACAccessGroup::factory()->create(['name' => 'Test']);

        $this->assertTrue(method_exists($group, 'users'));
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsToMany::class, $group->users());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function api_pbac_access_team_model_relationships(): void
    {
        // API: PBACAccessTeam relationships must exist
        $team = PBACAccessTeam::factory()->create(['name' => 'Test']);

        $this->assertTrue(method_exists($team, 'users'));
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsToMany::class, $team->users());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function api_service_container_bindings(): void
    {
        // API: Service container bindings must exist
        $this->assertInstanceOf(PolicyEvaluator::class, app(PolicyEvaluator::class));
        $this->assertInstanceOf(PbacService::class, app(PbacService::class));
        $this->assertInstanceOf(PbacLogger::class, app(PbacLogger::class));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function api_config_keys_exist(): void
    {
        // API: Config keys must remain stable
        $this->assertNotNull(Config::get('pbac.user_model'));
        $this->assertNotNull(Config::get('pbac.super_admin_attribute'));
        $this->assertIsArray(Config::get('pbac.traits'));
        $this->assertIsArray(Config::get('pbac.models'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function api_config_traits_keys(): void
    {
        // API: Config trait keys must exist
        $traits = Config::get('pbac.traits');

        $this->assertArrayHasKey('groups', $traits);
        $this->assertArrayHasKey('teams', $traits);
        $this->assertArrayHasKey('access_control', $traits);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function api_config_models_keys(): void
    {
        // API: Config model keys must exist
        $models = Config::get('pbac.models');

        $this->assertArrayHasKey('access_control', $models);
        $this->assertArrayHasKey('access_resource', $models);
        $this->assertArrayHasKey('access_target', $models);
        $this->assertArrayHasKey('access_group', $models);
        $this->assertArrayHasKey('access_team', $models);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function api_model_create_method(): void
    {
        // API: Standard Eloquent create() method must work
        $group = PBACAccessGroup::create([
            'name' => 'Test Group',
            'description' => 'Test Description',
        ]);

        $this->assertInstanceOf(PBACAccessGroup::class, $group);
        $this->assertEquals('Test Group', $group->name);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function api_relationship_attach_detach_methods(): void
    {
        // API: Standard relationship methods must work
        $user = TestUser::factory()->create();
        $group = PBACAccessGroup::factory()->create(['name' => 'Test']);

        // attach()
        $user->groups()->attach($group->id);
        $this->assertEquals(1, $user->groups()->count());

        // detach()
        $user->groups()->detach($group->id);
        $this->assertEquals(0, $user->groups()->count());

        // sync()
        $user->groups()->sync([$group->id]);
        $this->assertEquals(1, $user->groups()->count());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function api_pbac_service_has_policy_evaluator(): void
    {
        // API: PbacService must have policyEvaluator property
        $service = app(PbacService::class);

        $this->assertObjectHasProperty('policyEvaluator', $service);
        $this->assertInstanceOf(PolicyEvaluator::class, $service->policyEvaluator);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function api_logger_has_log_method(): void
    {
        // API: PbacLogger must have log() method
        $logger = app(PbacLogger::class);

        $this->assertTrue(method_exists($logger, 'log'));

        // Should not throw exception
        $logger->log('info', 'Test message', ['context' => 'test']);
        $this->assertTrue(true);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function api_action_supports_string_or_array(): void
    {
        // API: Action parameter must support both string and array
        $user = TestUser::factory()->create();
        $post = DummyPost::create(['title' => 'Test']);

        // String action
        $rule1 = PBACAccessControl::factory()
            ->allow()
            ->forUser($user)
            ->forResource(DummyPost::class, $post->id)
            ->withAction('view')
            ->create();

        $this->assertIsArray($rule1->action);

        // Array action
        $rule2 = PBACAccessControl::factory()
            ->allow()
            ->forUser($user)
            ->forResource(DummyPost::class, $post->id)
            ->withAction(['edit', 'delete'])
            ->create();

        $this->assertIsArray($rule2->action);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function api_resource_supports_class_or_instance(): void
    {
        // API: Resource parameter must support both class name and instance
        $user = TestUser::factory()->create();
        $post = DummyPost::create(['title' => 'Test']);

        // Class name
        PBACAccessControl::factory()
            ->allow()
            ->forUser($user)
            ->forResource(DummyPost::class, null)
            ->withAction('create')
            ->create();

        $this->assertTrue($user->can('create', DummyPost::class));

        // Instance
        PBACAccessControl::factory()
            ->allow()
            ->forUser($user)
            ->forResource(DummyPost::class, $post->id)
            ->withAction('view')
            ->create();

        $this->assertTrue($user->can('view', $post));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function api_model_factories_exist_and_work(): void
    {
        // API: All model factories must exist
        $accessControl = PBACAccessControl::factory()->make();
        $this->assertInstanceOf(PBACAccessControl::class, $accessControl);

        $group = PBACAccessGroup::factory()->make();
        $this->assertInstanceOf(PBACAccessGroup::class, $group);

        $team = PBACAccessTeam::factory()->make();
        $this->assertInstanceOf(PBACAccessTeam::class, $team);

        $resource = PBACAccessResource::factory()->make();
        $this->assertInstanceOf(PBACAccessResource::class, $resource);

        $target = PBACAccessTarget::factory()->make();
        $this->assertInstanceOf(PBACAccessTarget::class, $target);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function api_effect_values_are_allow_or_deny(): void
    {
        // API: Effect column must only contain 'allow' or 'deny'
        $user = TestUser::factory()->create();
        $post = DummyPost::create(['title' => 'Test']);

        $allowRule = PBACAccessControl::factory()
            ->allow()
            ->forUser($user)
            ->forResource(DummyPost::class, $post->id)
            ->withAction('view')
            ->create();

        $this->assertEquals('allow', $allowRule->effect);

        $denyRule = PBACAccessControl::factory()
            ->deny()
            ->forUser($user)
            ->forResource(DummyPost::class, $post->id)
            ->withAction('edit')
            ->create();

        $this->assertEquals('deny', $denyRule->effect);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function api_database_table_names(): void
    {
        // API: Database table names must remain stable
        $this->assertTrue(\Schema::hasTable('pbac_access_targets'));
        $this->assertTrue(\Schema::hasTable('pbac_access_resources'));
        $this->assertTrue(\Schema::hasTable('pbac_access_groups'));
        $this->assertTrue(\Schema::hasTable('pbac_teams'));
        $this->assertTrue(\Schema::hasTable('pbac_accesses'));
        $this->assertTrue(\Schema::hasTable('pbac_group_user'));
        $this->assertTrue(\Schema::hasTable('pbac_team_user'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function api_database_column_names_on_accesses_table(): void
    {
        // API: Critical column names must not change
        $this->assertTrue(\Schema::hasColumn('pbac_accesses', 'id'));
        $this->assertTrue(\Schema::hasColumn('pbac_accesses', 'pbac_access_target_id'));
        $this->assertTrue(\Schema::hasColumn('pbac_accesses', 'target_id'));
        $this->assertTrue(\Schema::hasColumn('pbac_accesses', 'pbac_access_resource_id'));
        $this->assertTrue(\Schema::hasColumn('pbac_accesses', 'resource_id'));
        $this->assertTrue(\Schema::hasColumn('pbac_accesses', 'action'));
        $this->assertTrue(\Schema::hasColumn('pbac_accesses', 'effect'));
        $this->assertTrue(\Schema::hasColumn('pbac_accesses', 'priority'));
        $this->assertTrue(\Schema::hasColumn('pbac_accesses', 'extras'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function api_json_cast_attributes(): void
    {
        // API: JSON cast attributes must work
        $user = TestUser::factory()->create();
        $post = DummyPost::create(['title' => 'Test']);

        $rule = PBACAccessControl::factory()
            ->allow()
            ->forUser($user)
            ->forResource(DummyPost::class, $post->id)
            ->withAction(['view', 'edit'])
            ->create([
                'extras' => ['key' => 'value'],
            ]);

        // Action should be array
        $this->assertIsArray($rule->action);

        // Extras should be array
        $this->assertIsArray($rule->extras);
    }
}
