<?php

namespace Pbac\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * PBAC Facade
 *
 * Provides convenient access to PBAC functionality.
 *
 * @method static bool can(\Illuminate\Database\Eloquent\Model $user, string $action, \Illuminate\Database\Eloquent\Model|string|null $resource = null, array|object|null $context = null)
 * @method static bool cannot(\Illuminate\Database\Eloquent\Model $user, string $action, \Illuminate\Database\Eloquent\Model|string|null $resource = null, array|object|null $context = null)
 * @method static \Pbac\Models\PBACAccessControl allow()
 * @method static \Pbac\Models\PBACAccessControl deny()
 * @method static array getRulesFor(\Illuminate\Database\Eloquent\Model $user, string|null $action = null, string|null $resource = null)
 * @method static array getUserGroups(\Illuminate\Database\Eloquent\Model $user)
 * @method static array getUserTeams(\Illuminate\Database\Eloquent\Model $user)
 * @method static bool isSuperAdmin(\Illuminate\Database\Eloquent\Model $user)
 * @method static void clearCache(\Illuminate\Database\Eloquent\Model|null $user = null)
 * @method static \Pbac\Models\PBACAccessGroup createGroup(string $name, string|null $description = null)
 * @method static \Pbac\Models\PBACAccessTeam createTeam(string $name, string|null $description = null, int|null $ownerId = null)
 * @method static void assignToGroup(\Illuminate\Database\Eloquent\Model $user, \Pbac\Models\PBACAccessGroup|int $group)
 * @method static void assignToTeam(\Illuminate\Database\Eloquent\Model $user, \Pbac\Models\PBACAccessTeam|int $team)
 * @method static void removeFromGroup(\Illuminate\Database\Eloquent\Model $user, \Pbac\Models\PBACAccessGroup|int $group)
 * @method static void removeFromTeam(\Illuminate\Database\Eloquent\Model $user, \Pbac\Models\PBACAccessTeam|int $team)
 * @method static bool hasGroup(\Illuminate\Database\Eloquent\Model $user, string|int $group)
 * @method static bool hasTeam(\Illuminate\Database\Eloquent\Model $user, string|int $team)
 * @method static array getPermissionsFor(\Illuminate\Database\Eloquent\Model $user, string|null $resource = null)
 * @method static \Illuminate\Support\Collection getAllRules()
 * @method static \Illuminate\Support\Collection getAllGroups()
 * @method static \Illuminate\Support\Collection getAllTeams()
 * @method static bool isImpersonating()
 * @method static \Illuminate\Database\Eloquent\Model|null getImpersonator()
 * @method static int|null getImpersonatorId()
 * @method static void startImpersonation(\Illuminate\Database\Eloquent\Model $impersonator, \Illuminate\Database\Eloquent\Model $target)
 * @method static void stopImpersonation()
 * @method static bool canImpersonate(\Illuminate\Database\Eloquent\Model $user, \Illuminate\Database\Eloquent\Model|int $target)
 * @method static bool canBeImpersonated(\Illuminate\Database\Eloquent\Model $user)
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
