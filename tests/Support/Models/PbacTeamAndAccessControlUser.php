<?php

namespace Modules\Pbac\Tests\Support\Models;



class PbacTeamAndAccessControlUser extends TestUser {
    use \Modules\Pbac\Traits\HasPbacTeams;
    use \Modules\Pbac\Traits\HasPbacAccessControl;
}
