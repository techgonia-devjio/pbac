<?php

namespace Pbac\Tests\Support\Database\Factories;


use Illuminate\Database\Eloquent\Factories\Factory;
use Pbac\Tests\Support\Models\PbacTeamUser;
use Pbac\Tests\Support\Models\TestUser;

class PbacTeamUserFactory extends TestUserFactory
{
    protected $model = PbacTeamUser::class;

}
