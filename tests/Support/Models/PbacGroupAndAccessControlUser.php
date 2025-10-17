<?php

namespace Modules\Pbac\Tests\Support\Models;



class PbacGroupAndAccessControlUser extends TestUser {
    use \Modules\Pbac\Traits\HasPbacGroups;
    use \Modules\Pbac\Traits\HasPbacAccessControl;
}
