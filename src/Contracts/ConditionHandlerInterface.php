<?php

namespace Pbac\Contracts;

use Pbac\Models\PBACAccessControl;


interface ConditionHandlerInterface
{

    public function handle(
        \Illuminate\Foundation\Auth\User $user,
        string $action,
        ?string $resourceTypeString,
        ?int $resourceId,
        array $context,
        mixed $conditionValue,
        PBACAccessControl $rule
    ): bool;
}
