<?php

namespace Pbac\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Pbac\Contracts\ConditionHandlerInterface;
use Pbac\Models\PBACAccessControl;
use Pbac\Models\PBACAccessTarget;
use Pbac\Models\PBACAccessResource;
use Pbac\Support\PbacLogger;
use Pbac\Traits\HasPbacAccessControl;
use Illuminate\Support\Str;

class PolicyEvaluator
{
    /**
     * @var array<string, string>
     */
    protected array $conditionHandlers;

    public function __construct(public ?PbacLogger $logger = null)
    {
        if ($this->logger === null) {
            $this->logger = new PbacLogger();
        }
        $this->conditionHandlers = Config::get('pbac.condition_handlers', []);
    }

    /**
     * Evaluate user access based on action, resource, and context.
     *
     * @param Model $user The user model requesting access.
     * @param string $action The action being performed (e.g., 'view', 'edit').
     * @param Model|string|null $resource The resource model or its class name, or null if action is not resource-specific.
     * @param array|object|null $context Additional data relevant to the access decision.
     * @return bool
     */
    public function evaluate(Model $user, string $action, Model|string|null $resource = null, array|object|null $context = null): bool
    {
        // 1. Validate User Model
        if (!$this->validateUser($user)) {
            return false;
        }

        // 2. Determine Resource Type and ID
        [$resourceTypeString, $resourceId] = $this->determineResource($resource);

        // 3. Resolve Resource Type ID and Check Activity
        $resourceTypeId = $this->resolveResourceTypeId($resourceTypeString, $action, $resourceId);

        $is_strict_resource_registration = Config::get('pbac.strict_resource_registration');
        if ($is_strict_resource_registration) {
            if ($resourceTypeString !== null && $resourceTypeId === null) {
                // This means the resource type was specified but not found or inactive, and strict mode is on
                $this->logger->info("PBAC Deny: Strict resource registration is enabled, and resource type '{$resourceTypeString}' is not registered or inactive.");
                return false;
            }
        } else {
            // If not in strict mode, and resource type is not found/inactive,
            // treat it as if no specific resource type was provided for rule querying.
            if ($resourceTypeString !== null && $resourceTypeId === null) {
                $this->logger->debug("PBAC Info: Non-strict mode, resource type '{$resourceTypeString}' not found or inactive. Proceeding with general resource rules.");
                $resourceTypeString = null; // Effectively ignore the specific type for rule matching
                $resourceId = null; // And ignore the specific ID if it was tied to the type
            }
        }

        // 4. Determine Target Types and IDs for the User
        $targetsByType = $this->determineTargets($user);
        $targetTypeStrings = array_keys($targetsByType);

        // 5. Resolve Target Type IDs and Check Activity
        $targetTypeEntries = $this->resolveTargetTypeEntries($targetTypeStrings, $action, $resourceTypeString, $resourceId);
        if (($targetTypeEntries && $targetTypeEntries->count() == 0) && !empty($targetTypeStrings)) {
            // This means target types were identified on the user (e.g., user is of type TestUser),
            // but none of those types are registered or active in pbac_access_targets.
            $this->logger->info("PBAC Deny: User's target types are not registered or are inactive.");

            return false;
        }

        // 6. Query Matching Rules
        $matchingRules = $this->queryMatchingRules($action, $resourceTypeId, $resourceId, $targetTypeEntries, $targetsByType);

        // 7. Evaluate Rules, now passing the context
        return $this->evaluateRules($user, $action, $resourceTypeString, $resourceId, $matchingRules, $context);
    }

    /**
     * Validate the user model and ensure it uses the core PBAC trait.
     *
     * @param  Model  $user
     * @return bool
     */
    protected function validateUser(Model $user): bool
    {
        if (!in_array(Config::get('pbac.traits.access_control'), class_uses_recursive($user))) {
            $this->logger->error("PBAC Error: User model " . get_class($user) . " does not use the HasPbacAccessControl trait.");
            return false;
        }
        return true;
    }

    /**
     * Determine the resource type string and ID from the provided resource argument.
     *
     * @param  Model|string|null $resource
     * @return array [string|null $resourceTypeString, int|null $resourceId]
     */
    protected function determineResource(Model|string|null $resource): array
    {
        $resourceTypeString = is_string($resource) ? $resource : ($resource ? get_class($resource) : null);
        $resourceId = $resource instanceof Model ? $resource->getKey() : null;

        return [$resourceTypeString, $resourceId];
    }

    /**
     * Resolve the resource type ID from the database and check if it's active.
     * Returns null if resourceTypeString is null, or if the type is not found or inactive.
     *
     * @param string|null $resourceTypeString
     * @param string $action For logging purposes.
     * @param int|null $resourceId For logging purposes.
     * @return int|null The resource type ID, or null.
     */
    protected function resolveResourceTypeId(?string $resourceTypeString, string $action, ?int $resourceId): ?int
    {
        if ($resourceTypeString === null) {
            return null;
        }

        $resourceTypeEntry = PBACAccessResource::where('type', $resourceTypeString)->first();

        if (!$resourceTypeEntry) {
            $this->logger->warning("PBAC Warning: Resource type '{$resourceTypeString}' not registered in pbac_access_resources.");
            return null;
        }

        if (!$resourceTypeEntry->is_active) {
            $this->logger->warning("PBAC Warning: Resource type '{$resourceTypeString}' is inactive.");
            return null;
        }

        return $resourceTypeEntry->id;
    }

    /**
     * Determine all the target types and their corresponding IDs for the given user.
     *
     * @param  Model  $user
     * @return array An associative array mapping target type strings to an array of their IDs.
     * Example: ['App\Models\User' => [1], 'App\Models\Team' => [5, 10]]
     */
    protected function determineTargets(Model $user): array
    {
        $targetsByType = [];
        $targetsByType[get_class($user)][] = $user->getKey();

        $userTraits = class_uses_recursive($user);
        $configuredTraits = Config::get('pbac.traits', []);

        foreach ($configuredTraits as $traitName => $traitClass) {
            if ($traitClass === HasPbacAccessControl::class) {
                continue;
            }

            if (in_array($traitClass, $userTraits)) {
                $relationName = Str::camel(Str::plural($traitName)); // e.g., 'groups', 'teams'

                if (method_exists($user, $relationName)) {
                    $relatedEntities = $user->{$relationName};
                    $entities = $relatedEntities instanceof Model ? new Collection([$relatedEntities]) : $relatedEntities;

                    if ($entities instanceof Collection && $entities->isNotEmpty()) {
                        $relatedModelClass = get_class($entities->first());
                        $ids = $entities->pluck('id')->toArray();

                        if (!isset($targetsByType[$relatedModelClass])) {
                            $targetsByType[$relatedModelClass] = [];
                        }
                        $targetsByType[$relatedModelClass] = array_merge($targetsByType[$relatedModelClass], $ids);
                    }
                } else {
                    $this->logger->warning("PBAC Warning: User model uses trait '{$traitClass}' but is missing the expected relationship method '{$relationName}'.");
                }
            }
        }

        foreach ($targetsByType as $type => $ids) {
            $targetsByType[$type] = array_unique($ids);
        }

        return $targetsByType;
    }

    /**
     * Resolve target type entries from the database and check if they are active.
     *
     * @param array $targetTypeStrings
     * @param string $action For logging purposes.
     * @param string|null $resourceTypeString For logging purposes.
     * @param int|null $resourceId For logging purposes.
     * @return \Illuminate\Support\Collection
     */
    protected function resolveTargetTypeEntries(array $targetTypeStrings, string $action, ?string $resourceTypeString, ?int $resourceId): Collection
    {
        // If there are no target types derived from the user (shouldn't happen for a validated user, but for safety)
        if (empty($targetTypeStrings)) {
            return new Collection();
        }

        $targetTypeEntries = PBACAccessTarget::whereIn('type', $targetTypeStrings)
                                             ->where('is_active', true)
                                             ->get();

        foreach ($targetTypeStrings as $typeString) {
            if (!$targetTypeEntries->firstWhere('type', $typeString)) {
                $this->logger->warning("PBAC Warning: Target type '{$typeString}' used by user is not registered or is inactive in pbac_access_targets.");
                return new Collection();
            }
        }

        return $targetTypeEntries;
    }


    /**
     * Query the database for matching access rules based on specific (type, id) pairs.
     *
     * @param string $action
     * @param int|null $resourceTypeId
     * @param int|null $resourceId
     * @param Collection $targetTypeEntries A collection of resolved PBACAccessTarget models.
     * @param array $targetsByType The structured map of target type strings to their IDs.
     * @return Collection
     */
    protected function queryMatchingRules(string $action, ?int $resourceTypeId, ?int $resourceId, Collection $targetTypeEntries, array $targetsByType): Collection
    {
        $matchingRulesQuery = PBACAccessControl::query()
                                               ->whereJsonContains('action', $action)
                                               ->where(function ($query) use ($resourceTypeId, $resourceId) {
                                                   $query->where(function ($q) use ($resourceTypeId, $resourceId) {
                                                       $q->where('pbac_access_resource_id', $resourceTypeId)
                                                         ->where(function($subQ) use ($resourceId) {
                                                             $subQ->where('resource_id', $resourceId)
                                                                  ->orWhereNull('resource_id');
                                                         });
                                                   })->orWhere(function ($q) {
                                                       $q->whereNull('pbac_access_resource_id');
                                                   });
                                               })
                                               ->where(function ($query) use ($targetTypeEntries, $targetsByType) {
                                                   foreach ($targetsByType as $typeString => $ids) {
                                                       $targetTypeEntry = $targetTypeEntries->firstWhere('type', $typeString);
                                                       if ($targetTypeEntry) {
                                                           $query->orWhere(function ($q) use ($targetTypeEntry, $ids) {
                                                               $q->where('pbac_access_target_id', $targetTypeEntry->id)
                                                                 ->where(function ($subQ) use ($ids) {
                                                                     $subQ->whereIn('target_id', $ids)
                                                                          ->orWhereNull('target_id');
                                                                 });
                                                           });
                                                       }
                                                   }
                                                   $query->orWhereNull('pbac_access_target_id');
                                               })
                                               ->orderBy('priority', 'desc');

        $this->logger->debug($matchingRulesQuery->toRawSql());
        return $matchingRulesQuery->get();
    }

    /**
     * Evaluate the matching rules, giving precedence to deny rules.
     *
     * @param  Model  $user
     * @param string $action
     * @param string|null $resourceTypeString
     * @param int|null $resourceId
     * @param \Illuminate\Support\Collection $matchingRules
     * @param array|object|null $context Additional context for conditional rule evaluation.
     * @return bool
     */
    protected function evaluateRules(Model $user, string $action, ?string $resourceTypeString, ?int $resourceId, Collection $matchingRules, array|object|null $context): bool
    {
        // Normalize context to an array if it's null or an object for consistent access
        if (is_object($context) && method_exists($context, 'toArray')) {
            $context = $context->toArray();
        } elseif (!is_array($context)) {
            $context = [];
        }

        $denyRules = $matchingRules->where('effect', 'deny');
        $allowRules = $matchingRules->where('effect', 'allow');

        // 1. Evaluate Deny Rules (Deny takes precedence)
        foreach ($denyRules as $rule) {
            // Check if the rule's conditions (from 'extras') are met by the current context.
            if ($this->checkRuleConditions($rule, $user, $action, $resourceTypeString, $resourceId, $context)) {
                $this->logger->info("PBAC Deny: User ID {$user->getKey()} denied action '{$action}' on resource '{$resourceTypeString}' (ID: {$resourceId}) by rule ID {$rule->id}.");
                return false;
            }
        }

        foreach ($allowRules as $rule) {
            if ($this->checkRuleConditions($rule, $user, $action, $resourceTypeString, $resourceId, $context)) {
                $this->logger->info("PBAC Allow: User ID {$user->getKey()} allowed action '{$action}' on resource '{$resourceTypeString}' (ID: {$resourceId}) by rule ID {$rule->id}.");
                return true;
            }
        }
        $this->logger->info("PBAC Default Deny: User ID {$user->getKey()} denied action '{$action}' on resource '{$resourceTypeString}' (ID: {$resourceId}). No matching allow rule found.");
        return false;
    }

    /**
     * Checks if a rule's 'extras' conditions are met by the current context.
     * This method is designed to be extensible for different condition types.
     *
     * @param PBACAccessControl $rule The rule being evaluated.
     * @param Model $user The user model.
     * @param string $action The action being performed.
     * @param string|null $resourceTypeString The resource type string.
     * @param int|null $resourceId The resource ID.
     * @param array $context The contextual data.
     * @return bool True if all the rule's conditions are met.
     */
    protected function checkRuleConditions(PBACAccessControl $rule, Model $user, string $action, ?string $resourceTypeString, ?int $resourceId, array $context): bool
    {
        if (empty($rule->extras)) {
            return true;
        }

        $conditions = $rule->extras;

        foreach ($conditions as $conditionKey => $conditionValue) {
            // Check if a handler is registered for this condition key
            if (!isset($this->conditionHandlers[$conditionKey])) {
                $this->logger->warning("PBAC Warning: No condition handler registered for key '{$conditionKey}' in rule ID {$rule->id}. The rule will be considered as failing.");
                // Fail-safe: If a condition is defined but has no handler,
                // we must deny the rule to prevent accidental access.
                return false;
            }

            try {
                $handlerClass = $this->conditionHandlers[$conditionKey];
                /** @var ConditionHandlerInterface $handler */
                $handler = app($handlerClass);

                // If any handler returns false, the entire condition check fails.
                if (!$handler->handle($user, $action, $resourceTypeString, $resourceId, $context, $conditionValue, $rule)) {
                    return false;
                }
            } catch (\Throwable $e) {
                $this->logger->error("PBAC Error: Exception in condition handler '{$handlerClass}' for rule ID {$rule->id}: " . $e->getMessage());
                return false; // Fail safely if a handler throws an exception
            }
        }

        // If the loop completes, it means every condition had a registered handler and every handler returned true.
        return true;
    }
}

