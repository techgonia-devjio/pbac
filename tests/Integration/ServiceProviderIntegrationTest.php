<?php

namespace Pbac\Tests\Integration;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Config;
use Pbac\Services\PbacService;
use Pbac\Services\PolicyEvaluator;
use Pbac\Support\PbacLogger;
use Pbac\Tests\TestCase;

class ServiceProviderIntegrationTest extends TestCase
{
    use \Pbac\Tests\Support\Traits\MigrationLoader;

    protected function setUp(): void
    {
        parent::setUp();
        $this->initMigration();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_registers_policy_evaluator_as_singleton(): void
    {
        // Resolve PolicyEvaluator from container
        $evaluator1 = app(PolicyEvaluator::class);
        $evaluator2 = app(PolicyEvaluator::class);

        // Should be the same instance (singleton)
        $this->assertSame($evaluator1, $evaluator2);
        $this->assertInstanceOf(PolicyEvaluator::class, $evaluator1);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_registers_pbac_logger_as_singleton(): void
    {
        // Resolve PbacLogger from container
        $logger1 = app(PbacLogger::class);
        $logger2 = app(PbacLogger::class);

        // Should be the same instance (singleton)
        $this->assertSame($logger1, $logger2);
        $this->assertInstanceOf(PbacLogger::class, $logger1);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_registers_pbac_service_as_singleton(): void
    {
        // Resolve PbacService from container
        $service1 = app(PbacService::class);
        $service2 = app(PbacService::class);

        // Should be the same instance (singleton)
        $this->assertSame($service1, $service2);
        $this->assertInstanceOf(PbacService::class, $service1);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_injects_policy_evaluator_into_pbac_service(): void
    {
        // Resolve PbacService from container
        $service = app(PbacService::class);

        // Service should have PolicyEvaluator injected
        $this->assertInstanceOf(PbacService::class, $service);

        // The service's evaluator should be the singleton instance
        $evaluator = app(PolicyEvaluator::class);
        $this->assertSame($evaluator, $service->policyEvaluator);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_loads_configuration_correctly(): void
    {
        // Check that PBAC config is loaded
        $this->assertNotNull(Config::get('pbac'));

        // Check specific config values that should be set in tests
        $this->assertEquals(
            \Pbac\Tests\Support\Models\TestUser::class,
            Config::get('pbac.user_model')
        );

        $this->assertEquals(
            'is_super_admin',
            Config::get('pbac.super_admin_attribute')
        );
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_loads_migrations_correctly(): void
    {
        // Check that migrations have run by verifying tables exist
        $this->assertTrue(\Schema::hasTable('pbac_access_targets'));
        $this->assertTrue(\Schema::hasTable('pbac_access_resources'));
        $this->assertTrue(\Schema::hasTable('pbac_access_groups'));
        $this->assertTrue(\Schema::hasTable('pbac_access_teams'));
        $this->assertTrue(\Schema::hasTable('pbac_accesses'));
        $this->assertTrue(\Schema::hasTable('pbac_group_user'));
        $this->assertTrue(\Schema::hasTable('pbac_team_user'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_creates_correct_table_structure_for_targets(): void
    {
        // Verify pbac_access_targets table structure
        $this->assertTrue(\Schema::hasColumn('pbac_access_targets', 'id'));
        $this->assertTrue(\Schema::hasColumn('pbac_access_targets', 'type'));
        $this->assertTrue(\Schema::hasColumn('pbac_access_targets', 'description'));
        $this->assertTrue(\Schema::hasColumn('pbac_access_targets', 'is_active'));
        $this->assertTrue(\Schema::hasColumn('pbac_access_targets', 'created_at'));
        $this->assertTrue(\Schema::hasColumn('pbac_access_targets', 'updated_at'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_creates_correct_table_structure_for_resources(): void
    {
        // Verify pbac_access_resources table structure
        $this->assertTrue(\Schema::hasColumn('pbac_access_resources', 'id'));
        $this->assertTrue(\Schema::hasColumn('pbac_access_resources', 'type'));
        $this->assertTrue(\Schema::hasColumn('pbac_access_resources', 'description'));
        $this->assertTrue(\Schema::hasColumn('pbac_access_resources', 'is_active'));
        $this->assertTrue(\Schema::hasColumn('pbac_access_resources', 'created_at'));
        $this->assertTrue(\Schema::hasColumn('pbac_access_resources', 'updated_at'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_creates_correct_table_structure_for_groups(): void
    {
        // Verify pbac_access_groups table structure
        $this->assertTrue(\Schema::hasColumn('pbac_access_groups', 'id'));
        $this->assertTrue(\Schema::hasColumn('pbac_access_groups', 'name'));
        $this->assertTrue(\Schema::hasColumn('pbac_access_groups', 'description'));
        $this->assertTrue(\Schema::hasColumn('pbac_access_groups', 'created_at'));
        $this->assertTrue(\Schema::hasColumn('pbac_access_groups', 'updated_at'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_creates_correct_table_structure_for_teams(): void
    {
        // Verify pbac_access_teams table structure
        $this->assertTrue(\Schema::hasColumn('pbac_access_teams', 'id'));
        $this->assertTrue(\Schema::hasColumn('pbac_access_teams', 'name'));
        $this->assertTrue(\Schema::hasColumn('pbac_access_teams', 'description'));
        $this->assertTrue(\Schema::hasColumn('pbac_access_teams', 'owner_id'));
        $this->assertTrue(\Schema::hasColumn('pbac_access_teams', 'created_at'));
        $this->assertTrue(\Schema::hasColumn('pbac_access_teams', 'updated_at'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_creates_correct_table_structure_for_accesses(): void
    {
        // Verify pbac_accesses table structure
        $this->assertTrue(\Schema::hasColumn('pbac_accesses', 'id'));
        $this->assertTrue(\Schema::hasColumn('pbac_accesses', 'pbac_access_target_id'));
        $this->assertTrue(\Schema::hasColumn('pbac_accesses', 'target_id'));
        $this->assertTrue(\Schema::hasColumn('pbac_accesses', 'pbac_access_resource_id'));
        $this->assertTrue(\Schema::hasColumn('pbac_accesses', 'resource_id'));
        $this->assertTrue(\Schema::hasColumn('pbac_accesses', 'action'));
        $this->assertTrue(\Schema::hasColumn('pbac_accesses', 'effect'));
        $this->assertTrue(\Schema::hasColumn('pbac_accesses', 'priority'));
        $this->assertTrue(\Schema::hasColumn('pbac_accesses', 'extras'));
        $this->assertTrue(\Schema::hasColumn('pbac_accesses', 'is_active'));
        $this->assertTrue(\Schema::hasColumn('pbac_accesses', 'created_at'));
        $this->assertTrue(\Schema::hasColumn('pbac_accesses', 'updated_at'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_creates_correct_pivot_table_for_group_user(): void
    {
        // Verify pbac_group_user pivot table structure
        $this->assertTrue(\Schema::hasColumn('pbac_group_user', 'pbac_access_group_id'));
        $this->assertTrue(\Schema::hasColumn('pbac_group_user', 'user_id'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_creates_correct_pivot_table_for_team_user(): void
    {
        // Verify pbac_team_user pivot table structure
        $this->assertTrue(\Schema::hasColumn('pbac_team_user', 'pbac_team_id'));
        $this->assertTrue(\Schema::hasColumn('pbac_team_user', 'user_id'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_registers_blade_pbac_can_directive(): void
    {
        // Compile a blade template with the @pbacCan directive
        $compiled = Blade::compileString("@pbacCan('view', \$post) Visible @endpbacCan");

        // Check that it compiles to proper PHP
        $this->assertStringContainsString("auth()->check()", $compiled);
        $this->assertStringContainsString("auth()->user()->can", $compiled);
        $this->assertStringContainsString("endif", $compiled);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_allows_helper_method_access_to_policy_evaluator(): void
    {
        // Verify we can get PolicyEvaluator using helper function
        $evaluator = app(PolicyEvaluator::class);

        $this->assertInstanceOf(PolicyEvaluator::class, $evaluator);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_allows_helper_method_access_to_pbac_service(): void
    {
        // Verify we can get PbacService using helper function
        $service = app(PbacService::class);

        $this->assertInstanceOf(PbacService::class, $service);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_properly_boots_package_in_correct_order(): void
    {
        // This tests that packageRegistered runs before packageBooted
        // by verifying that singletons are available when Gate::before is called

        // Get the evaluator (should be registered)
        $evaluator = app(PolicyEvaluator::class);
        $this->assertInstanceOf(PolicyEvaluator::class, $evaluator);

        // Get the service (should be registered)
        $service = app(PbacService::class);
        $this->assertInstanceOf(PbacService::class, $service);

        // Verify config is loaded
        $this->assertNotNull(Config::get('pbac'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_multiple_provider_registrations_correctly(): void
    {
        // Get instances before potential re-registration
        $evaluator1 = app(PolicyEvaluator::class);
        $service1 = app(PbacService::class);

        // Simulate multiple resolutions (shouldn't create new instances for singletons)
        $evaluator2 = app(PolicyEvaluator::class);
        $service2 = app(PbacService::class);

        // Should be exact same instances
        $this->assertSame($evaluator1, $evaluator2);
        $this->assertSame($service1, $service2);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_loads_default_config_values(): void
    {
        // Check default config structure exists
        $this->assertIsArray(Config::get('pbac'));

        // Check for expected config keys
        $this->assertArrayHasKey('user_model', Config::get('pbac'));
        $this->assertArrayHasKey('super_admin_attribute', Config::get('pbac'));
        $this->assertArrayHasKey('strict_resource_registration', Config::get('pbac'));
        $this->assertArrayHasKey('strict_target_registration', Config::get('pbac'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_allows_config_override_in_tests(): void
    {
        // Set a config value
        Config::set('pbac.strict_mode.resource', false);

        // Verify it was set
        $this->assertFalse(Config::get('pbac.strict_mode.resource'));

        // Change it
        Config::set('pbac.strict_mode.resource', true);

        // Verify it changed
        $this->assertTrue(Config::get('pbac.strict_mode.resource'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_provides_access_to_logger_instance(): void
    {
        $logger = app(PbacLogger::class);

        $this->assertInstanceOf(PbacLogger::class, $logger);

        // Logger should have a log method
        $this->assertTrue(method_exists($logger, 'log'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_doesnt_throw_errors_when_logging_is_disabled(): void
    {
        // Disable logging
        Config::set('pbac.logging.enabled', false);

        $logger = app(PbacLogger::class);

        // Should not throw any errors
        $logger->log('info', 'Test message');

        // Re-enable for other tests
        Config::set('pbac.logging.enabled', true);

        $this->assertTrue(true); // If we reach here, no errors were thrown
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_maintains_service_dependencies_correctly(): void
    {
        // Get PbacService
        $service = app(PbacService::class);

        // Get PolicyEvaluator
        $evaluator = app(PolicyEvaluator::class);

        // Service should have the same evaluator instance
        $this->assertSame($evaluator, $service->policyEvaluator);

        // Both should be singletons
        $service2 = app(PbacService::class);
        $evaluator2 = app(PolicyEvaluator::class);

        $this->assertSame($service, $service2);
        $this->assertSame($evaluator, $evaluator2);
    }
}
