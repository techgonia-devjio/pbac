<?php

namespace Modules\Pbac\Contracts;

use Illuminate\Database\Eloquent\Model;
use Modules\Pbac\Models\PBACAccessControl;


interface ConditionHandlerInterface
{

    public function handle(
        Model $user,
        string $action,
        ?string $resourceTypeString,
        ?int $resourceId,
        array $context,
        mixed $conditionValue,
        PBACAccessControl $rule
    ): bool;
}
