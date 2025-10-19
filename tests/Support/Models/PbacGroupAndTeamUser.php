<?php

namespace Pbac\Tests\Support\Models;



class PbacGroupAndTeamUser extends TestUser {
    use \Pbac\Traits\HasPbacGroups;
    use \Pbac\Traits\HasPbacTeams;
}
