<?php

namespace Pbac\Tests\Support\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Pbac\Models\PBACAccessResource;
use Pbac\Tests\Support\Models\DummyPost;
use Pbac\Tests\Support\Models\DummyResourceA;
use Pbac\Tests\Support\Models\DummyResourceB;
use Pbac\Tests\Support\Models\DummyResourceC;

class PBACAccessResourceFactory extends Factory
{
    protected $model = PBACAccessResource::class;

    public function definition(): array
    {
        // Dynamically create a unique table name for testing isolation
        $tableName = 'dummy_resources_' . $this->faker->unique()->randomNumber(5);

        // Dynamically create a unique class name for the model
        $className = 'DynamicModel_' . $this->faker->unique()->word();

        // Define the namespace for the dynamic class
        $namespace = 'Pbac\Tests\Support\Models'; // No trailing backslash here

        // Full qualified class name
        $fullClassName = $namespace . '\\' . $className;

        // Register an autoloader to define the class when it's first referenced
        // The callback MUST accept the unfound class name as its first argument
        spl_autoload_register(function ($unfoundClassName) use ($fullClassName, $className, $namespace, $tableName) {
            // Only define the class if the unfound class name matches our dynamic class
            if ($unfoundClassName === $fullClassName) {
                // Define the dynamic Eloquent model class using eval()
                // It extends Illuminate\Database\Eloquent\Model and sets the dynamic table name
                eval("namespace $namespace;

                use Illuminate\Database\Eloquent\Model;
                use Illuminate\Support\Facades\Schema;

                class $className extends Model
                {
                    // Set the dynamic table name for this model instance
                    protected \$table = '$tableName';

                    // Define fillable attributes for mass assignment
                    protected \$fillable = ['name'];

                    /**
                     * The 'booted' method is called once per model class when it's initialized.
                     * We use it here to create the dummy table if it doesn't already exist.
                     */
                    protected static function booted()
                    {
                        // Check if the table exists before attempting to create it
                        if (!Schema::hasTable('$tableName')) {
                            Schema::create('$tableName', function (\$table) {
                                \$table->id(); // Auto-incrementing primary key
                                \$table->string('name'); // A simple string column
                                \$table->timestamps();
                            });
                        }
                        // more dummy data
                        for (\$i = 0; \$i < 5; \$i++) {
                            \$table = new static();
                            \$table->name = 'Dummy record $className' . rand(1, 30);
                            \$table->save();
                        }
                    }
                }");
            }
        });

        // Return the attributes for the PBACAccessResource model,
        // using the full qualified name of the dynamically created class as the 'type'.
        return [
            'type' => $fullClassName, // e.g., 'Modules\Pbac\Tests\Support\Models\DynamicModel_xyz'
            'description' => $this->faker->sentence,
            'is_active' => true,
        ];
    }

    /**
     * Define an 'inactive' state for the factory.
     * This allows you to create inactive resources easily: PBACAccessResource::factory()->inactive()->create();
     */
    public function inactive()
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
