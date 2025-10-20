<?php

namespace Pbac\Providers;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Pbac\Services\PolicyEvaluator;
use Pbac\Support\PbacLogger;
use Spatie\LaravelPackageTools\Commands\InstallCommand;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Spatie\LaravelPackageTools\Package;

class PbacProvider extends PackageServiceProvider {

    public function configurePackage(Package $package): void
    {
        $package
            ->name('pbac')
            ->hasConfigFile()
            ->hasMigration('create_pbac_access_targets')
            ->hasMigration('create_pbac_access_resources')
            ->hasMigration('create_pbac_access_group') // high level users can create groups like: admins, guests, users, owner
            ->hasMigration('create_pbac_access_team') // teams are groups of users with specific roles: a team of dev,marketing, sales, etc.
            ->hasMigration('create_pbac_accesses') //
            //->hasCommand(\Modules\Pbac\Commands\GenerateAccessRules::class)
            //->hasCommand(\Modules\Pbac\Commands\GenerateAccessGroups::class)
            //->hasCommand(\Modules\Pbac\Commands\GenerateAccessTeams::class)
            ->publishesServiceProvider("PbacProvider")
            ->hasInstallCommand(function(InstallCommand $command) {
                $command
                    ->publishConfigFile()
                    ->publishMigrations()
                    ->copyAndRegisterServiceProviderInApp();
            });
    }

    public function packageBooted()
    {
        // The Gate::before check for PBAC integration.
        // This integrates PBAC with Laravel's Gate system.
        Gate::before(function ($user, $ability, $arguments) {
            if (!$user || !in_array(config('pbac.traits.access_control'), class_uses($user))) {
                return null; // Let Gate continue if user is not logged in or trait is not used
            }

            // Check for the super admin attribute defined in config
            $superAdminAttribute = config('pbac.super_admin_attribute');
            if ($superAdminAttribute && isset($user->{$superAdminAttribute}) && $user->{$superAdminAttribute}) {
                return true; // Super admin bypasses all checks
            }

            // For PBAC users, delegate to the user's can() method which uses PolicyEvaluator
            // Extract the resource from arguments (first element if exists)
            $resource = !empty($arguments) ? $arguments[0] : null;

            // Call the user's can() method which internally uses PolicyEvaluator
            return $user->can($ability, $resource) ? true : false;
        });

        // Optional: Register a Blade directive for checking PBAC access
        // This is an alternative or supplement to Laravel's built-in @can
        Blade::directive('pbacCan', function ($arguments) {
            // The $arguments string will contain the ability and potentially the resource
            // Example: @pbacCan('view', $article) or @pbacCan('create', App\Models\Article::class)
            return "<?php if (auth()->check() && auth()->user()->can({$arguments})): ?>";
        });
        Blade::directive('endpbacCan', function () {
            return "<?php endif; ?>";
        });
    }

    public function packageRegistered()
    {
        // Bind the PolicyEvaluator service to the container
        $this->app->singleton(PbacLogger::class, function ($app) {
            return new PbacLogger();
        });
        $this->app->singleton(PolicyEvaluator::class, function ($app) {
            return new PolicyEvaluator();
        });

        // Bind PbacService as 'pbac' for facade access
        $this->app->singleton('pbac', function ($app) {
            return new \Pbac\Services\PbacService($app->make(PolicyEvaluator::class));
        });

        // Also bind the class name for direct injection
        $this->app->singleton(\Pbac\Services\PbacService::class, function ($app) {
            return $app->make('pbac');
        });
    }

}
