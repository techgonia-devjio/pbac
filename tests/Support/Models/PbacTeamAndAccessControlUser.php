<?php

namespace Pbac\Tests\Support\Models;



class PbacTeamAndAccessControlUser extends TestUser {
    use \Pbac\Traits\HasPbacTeams;
    use \Pbac\Traits\HasPbacAccessControl;
}
