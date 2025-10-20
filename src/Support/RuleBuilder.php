<?php

namespace Pbac\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Pbac\Models\PBACAccessControl;
use Pbac\Models\PBACAccessGroup;
use Pbac\Models\PBACAccessTeam;
use Pbac\Models\PBACAccessResource;
use Pbac\Models\PBACAccessTarget;

/**
 * Rule Builder
 *
 * Provides a fluent interface for creating PBAC rules.
 *
 * Example:
 *   Pbac::allow()
 *       ->forGroup($group)
 *       ->forResource(Post::class, null)
 *       ->withAction(['view', 'edit'])
 *       ->withPriority(80)
 *       ->create();
 */
class RuleBuilder
{
    protected string $effect;
    protected ?int $targetTypeId = null;
    protected ?int $targetId = null;
    protected ?int $resourceTypeId = null;
    protected ?int $resourceId = null;
    protected array|string $action = [];
    protected int $priority = 0;
    protected ?array $extras = null;

    public function __construct(string $effect = 'allow')
    {
        $this->effect = $effect;
    }

    /**
     * Set the target to a specific user.
     *
     * @param Model $user
     * @return $this
     */
    public function forUser(Model $user): self
    {
        return $this->forTarget(get_class($user), $user->getKey());
    }

    /**
     * Set the target to a group.
     *
     * @param PBACAccessGroup|int $group
     * @return $this
     */
    public function forGroup(PBACAccessGroup|int $group): self
    {
        $groupId = $group instanceof PBACAccessGroup ? $group->id : $group;
        return $this->forTarget(PBACAccessGroup::class, $groupId);
    }

    /**
     * Set the target to a team.
     *
     * @param PBACAccessTeam|int $team
     * @return $this
     */
    public function forTeam(PBACAccessTeam|int $team): self
    {
        $teamId = $team instanceof PBACAccessTeam ? $team->id : $team;
        return $this->forTarget(PBACAccessTeam::class, $teamId);
    }

    /**
     * Set the target type and ID.
     *
     * @param string|null $targetType
     * @param int|null $targetId
     * @return $this
     */
    public function forTarget(?string $targetType, ?int $targetId = null): self
    {
        if ($targetType === null) {
            $this->targetTypeId = null;
            $this->targetId = null;
            return $this;
        }

        $target = PBACAccessTarget::firstOrCreate(['type' => $targetType]);
        $this->targetTypeId = $target->id;
        $this->targetId = $targetId;

        return $this;
    }

    /**
     * Set the resource type and ID.
     *
     * @param string|null $resourceType
     * @param int|null $resourceId
     * @return $this
     */
    public function forResource(?string $resourceType, ?int $resourceId = null): self
    {
        if ($resourceType === null) {
            $this->resourceTypeId = null;
            $this->resourceId = null;
            return $this;
        }

        $resource = PBACAccessResource::firstOrCreate(['type' => $resourceType]);
        $this->resourceTypeId = $resource->id;
        $this->resourceId = $resourceId;

        return $this;
    }

    /**
     * Set the action(s).
     *
     * @param array|string $action
     * @return $this
     */
    public function withAction(array|string $action): self
    {
        $this->action = Arr::wrap($action);
        return $this;
    }

    /**
     * Set the priority.
     *
     * @param int $priority
     * @return $this
     */
    public function withPriority(int $priority): self
    {
        $this->priority = $priority;
        return $this;
    }

    /**
     * Set the extras (conditions).
     *
     * @param array $extras
     * @return $this
     */
    public function withExtras(array $extras): self
    {
        $this->extras = $extras;
        return $this;
    }

    /**
     * Create the rule in the database.
     *
     * @param array $overrides Additional attributes to set
     * @return PBACAccessControl
     */
    public function create(array $overrides = []): PBACAccessControl
    {
        $attributes = [
            'pbac_access_target_id' => $this->targetTypeId,
            'target_id' => $this->targetId,
            'pbac_access_resource_id' => $this->resourceTypeId,
            'resource_id' => $this->resourceId,
            'action' => $this->action,
            'effect' => $this->effect,
            'priority' => $this->priority,
            'extras' => $this->extras,
        ];

        // Merge with overrides
        $attributes = array_merge($attributes, $overrides);

        // Handle extras separately if in overrides
        if (isset($overrides['extras'])) {
            $attributes['extras'] = $overrides['extras'];
        }

        return PBACAccessControl::create($attributes);
    }

    /**
     * Get the current state as an array (for debugging).
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'effect' => $this->effect,
            'pbac_access_target_id' => $this->targetTypeId,
            'target_id' => $this->targetId,
            'pbac_access_resource_id' => $this->resourceTypeId,
            'resource_id' => $this->resourceId,
            'action' => $this->action,
            'priority' => $this->priority,
            'extras' => $this->extras,
        ];
    }
}
