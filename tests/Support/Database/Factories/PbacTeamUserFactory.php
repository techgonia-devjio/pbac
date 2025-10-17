<?php

namespace Modules\Pbac\Tests\Support\Database\Factories;


use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Pbac\Tests\Support\Models\PbacTeamUser;
use Modules\Pbac\Tests\Support\Models\TestUser;

class PbacTeamUserFactory extends TestUserFactory
{
    protected $model = PbacTeamUser::class;

}
