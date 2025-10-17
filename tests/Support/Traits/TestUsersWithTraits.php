<?php

namespace Modules\Pbac\Tests\Support\Traits;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Modules\Pbac\Tests\Support\Database\Factories\TestUserFactory;
use Modules\Pbac\Tests\Support\Models\PbacAccessControlUser;
use Modules\Pbac\Tests\Support\Models\PbacGroupAndAccessControlUser;
use Modules\Pbac\Tests\Support\Models\PbacGroupAndTeamUser;
use Modules\Pbac\Tests\Support\Models\PbacGroupUser;
use Modules\Pbac\Tests\Support\Models\PbacTeamAndAccessControlUser;
use Modules\Pbac\Tests\Support\Models\PbacTeamUser;
use Modules\Pbac\Tests\Support\Models\PbacUser;
use Modules\Pbac\Tests\Support\Models\TestUser;
use Modules\Pbac\Traits\HasPbacAccessControl;
use Modules\Pbac\Traits\HasPbacGroups;
use Modules\Pbac\Traits\HasPbacTeams;

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
