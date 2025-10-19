<?php

namespace Modules\Pbac\Support\ConditionerHandlers;


use Illuminate\Database\Eloquent\Model;
use Modules\Pbac\Contracts\ConditionHandlerInterface;
use Modules\Pbac\Models\PBACAccessControl;
use Modules\Pbac\Support\PbacLogger;

class MinLevelHandler {
    public function __construct(private PbacLogger $logger)
    {
    }

    public function handle(Model $user, string $action, ?string $resourceTypeString, ?int $resourceId, array $context, mixed $conditionValue, PBACAccessControl $rule): bool
    {
        if (!isset($context['level'])) {
            $this->logger->debug("Rule ID {$rule->id} requires a 'level', but none was provided in the context.");
            return false; // Deny if level is required but not provided
        }

        $requiredLevel = (int) $conditionValue;
        $userLevel = (int) $context['level'];

        if ($userLevel < $requiredLevel) {
            $this->logger->debug("Rule ID {$rule->id} failed 'min_level' condition: user level {$userLevel} < required {$requiredLevel}.");
            return false;
        }

        return true;
    }
}