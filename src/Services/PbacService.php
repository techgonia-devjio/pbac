<?php

namespace Modules\Pbac\Services;

use Illuminate\Database\Eloquent\Model;
use Modules\Pbac\Models\PBACAccessControl;
use Modules\Pbac\Traits\HasPbacAccessControl;
use Modules\Pbac\Traits\HasPbacGroups;
use Modules\Pbac\Traits\HasPbacTeams;

class PbacService
{
    public function __construct(public PolicyEvaluator $policyEvaluator)
    { }

}
