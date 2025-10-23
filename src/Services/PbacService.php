<?php

namespace Pbac\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Pbac\Models\PBACAccessControl;
use Pbac\Models\PBACAccessGroup;
use Pbac\Models\PBACAccessTeam;
use Pbac\Models\PBACAccessResource;
use Pbac\Models\PBACAccessTarget;

/**
 * PBAC Service
 *
 * Central service for Policy-Based Access Control operations.
 * Provides utility methods for checking permissions, managing groups/teams,
 * and working with PBAC rules.
 */
class PbacService
{
    public function __construct(public PolicyEvaluator $policyEvaluator)
    {
    }

    /**
     * Check if a user can perform an action on a resource.
     *
     * @param \Illuminate\Foundation\Auth\User $user The user to check
     * @param string $action The action to perform (view, edit, delete, etc.)
     * @param Model|string|null $resource The resource instance or class name
     * @param array|object|null $context Additional context for condition handlers
     * @return bool
     */
    public function can(\Illuminate\Foundation\Auth\User $user, string $action, Model|string|null $resource = null, array|object|null $context = null): bool
    {
        return $this->policyEvaluator->evaluate($user, $action, $resource, $context);
    }

    /**
     * Check if a user cannot perform an action on a resource.
     *
     * @param \Illuminate\Foundation\Auth\User $user
     * @param string $action
     * @param Model|string|null $resource
     * @param array|object|null $context
     * @return bool
     */
    public function cannot(\Illuminate\Foundation\Auth\User $user, string $action, Model|string|null $resource = null, array|object|null $context = null): bool
    {
        return !$this->can($user, $action, $resource, $context);
    }

    /**
     * Create an "allow" rule (fluent interface).
     *
     * @return \Pbac\Support\RuleBuilder
     */
    public function allow()
    {
        return new \Pbac\Support\RuleBuilder('allow');
    }

    /**
     * Create a "deny" rule (fluent interface).
     *
     * @return \Pbac\Support\RuleBuilder
     */
    public function deny()
    {
        return new \Pbac\Support\RuleBuilder('deny');
    }

    /**
     * Get all PBAC rules for a specific user.
     *
     * @param \Illuminate\Foundation\Auth\User $user
     * @param string|null $action Filter by action
     * @param string|null $resource Filter by resource type
     * @return array
     */
    public function getRulesFor(\Illuminate\Foundation\Auth\User $user, ?string $action = null, ?string $resource = null): array
    {
        $groups = $user->groups()->pluck('id')->toArray();
        $teams = method_exists($user, 'teams') ? $user->teams()->pluck('id')->toArray() : [];

        // Get user target type
        $userTargetType = PBACAccessTarget::where('type', get_class($user))->first();
        $groupTargetType = PBACAccessTarget::where('type', PBACAccessGroup::class)->first();
        $teamTargetType = PBACAccessTarget::where('type', PBACAccessTeam::class)->first();

        $query = PBACAccessControl::query()
            ->where(function ($q) use ($user, $groups, $teams, $userTargetType, $groupTargetType, $teamTargetType) {
                // User-specific rules
                if ($userTargetType) {
                    $q->orWhere(function ($subQ) use ($userTargetType, $user) {
                        $subQ->where('pbac_access_target_id', $userTargetType->id)
                            ->where(function ($idQ) use ($user) {
                                $idQ->where('target_id', $user->getKey())
                                    ->orWhereNull('target_id');
                            });
                    });
                }

                // Group rules
                if ($groupTargetType && !empty($groups)) {
                    $q->orWhere(function ($subQ) use ($groupTargetType, $groups) {
                        $subQ->where('pbac_access_target_id', $groupTargetType->id)
                            ->whereIn('target_id', $groups);
                    });
                }

                // Team rules
                if ($teamTargetType && !empty($teams)) {
                    $q->orWhere(function ($subQ) use ($teamTargetType, $teams) {
                        $subQ->where('pbac_access_target_id', $teamTargetType->id)
                            ->whereIn('target_id', $teams);
                    });
                }
            });

        if ($action) {
            $query->whereJsonContains('action', $action);
        }

        if ($resource) {
            $resourceType = PBACAccessResource::where('type', $resource)->first();
            if ($resourceType) {
                $query->where('pbac_access_resource_id', $resourceType->id);
            }
        }

        return $query->with(['targetType', 'resourceType'])->get()->toArray();
    }

    /**
     * Get all groups a user belongs to.
     *
     * @param \Illuminate\Foundation\Auth\User $user
     * @return array
     */
    public function getUserGroups(\Illuminate\Foundation\Auth\User $user): array
    {
        if (!method_exists($user, 'groups')) {
            return [];
        }

        return $user->groups->toArray();
    }

    /**
     * Get all teams a user belongs to.
     *
     * @param \Illuminate\Foundation\Auth\User $user
     * @return array
     */
    public function getUserTeams(\Illuminate\Foundation\Auth\User $user): array
    {
        if (!method_exists($user, 'teams')) {
            return [];
        }

        return $user->teams->toArray();
    }

    /**
     * Check if a user is a super admin.
     *
     * @param \Illuminate\Foundation\Auth\User $user
     * @return bool
     */
    public function isSuperAdmin(\Illuminate\Foundation\Auth\User $user): bool
    {
        $superAdminAttribute = Config::get('pbac.super_admin_attribute');
        return $superAdminAttribute && ($user->{$superAdminAttribute} ?? false);
    }

    /**
     * Clear PBAC cache for a specific user or all users.
     *
     * @param Model|null $user
     * @return void
     */
    public function clearCache(?\Illuminate\Foundation\Auth\User $user = null): void
    {
        if (!Config::get('pbac.cache.enabled', false)) {
            return;
        }

        $prefix = Config::get('pbac.cache.key_prefix', 'pbac:');

        if ($user) {
            Cache::forget($prefix . 'user:' . get_class($user) . ':' . $user->getKey());
        } else {
            // Clear all PBAC cache keys
            Cache::flush();
        }
    }

    /**
     * Create a new group.
     *
     * @param string $name
     * @param string|null $description
     * @return PBACAccessGroup
     */
    public function createGroup(string $name, ?string $description = null): PBACAccessGroup
    {
        return PBACAccessGroup::create([
            'name' => $name,
            'description' => $description,
        ]);
    }

    /**
     * Create a new team.
     *
     * @param string $name
     * @param string|null $description
     * @param int|null $ownerId
     * @return PBACAccessTeam
     */
    public function createTeam(string $name, ?string $description = null, ?int $ownerId = null): PBACAccessTeam
    {
        return PBACAccessTeam::create([
            'name' => $name,
            'description' => $description,
            'owner_id' => $ownerId,
        ]);
    }

    /**
     * Assign a user to a group.
     *
     * @param \Illuminate\Foundation\Auth\User $user
     * @param PBACAccessGroup|int $group
     * @return void
     */
    public function assignToGroup(\Illuminate\Foundation\Auth\User $user, PBACAccessGroup|int $group): void
    {
        $groupId = $group instanceof PBACAccessGroup ? $group->id : $group;

        if (method_exists($user, 'groups')) {
            $user->groups()->syncWithoutDetaching([$groupId]);
        }
    }

    /**
     * Assign a user to a team.
     *
     * @param \Illuminate\Foundation\Auth\User $user
     * @param PBACAccessTeam|int $team
     * @return void
     */
    public function assignToTeam(\Illuminate\Foundation\Auth\User $user, PBACAccessTeam|int $team): void
    {
        $teamId = $team instanceof PBACAccessTeam ? $team->id : $team;

        if (method_exists($user, 'teams')) {
            $user->teams()->syncWithoutDetaching([$teamId]);
        }
    }

    /**
     * Remove a user from a group.
     *
     * @param \Illuminate\Foundation\Auth\User $user
     * @param PBACAccessGroup|int $group
     * @return void
     */
    public function removeFromGroup(\Illuminate\Foundation\Auth\User $user, PBACAccessGroup|int $group): void
    {
        $groupId = $group instanceof PBACAccessGroup ? $group->id : $group;

        if (method_exists($user, 'groups')) {
            $user->groups()->detach($groupId);
        }
    }

    /**
     * Remove a user from a team.
     *
     * @param \Illuminate\Foundation\Auth\User $user
     * @param PBACAccessTeam|int $team
     * @return void
     */
    public function removeFromTeam(\Illuminate\Foundation\Auth\User $user, PBACAccessTeam|int $team): void
    {
        $teamId = $team instanceof PBACAccessTeam ? $team->id : $team;

        if (method_exists($user, 'teams')) {
            $user->teams()->detach($teamId);
        }
    }

    /**
     * Check if a user belongs to a specific group.
     *
     * @param \Illuminate\Foundation\Auth\User $user
     * @param string|int $group Group name or ID
     * @return bool
     */
    public function hasGroup(\Illuminate\Foundation\Auth\User $user, string|int $group): bool
    {
        if (!method_exists($user, 'groups')) {
            return false;
        }

        if (is_int($group)) {
            return $user->groups->contains('id', $group);
        }

        return $user->groups->contains('name', $group);
    }

    /**
     * Check if a user belongs to a specific team.
     *
     * @param \Illuminate\Foundation\Auth\User $user
     * @param string|int $team Team name or ID
     * @return bool
     */
    public function hasTeam(\Illuminate\Foundation\Auth\User $user, string|int $team): bool
    {
        if (!method_exists($user, 'teams')) {
            return false;
        }

        if (is_int($team)) {
            return $user->teams->contains('id', $team);
        }

        return $user->teams->contains('name', $team);
    }

    /**
     * Get all permissions (actions) a user has for a specific resource or globally.
     *
     * @param \Illuminate\Foundation\Auth\User $user
     * @param string|null $resource Filter by resource type
     * @return array Array of actions the user can perform
     */
    public function getPermissionsFor(\Illuminate\Foundation\Auth\User $user, ?string $resource = null): array
    {
        $rules = $this->getRulesFor($user, null, $resource);

        $permissions = [];
        foreach ($rules as $rule) {
            if ($rule['effect'] === 'allow') {
                $permissions = array_merge($permissions, $rule['action']);
            }
        }

        return array_unique($permissions);
    }

    /**
     * Get all PBAC rules.
     *
     * @return Collection
     */
    public function getAllRules(): Collection
    {
        return PBACAccessControl::with(['targetType', 'resourceType'])->get();
    }

    /**
     * Get all groups.
     *
     * @return Collection
     */
    public function getAllGroups(): Collection
    {
        return PBACAccessGroup::all();
    }

    /**
     * Get all teams.
     *
     * @return Collection
     */
    public function getAllTeams(): Collection
    {
        return PBACAccessTeam::all();
    }

    /**
     * Check if currently impersonating another user.
     *
     * @return bool
     */
    public function isImpersonating(): bool
    {
        $sessionKey = config('pbac-ui.impersonation.session_key', 'pbac_impersonator_id');
        return session()->has($sessionKey);
    }

    /**
     * Get the original user (impersonator).
     *
     * @return Model|null
     */
    public function getImpersonator(): ?Model
    {
        $impersonatorId = $this->getImpersonatorId();
        if (!$impersonatorId) {
            return null;
        }

        $userModel = config('auth.providers.users.model', \App\Models\User::class);
        return $userModel::find($impersonatorId);
    }

    /**
     * Get the impersonator's ID from session.
     *
     * @return int|null
     */
    public function getImpersonatorId(): ?int
    {
        $sessionKey = config('pbac-ui.impersonation.session_key', 'pbac_impersonator_id');
        return session()->get($sessionKey);
    }

    /**
     * Start impersonating another user.
     *
     * @param Model $impersonator The user who is impersonating
     * @param Model $target The user to impersonate
     * @return void
     */
    public function startImpersonation(\Illuminate\Foundation\Auth\User $impersonator, Model $target): void
    {
        $sessionKey = config('pbac-ui.impersonation.session_key', 'pbac_impersonator_id');
        session()->put($sessionKey, $impersonator->getKey());

        $guard = config('pbac-ui.impersonation.guard', null);
        auth()->guard($guard)->login($target);
    }

    /**
     * Stop impersonating and return to original user.
     *
     * @return void
     */
    public function stopImpersonation(): void
    {
        $sessionKey = config('pbac-ui.impersonation.session_key', 'pbac_impersonator_id');
        $impersonatorId = session()->pull($sessionKey);

        if ($impersonatorId) {
            $userModel = config('auth.providers.users.model', \App\Models\User::class);
            $originalUser = $userModel::find($impersonatorId);

            if ($originalUser) {
                $guard = config('pbac-ui.impersonation.guard', null);
                auth()->guard($guard)->login($originalUser);
            }
        }
    }

    /**
     * Check if a user can impersonate another user.
     *
     * @param \Illuminate\Foundation\Auth\User $user The user who wants to impersonate
     * @param Model|int $target The target user or user ID
     * @return bool
     */
    public function canImpersonate(\Illuminate\Foundation\Auth\User $user, Model|int $target): bool
    {
        $targetId = $target instanceof Model ? $target->getKey() : $target;

        // Can't impersonate yourself
        if ($user->getKey() == $targetId) {
            return false;
        }

        // Check if user has 'impersonate' permission via PBAC
        $userModel = get_class($user);
        return $this->can($user, 'impersonate', $userModel);
    }

    /**
     * Check if a user can be impersonated.
     *
     * @param \Illuminate\Foundation\Auth\User $user The user to check
     * @return bool
     */
    public function canBeImpersonated(\Illuminate\Foundation\Auth\User $user): bool
    {
        // Check exclude roles
        $excludeRoles = config('pbac-ui.impersonation.exclude_roles', []);
        if (!empty($excludeRoles) && isset($user->role)) {
            if (in_array($user->role, $excludeRoles)) {
                return false;
            }
        }

        // Check exclude emails
        $excludeEmails = config('pbac-ui.impersonation.exclude_emails', []);
        if (!empty($excludeEmails) && isset($user->email)) {
            if (in_array($user->email, $excludeEmails)) {
                return false;
            }
        }

        return true;
    }
}

