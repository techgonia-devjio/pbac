<?php

namespace Modules\Pbac\Tests\Support\Models;



class PbacUser extends TestUser {
    use \Modules\Pbac\Traits\HasPbacGroups;
    use \Modules\Pbac\Traits\HasPbacTeams;
    use \Modules\Pbac\Traits\HasPbacAccessControl;
}
