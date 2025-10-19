<?php

namespace Pbac\Services;

use Illuminate\Database\Eloquent\Model;
use Pbac\Models\PBACAccessControl;
use Pbac\Traits\HasPbacAccessControl;
use Pbac\Traits\HasPbacGroups;
use Pbac\Traits\HasPbacTeams;

class PbacService
{
    public function __construct(public PolicyEvaluator $policyEvaluator)
    { }

}
