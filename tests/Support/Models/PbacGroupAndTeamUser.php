<?php

namespace Modules\Pbac\Tests\Support\Models;



class PbacGroupAndTeamUser extends TestUser {
    use \Modules\Pbac\Traits\HasPbacGroups;
    use \Modules\Pbac\Traits\HasPbacTeams;
}
