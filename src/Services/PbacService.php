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
     * @param Model $user The user to check
     * @param string $action The action to perform (view, edit, delete, etc.)
     * @param Model|string|null $resource The resource instance or class name
     * @param array|object|null $context Additional context for condition handlers
     * @return bool
     */
    public function can(Model $user, string $action, Model|string|null $resource = null, array|object|null $context = null): bool
    {
        return $this->policyEvaluator->evaluate($user, $action, $resource, $context);
    }

    /**
     * Check if a user cannot perform an action on a resource.
     *
     * @param Model $user
     * @param string $action
     * @param Model|string|null $resource
     * @param array|object|null $context
     * @return bool
     */
    public function cannot(Model $user, string $action, Model|string|null $resource = null, array|object|null $context = null): bool
    {
        return !$this->can($user, $action, $resource, $context);
    }

    /**
     * Create an "allow" rule (fluent interface).
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function allow()
    {
        return PBACAccessControl::factory()->allow();
    }

    /**
     * Create a "deny" rule (fluent interface).
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function deny()
    {
        return PBACAccessControl::factory()->deny();
    }

    /**
     * Get all PBAC rules for a specific user.
     *
     * @param Model $user
     * @param string|null $action Filter by action
     * @param string|null $resource Filter by resource type
     * @return array
     */
    public function getRulesFor(Model $user, ?string $action = null, ?string $resource = null): array
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
     * @param Model $user
     * @return array
     */
    public function getUserGroups(Model $user): array
    {
        if (!method_exists($user, 'groups')) {
            return [];
        }

        return $user->groups->toArray();
    }

    /**
     * Get all teams a user belongs to.
     *
     * @param Model $user
     * @return array
     */
    public function getUserTeams(Model $user): array
    {
        if (!method_exists($user, 'teams')) {
            return [];
        }

        return $user->teams->toArray();
    }

    /**
     * Check if a user is a super admin.
     *
     * @param Model $user
     * @return bool
     */
    public function isSuperAdmin(Model $user): bool
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
    public function clearCache(?Model $user = null): void
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
     * @param Model $user
     * @param PBACAccessGroup|int $group
     * @return void
     */
    public function assignToGroup(Model $user, PBACAccessGroup|int $group): void
    {
        $groupId = $group instanceof PBACAccessGroup ? $group->id : $group;

        if (method_exists($user, 'groups')) {
            $user->groups()->syncWithoutDetaching([$groupId]);
        }
    }

    /**
     * Assign a user to a team.
     *
     * @param Model $user
     * @param PBACAccessTeam|int $team
     * @return void
     */
    public function assignToTeam(Model $user, PBACAccessTeam|int $team): void
    {
        $teamId = $team instanceof PBACAccessTeam ? $team->id : $team;

        if (method_exists($user, 'teams')) {
            $user->teams()->syncWithoutDetaching([$teamId]);
        }
    }

    /**
     * Remove a user from a group.
     *
     * @param Model $user
     * @param PBACAccessGroup|int $group
     * @return void
     */
    public function removeFromGroup(Model $user, PBACAccessGroup|int $group): void
    {
        $groupId = $group instanceof PBACAccessGroup ? $group->id : $group;

        if (method_exists($user, 'groups')) {
            $user->groups()->detach($groupId);
        }
    }

    /**
     * Remove a user from a team.
     *
     * @param Model $user
     * @param PBACAccessTeam|int $team
     * @return void
     */
    public function removeFromTeam(Model $user, PBACAccessTeam|int $team): void
    {
        $teamId = $team instanceof PBACAccessTeam ? $team->id : $team;

        if (method_exists($user, 'teams')) {
            $user->teams()->detach($teamId);
        }
    }

    /**
     * Check if a user belongs to a specific group.
     *
     * @param Model $user
     * @param string|int $group Group name or ID
     * @return bool
     */
    public function hasGroup(Model $user, string|int $group): bool
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
     * @param Model $user
     * @param string|int $team Team name or ID
     * @return bool
     */
    public function hasTeam(Model $user, string|int $team): bool
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
     * @param Model $user
     * @param string|null $resource Filter by resource type
     * @return array Array of actions the user can perform
     */
    public function getPermissionsFor(Model $user, ?string $resource = null): array
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
}

