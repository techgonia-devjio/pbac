<?php

namespace Modules\Pbac\Support\ConditionerHandlers;

use Illuminate\Database\Eloquent\Model;
use Modules\Pbac\Contracts\ConditionHandlerInterface;
use Modules\Pbac\Models\PBACAccessControl;
use Modules\Pbac\Support\PbacLogger;

class RequiresAttributeValueHandler implements ConditionHandlerInterface
{
    public function __construct(private PbacLogger $logger)
    {
    }

    public function handle(Model $user, string $action, ?string $resourceTypeString, ?int $resourceId, array $context, mixed $conditionValue, PBACAccessControl $rule): bool
    {
        if ($resourceId === null || $resourceTypeString === null) {
            $this->logger->debug("Rule ID {$rule->id} requires a resource, but none was provided for attribute check.");
            return false;
        }

        $requiredAttrs = (array) $conditionValue;

        try {
            $resourceModel = app($resourceTypeString)->find($resourceId);

            if (!$resourceModel) {
                $this->logger->debug("Rule ID {$rule->id} could not load resource for 'requires_attribute_value' condition: {$resourceTypeString}:{$resourceId}.");
                return false; // Cannot evaluate condition if resource not found
            }

            foreach ($requiredAttrs as $attribute => $requiredValue) {
                // Use loose comparison to handle type differences (e.g., '1' == true)
                if (!isset($resourceModel->{$attribute}) || $resourceModel->{$attribute} != $requiredValue) {
                    $this->logger->debug("Rule ID {$rule->id} failed 'requires_attribute_value' for resource {$resourceTypeString}:{$resourceId}. Attribute '{$attribute}' does not match.");
                    return false;
                }
            }
        } catch (\Throwable $e) {
            $this->logger->error("PBAC Error: Failed to evaluate 'requires_attribute_value' for rule ID {$rule->id}: " . $e->getMessage());
            return false;
        }

        return true;
    }
}