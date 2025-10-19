<?php

namespace Pbac\Tests\Support\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Pbac\Traits\HasPbacAccessControl;
use Pbac\Traits\HasPbacGroups;
use Pbac\Traits\HasPbacTeams;

class TestUser extends PlainUser {
    use HasPbacAccessControl;
    use HasPbacGroups;
    use HasPbacTeams;
}
