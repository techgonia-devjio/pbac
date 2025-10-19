<?php

namespace Modules\Pbac\Tests\Support\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Pbac\Traits\HasPbacAccessControl;
use Modules\Pbac\Traits\HasPbacGroups;
use Modules\Pbac\Traits\HasPbacTeams;

class TestUser extends PlainUser {
    use HasPbacAccessControl;
    use HasPbacGroups;
    use HasPbacTeams;
}
