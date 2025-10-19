<?php

namespace Pbac\Tests\Support\Traits;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Pbac\Tests\Support\Database\Factories\TestUserFactory;
use Pbac\Tests\Support\Models\PbacAccessControlUser;
use Pbac\Tests\Support\Models\PbacGroupAndAccessControlUser;
use Pbac\Tests\Support\Models\PbacGroupAndTeamUser;
use Pbac\Tests\Support\Models\PbacGroupUser;
use Pbac\Tests\Support\Models\PbacTeamAndAccessControlUser;
use Pbac\Tests\Support\Models\PbacTeamUser;
use Pbac\Tests\Support\Models\PbacUser;
use Pbac\Tests\Support\Models\TestUser;
use Pbac\Traits\HasPbacAccessControl;
use Pbac\Traits\HasPbacGroups;
use Pbac\Traits\HasPbacTeams;

trait TestUsersWithTraits
{


    protected function createUserWithPbacTraitsGroup(): TestUser
    {
        return PbacGroupUser::factory()->create();
    }

    protected function createUserWithPbacTraitsTeam(): TestUser
    {
        $user = PbacTeamUser::factory()->create();
        return $user;
    }

    protected function createUserWithPbacTraitsAccessControl(): TestUser
    {
        $user = PbacAccessControlUser::factory()->create();
        return $user;
    }

    protected function createUserWithPbacTraitsGroupAndTeam(): TestUser
    {
        $user = PbacGroupAndTeamUser::factory()->create();
        return $user;
    }

    protected function createUserWithPbacTraitsGroupAndAccessControl(): TestUser
    {
        $user = PbacGroupAndAccessControlUser::factory()->create();
        return $user;
    }

    protected function createUserWithPbacTraitsTeamAndAccessControl(): TestUser
    {
        $user = PbacTeamAndAccessControlUser::factory()->create();
        return $user;
    }

    protected function createUserWithPbacTraitsGroupAndTeamAndAccessControl(): TestUser
    {
        $user = PbacUser::factory()->create();
        return $user;
    }

}
