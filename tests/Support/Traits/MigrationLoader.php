<?php

namespace Modules\Pbac\Tests\Support\Traits;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Modules\Pbac\Tests\Support\Models\TestUser;

trait MigrationLoader
{
    protected string $migrationPath;
    protected string $extension = '.tmp.php';
    protected Filesystem $fs;
    protected array $convertedMigrationFiles = [];

    protected function getMigrationPath(): string
    {
        if (!isset($this->migrationPath)) {
            // Use absolute path to the package's database/migrations directory
            $this->migrationPath = __DIR__ . '/../../../database/migrations';
        }
        return $this->migrationPath;
    }

    public function setup(): void
    {
        parent::setUp();
        $this->initMigration();
    }

    protected function initMigration()
    {
        $this->fs = new Filesystem();
        $this->convertedMigrationFiles = [];
        $this->setupMigrationFactory();
        $this->loadDatabase();
    }

    private function setupMigrationFactory()
    {
        Factory::guessFactoryNamesUsing(function (string $modelName) {
            if (str_starts_with($modelName, 'Modules\\Pbac\\Models\\')) {
                // Modules\Pbac\Tests\Support\Database\Factories
                return 'Modules\\Pbac\\Tests\\Support\\Database\\Factories\\'.class_basename($modelName).'Factory';
            }
            // For the dummy TestUser model
            if ($modelName === TestUser::class || is_subclass_of($modelName, TestUser::class)) {
                $name = class_basename($modelName);
                return 'Modules\\Pbac\\Tests\\Support\\Database\\Factories\\'.$name.'Factory';
            }
            // Fallback to Laravel's default convention if needed
            return 'Database\\Factories\\'.class_basename($modelName).'Factory';
        });
    }

    public function loadDatabase(): void
    {
        $this->convertStubToPhp();

        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite.database' => ':memory:']);

        $this->setupUserTable();
        Artisan::call('migrate', [
            '--path' => $this->getMigrationPath(),
            '--realpath' => true,
        ]);
    }

    protected function convertStubToPhp(): void
    {
        $migrationPath = $this->getMigrationPath();
        foreach ($this->fs->files($migrationPath) as $file) {
            if ($file->getExtension() === 'stub') {
                $source = $file->getPathname();
                $dest = $migrationPath.'/'.$file->getFilenameWithoutExtension().$this->extension;
                $this->fs->move($source, $dest);
                $this->convertedMigrationFiles[] = $dest;
            }
        }
    }

    public function tearDown(): void
    {
        parent::tearDown();
        $this->unloadDatabase();
    }

    public function unloadDatabase(): void
    {
        $this->revertPhpToStub();
    }

    protected function revertPhpToStub(): void
    {
        foreach ($this->convertedMigrationFiles as $phpPath) {
            $stubPath = Str::replaceLast($this->extension, '.stub', $phpPath);
            if ($this->fs->exists($phpPath)) {
                $this->fs->move($phpPath, $stubPath);
            }
        }
    }

    private function setupUserTable()
    {
        $schema = $this->app['db']->connection()->getSchemaBuilder();

        $schema->create('users', function (Blueprint $table) {
            $table->increments('id');
            $table->string('email');
            $table->string('password')->nullable();
            $table->string('name')->nullable();
            $table->boolean('is_super_admin')->nullable();

            $table->softDeletes();
        });


    }

}
