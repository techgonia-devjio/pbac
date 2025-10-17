<?php

namespace Modules\Pbac\Commands;

use Illuminate\Console\Command;
use Modules\Pbac\Models\PBACAccessControl; // Import the rule model
use Modules\Pbac\Models\PBACAccessTarget; // Import Target model
use Modules\Pbac\Models\PBACAccessResource; // Import Resource model
use Illuminate\Support\Facades\Validator; // For input validation
use Illuminate\Support\Facades\Config; // Import Config facade

class CreateAccessRuleCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pbac:create-rule
                            {action : The action (e.g., view, update)}
                            {effect=allow : The effect (allow or deny)}
                            {--resource-type= : The resource class or type (e.g., App\Models\Article). Required if --resource-id is used.}
                            {--resource-id= : The specific resource ID (optional). Requires --resource-type.}
                            {--target-type= : The target class or type (e.g., App\Models\User, Modules\Pbac\Models\PBACAccessGroup, Modules\Pbac\Models\PBACAccessTeam). Required if --target-id is used.}
                            {--target-id= : The specific target ID (optional). Requires --target-type.}
                            {--priority=0 : Rule priority (higher is evaluated first)}'; // Removed --conditions option

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new access rule';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $action = $this->argument('action');
        $effect = $this->argument('effect');
        $resourceType = $this->option('resource-type');
        $resourceId = $this->option('resource-id');
        $targetType = $this->option('target-type');
        $targetId = $this->option('target-id');
        $priority = (int) $this->option('priority');

        // Validation
        $validator = Validator::make($this->arguments() + $this->options(), [
            'action' => ['required', 'string', 'in:' . implode(',', Config::get('pbac.supported_actions', []))], // Validate action against config
            'effect' => ['required', 'string', 'in:allow,deny'],
            'resource-type' => ['nullable', 'string', 'in:' . implode(',', Config::get('pbac.supported_resource_types', []))], // Validate resource type against config
            'resource-id' => ['nullable', 'integer'],
            'target-type' => ['nullable', 'string', 'in:' . implode(',', Config::get('pbac.supported_target_types', []))], // Validate target type against config
            'target-id' => ['nullable', 'integer'],
            'priority' => ['integer'],
        ]);

        // Add conditional validation: resource-id requires resource-type, target-id requires target-type
        $validator->sometimes('resource-type', 'required', function ($input) {
            return !empty($input['resource-id']);
        });
        $validator->sometimes('target-type', 'required', function ($input) {
            return !empty($input['target-id']);
        });


        if ($validator->fails()) {
            $this->error("Validation failed:");
            foreach ($validator->errors()->all() as $error) {
                $this->error("- " . $error);
            }
            return Command::FAILURE;
        }

        // Resolve target and resource type IDs from the database
        $targetTypeEntry = $targetType ? PBACAccessTarget::where('type', $targetType)->first() : null;
        $resourceTypeEntry = $resourceType ? PBACAccessResource::where('type', $resourceType)->first() : null;

        // Validate that target/resource types exist if specified
        if ($targetType && !$targetTypeEntry) {
            $this->error("Target type '{$targetType}' not found in pbac_access_targets. Please register it first.");
            return Command::FAILURE;
        }
        if ($resourceType && !$resourceTypeEntry) {
            $this->error("Resource type '{$resourceType}' not found in pbac_access_resources. Please register it first.");
            return Command::FAILURE;
        }

        try {
            $rule = PBACAccessControl::create([
                'pbac_access_target_id' => $targetTypeEntry ? $targetTypeEntry->id : null,
                'target_id' => $targetId,
                'pbac_access_resource_id' => $resourceTypeEntry ? $resourceTypeEntry->id : null,
                'resource_id' => $resourceId,
                'action' => $action,
                'effect' => $effect,
                // Removed 'extras' column assignment
                'priority' => $priority,
            ]);

            $this->info("Access rule created successfully (ID: {$rule->id}).");
            $this->line("Action: {$rule->action}, Effect: {$rule->effect}");
            $this->line("Resource: " . ($resourceType ?? 'Any Type') . ($resourceId ? " (ID: {$resourceId})" : " (Any Instance)"));
            $this->line("Target: " . ($targetType ?? 'Any Type') . ($targetId ? " (ID: {$targetId})" : " (Any Instance)"));
            $this->line("Priority: {$rule->priority}");


        } catch (\Exception $e) {
            $this->error("An error occurred while creating the access rule: " . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}

