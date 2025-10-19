<?php

namespace Pbac\Tests\Support\Models;



class PbacGroupAndAccessControlUser extends TestUser {
    use \Pbac\Traits\HasPbacGroups;
    use \Pbac\Traits\HasPbacAccessControl;
}
