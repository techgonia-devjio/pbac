<?php

namespace Modules\Pbac\Tests\Support\Models;



class PbacAccessControlUser extends TestUser {
    use \Modules\Pbac\Traits\HasPbacAccessControl;
}
