<?php

namespace Pbac\Tests\Support\Models;



class PbacUser extends TestUser {
    use \Pbac\Traits\HasPbacGroups;
    use \Pbac\Traits\HasPbacTeams;
    use \Pbac\Traits\HasPbacAccessControl;
}
