<?php

namespace Pbac\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * PBAC Facade
 *
 * Provides convenient access to PBAC functionality.
 *
 * @method static bool can(\Illuminate\Foundation\Auth\User $user, string $action, \Illuminate\Database\Eloquent\Model|string|null $resource = null, array|object|null $context = null)
 * @method static bool cannot(\Illuminate\Foundation\Auth\User $user, string $action, \Illuminate\Database\Eloquent\Model|string|null $resource = null, array|object|null $context = null)
 * @method static \Pbac\Models\PBACAccessControl allow()
 * @method static \Pbac\Models\PBACAccessControl deny()
 * @method static array getRulesFor(\Illuminate\Foundation\Auth\User $user, string|null $action = null, string|null $resource = null)
 * @method static array getUserGroups(\Illuminate\Foundation\Auth\User $user)
 * @method static array getUserTeams(\Illuminate\Foundation\Auth\User $user)
 * @method static bool isSuperAdmin(\Illuminate\Foundation\Auth\User $user)
 * @method static void clearCache(\Illuminate\Database\Eloquent\Model|null $user = null)
 * @method static \Pbac\Models\PBACAccessGroup createGroup(string $name, string|null $description = null)
 * @method static \Pbac\Models\PBACAccessTeam createTeam(string $name, string|null $description = null, int|null $ownerId = null)
 * @method static void assignToGroup(\Illuminate\Foundation\Auth\User $user, \Pbac\Models\PBACAccessGroup|int $group)
 * @method static void assignToTeam(\Illuminate\Foundation\Auth\User $user, \Pbac\Models\PBACAccessTeam|int $team)
 * @method static void removeFromGroup(\Illuminate\Foundation\Auth\User $user, \Pbac\Models\PBACAccessGroup|int $group)
 * @method static void removeFromTeam(\Illuminate\Foundation\Auth\User $user, \Pbac\Models\PBACAccessTeam|int $team)
 * @method static bool hasGroup(\Illuminate\Foundation\Auth\User $user, string|int $group)
 * @method static bool hasTeam(\Illuminate\Foundation\Auth\User $user, string|int $team)
 * @method static array getPermissionsFor(\Illuminate\Foundation\Auth\User $user, string|null $resource = null)
 * @method static \Illuminate\Support\Collection getAllRules()
 * @method static \Illuminate\Support\Collection getAllGroups()
 * @method static \Illuminate\Support\Collection getAllTeams()
 * @method static bool isImpersonating()
 * @method static \Illuminate\Database\Eloquent\Model|null getImpersonator()
 * @method static int|null getImpersonatorId()
 * @method static void startImpersonation(\Illuminate\Database\Eloquent\Model $impersonator, \Illuminate\Database\Eloquent\Model $target)
 * @method static void stopImpersonation()
 * @method static bool canImpersonate(\Illuminate\Foundation\Auth\User $user, \Illuminate\Database\Eloquent\Model|int $target)
 * @method static bool canBeImpersonated(\Illuminate\Foundation\Auth\User $user)
 *
 * @see \Pbac\Services\PbacService
 */
class Pbac extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'pbac';
    }
}
