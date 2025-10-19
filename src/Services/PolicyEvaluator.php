<?php

namespace Modules\Pbac\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Modules\Pbac\Models\PBACAccessControl;
use Modules\Pbac\Models\PBACAccessTarget;
use Modules\Pbac\Models\PBACAccessResource;
use Modules\Pbac\Support\PbacLogger;
use Modules\Pbac\Traits\HasPbacAccessControl;
use Illuminate\Support\Str;
// No need for phpDocumentor\Reflection\Types\ClassString; if not directly used for type hinting non-existing classes

class PolicyEvaluator
{

    public function __construct(public ?PbacLogger $logger = null)
    {
        if ($this->logger === null) {
            $this->logger = new PbacLogger();
        }
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
        // Handle unregistered/inactive resource types based on configuration
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
        [$targetTypeStrings, $targetIds] = $this->determineTargets($user);

        // 5. Resolve Target Type IDs and Check Activity
        $targetTypeEntries = $this->resolveTargetTypeEntries($targetTypeStrings, $action, $resourceTypeString, $resourceId);
        if (($targetTypeEntries && $targetTypeEntries->count() == 0) && !empty($targetTypeStrings)) {
            // This means target types were identified on the user (e.g., user is of type TestUser),
            // but none of those types are registered or active in pbac_access_targets.
            $this->logger->info("PBAC Deny: User's target types are not registered or are inactive.");

            return false;
        }

        // 6. Query Matching Rules
        $matchingRules = $this->queryMatchingRules($action, $resourceTypeId, $resourceId, $targetTypeEntries, $targetIds);

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
        if (!in_array(Config::get('pbac.traits.access_control'), class_uses($user))) {
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
            return null; // No resource type specified
        }

        $resourceTypeEntry = PBACAccessResource::where('type', $resourceTypeString)->first();

        if (!$resourceTypeEntry) {
            $this->logger->warning("PBAC Warning: Resource type '{$resourceTypeString}' not registered in pbac_access_resources.");
            return null; // Resource type not found
        }

        if (!$resourceTypeEntry->is_active) {
            $this->logger->warning("PBAC Warning: Resource type '{$resourceTypeString}' is inactive.");
            return null; // Resource type is inactive
        }

        return $resourceTypeEntry->id;
    }

    /**
     * Determine all the target types (classes) and IDs for the given user,
     * including those from related entities via PBAC traits.
     *
     * @param  Model  $user
     * @return array [array $targetTypeStrings, array $targetIds]
     */
    protected function determineTargets(Model $user): array
    {
        $targetTypeStrings = [get_class($user)]; // User itself is a target type string
        $targetIds = [$user->getKey()]; // User's specific ID

        $userTraits = class_uses($user);
        $configuredTraits = Config::get('pbac.traits', []);

        foreach ($configuredTraits as $traitName => $traitClass) {
            // Skip the core access control trait
            if ($traitClass === HasPbacAccessControl::class) {
                continue;
            }

            // Check if the user model uses this specific target trait
            if (in_array($traitClass, $userTraits)) {
                // Assume the trait provides a relationship method named after the trait key (pluralized)
                $relationName = Str::camel(Str::plural($traitName)); // e.g., 'groups', 'teams'

                if (method_exists($user, $relationName)) {
                    $relatedEntities = $user->{$relationName}; // Get the related groups/teams etc.

                    if ($relatedEntities instanceof Collection) {
                        if ($relatedEntities->isNotEmpty()) {
                            // Get the class name of the related model (e.g., PBACAccessGroup::class)
                            // This gets the class name of the *related model instance*, not the trait.
                            $relatedModelClass = get_class($relatedEntities->first());
                            $targetTypeStrings[] = $relatedModelClass;
                            $targetIds = array_merge($targetIds, $relatedEntities->pluck('id')->toArray());
                        }
                    } elseif ($relatedEntities instanceof Model) { // Handle single related models (e.g., belongsTo)
                        $targetTypeStrings[] = get_class($relatedEntities);
                        $targetIds[] = $relatedEntities->getKey();
                    }
                } else {
                    $this->logger->warning("PBAC Warning: User model uses trait '{$traitClass}' but is missing the expected relationship method '{$relationName}'.");

                }
            }
        }

        // Ensure unique target type strings and IDs
        $targetTypeStrings = array_unique($targetTypeStrings);
        $targetIds = array_unique($targetIds);

        return [$targetTypeStrings, $targetIds];
    }

    /**
     * Resolve target type entries from the database and check if they are active.
     * Returns a collection of matching target type entries.
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
            ->where('is_active', true) // Only consider active target types
            ->get();

        // Check if all target types identified on the user are actually registered AND active
        foreach ($targetTypeStrings as $typeString) {
            $entryFound = $targetTypeEntries->firstWhere('type', $typeString);
            if (!$entryFound) {
                // If a type is either not registered or not active, and strict rules apply,
                // then no rules associated with this target type can apply.
                    $this->logger->warning("PBAC Warning: Target type '{$typeString}' used by user is not registered or is inactive in pbac_access_targets.");

                // If any identified target type is invalid, we return an empty collection
                // to prevent rules for these invalid types from being matched.
                return new Collection();
            }
        }

        return $targetTypeEntries;
    }


    /**
     * Query the database for matching access rules based on the provided criteria.
     *
     * @param string $action
     * @param int|null $resourceTypeId
     * @param int|null $resourceId
     * @param \Illuminate\Support\Collection $targetTypeEntries
     * @param array $targetIds
     * @return \Illuminate\Support\Collection
     */
    protected function queryMatchingRules(string $action, ?int $resourceTypeId, ?int $resourceId, Collection $targetTypeEntries, array $targetIds): Collection
    {
        $targetTypeIds = $targetTypeEntries->pluck('id')->toArray();

        $matchingRulesQuery = PBACAccessControl::query()
            // Action check
            ->whereJsonContains('action', $action)
            ->where(function ($query) use ($resourceTypeId, $resourceId) {
                // Resource Type and ID matching
                // This covers: specific resource instance, any instance of a specific resource type, or any resource type (if $resourceTypeId is null)
                $query->where(function ($q) use ($resourceTypeId, $resourceId) {
                    $q->where('pbac_access_resource_id', $resourceTypeId) // Rule is for this specific resource type
                    ->where(function($subQ) use ($resourceId) {
                        $subQ->where('resource_id', $resourceId) // Rule is for this specific resource ID
                        ->orWhereNull('resource_id');       // OR rule is for any resource ID of this type
                    });
                })->orWhere(function ($q) {
                    $q->whereNull('pbac_access_resource_id'); // Rule applies to *any* resource type
                });
            })
            ->where(function ($query) use ($targetTypeIds, $targetIds) {
                // Target Type and ID matching
                // This covers: specific target instance, any instance of a specific target type, or any target type at all.
                $query->where(function($q) use ($targetTypeIds, $targetIds) {
                    $q->whereIn('pbac_access_target_id', $targetTypeIds) // Rule is for one of the user's target types
                    ->where(function($subQ) use ($targetIds) {
                        $subQ->whereIn('target_id', $targetIds) // Rule is for one of the user's specific target IDs
                        ->orWhereNull('target_id');       // OR rule is for any target ID of these types (e.g., any TestUser)
                    });
                })->orWhere(function($q) {
                    $q->whereNull('pbac_access_target_id'); // Rule applies to *any* target type (and thus any target ID)
                });
            })
            ->orderBy('priority', 'desc'); // Evaluate higher priority rules first
        // dump($matchingRulesQuery->toRawSql());
        $this->logger->debug($matchingRulesQuery->toRawSql());
        $matchingRules = $matchingRulesQuery->get();

        return $matchingRules;
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
            $context = []; // Default to empty array if not array or object
        }

        // Separate deny and allow rules
        $denyRules = $matchingRules->where('effect', 'deny');
        $allowRules = $matchingRules->where('effect', 'allow');

        // 1. Evaluate Deny Rules (Deny takes precedence)
        foreach ($denyRules as $rule) {
            // Check if the rule's conditions (from 'extras') are met by the current context.
            if ($this->checkRuleConditions($rule, $user, $action, $resourceTypeString, $resourceId, $context)) {
                $this->logger->info("PBAC Deny: User ID {$user->getKey()} denied action '{$action}' on resource type '{$resourceTypeString}' (ID: {$resourceId}) by rule ID {$rule->id} (Contextual Deny).");

                return false; // Deny rule applies and its conditions are met
            }
        }

        // 2. Evaluate Allow Rules
        foreach ($allowRules as $rule) {
            // Check if the rule's conditions (from 'extras') are met by the current context.
            if ($this->checkRuleConditions($rule, $user, $action, $resourceTypeString, $resourceId, $context)) {
                    $this->logger->info("PBAC Allow: User ID {$user->getKey()} allowed action '{$action}' on resource type '{$resourceTypeString}' (ID: {$resourceId}) by rule ID {$rule->id} (Contextual Allow).");

                return true; // Allow rule applies and its conditions are met
            }
        }
        // If no matching allow rule (that also satisfies its conditions) was found after all checks
        $this->logger->info("PBAC Default Deny: User ID {$user->getKey()} denied action '{$action}' on resource type '{$resourceTypeString}' (ID: {$resourceId}). No matching allow rule found after context evaluation.");
        return false; // Default to deny
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
     * @return bool True if the rule's conditions are met, or if there are no conditions in 'extras'.
     */
    protected function checkRuleConditions(PBACAccessControl $rule, Model $user, string $action, ?string $resourceTypeString, ?int $resourceId, array $context): bool
    {
        // If the rule has no 'extras' data or it's empty, it means no additional conditions, so it passes.
        if (empty($rule->extras)) {
            return true;
        }

        // Access the conditions from the 'extras' attribute.
        // Assuming 'extras' is cast to array in PBACAccessControl model.
        $conditions = $rule->extras;

        // --- Add your common context-based condition checks here ---
        // These are examples; adapt them to your specific business rules.

        // Example 1: Check for 'min_level' in context
        if (isset($conditions['min_level']) && isset($context['user_level'])) {
            if ($context['user_level'] < $conditions['min_level']) {
                $this->logger->debug("Rule ID {$rule->id} failed 'min_level' condition: user level {$context['user_level']} < required {$conditions['min_level']}.");
                return false;
            }
        }

        // Example 2: Check for 'allowed_ips' in context (CIDR or specific IP)
        if (isset($conditions['allowed_ips']) && isset($context['ip_address'])) {
            $allowedIps = (array) $conditions['allowed_ips'];
            $userIp = $context['ip_address'];
            $ipMatched = false;
            foreach ($allowedIps as $allowedIp) {
                if (str_contains($allowedIp, '/')) { // It's a CIDR
                    if ($this->isIpInCidr($userIp, $allowedIp)) {
                        $ipMatched = true;
                        break;
                    }
                } elseif ($userIp === $allowedIp) { // Direct IP match
                    $ipMatched = true;
                    break;
                }
            }
            if (!$ipMatched) {
                $this->logger->debug("Rule ID {$rule->id} failed 'allowed_ips' condition: IP {$userIp} not in allowed list.");
                return false;
            }
        }

        // Example 3: Check for 'requires_attribute_value' on the resource itself (if available)
        // This is a more advanced example that crosses context and resource attributes.
        // For simplicity, we'll assume the $resource (model instance) is available or passed around.
        // If the resource is just a string, you might need to load it here, or pass it to evaluate().
        // For this method, let's assume if $resourceId is present, we can get the original Model if needed.
        if (isset($conditions['requires_attribute_value']) && $resourceId !== null && $resourceTypeString !== null) {
            $requiredAttrs = (array) $conditions['requires_attribute_value'];
            try {
                $resourceModel = app($resourceTypeString)->find($resourceId);
                if ($resourceModel) {
                    foreach ($requiredAttrs as $attribute => $requiredValue) {
                        if (!isset($resourceModel->{$attribute}) || $resourceModel->{$attribute} !== $requiredValue) {
                            $this->logger->debug("Rule ID {$rule->id} failed 'requires_attribute_value' condition for resource {$resourceTypeString}:{$resourceId}. Attribute '{$attribute}' does not match required value.");
                            return false;
                        }
                    }
                } else {
                    $this->logger->debug("Rule ID {$rule->id} could not load resource for 'requires_attribute_value' condition: {$resourceTypeString}:{$resourceId}.");
                    return false; // Cannot evaluate condition if resource not found
                }
            } catch (\Throwable $e) {
                $this->logger->error("PBAC Error: Failed to evaluate 'requires_attribute_value' for rule ID {$rule->id}: " . $e->getMessage());
                return false;
            }
        }

        // If you want to support dynamically loaded condition classes (as you mentioned "create separate class with just a simple method handle")
        // You can have a "condition_class" key in your extras:
        /*
        if (isset($conditions['condition_class']) && class_exists($conditions['condition_class'])) {
            try {
                $conditionHandler = app($conditions['condition_class']); // Resolve from container for dependency injection
                if (method_exists($conditionHandler, 'handle')) {
                    // Pass all relevant data to the custom condition handler
                    if (!$conditionHandler->handle($user, $action, $resourceTypeString, $resourceId, $context, $conditions)) {
                        $this->logDebug("Rule ID {$rule->id} failed custom condition class '{$conditions['condition_class']}'.");
                        return false;
                    }
                } else {
                    $this->logError("PBAC Error: Condition class '{$conditions['condition_class']}' for rule ID {$rule->id} missing 'handle' method.");
                    return false;
                }
            } catch (\Throwable $e) {
                $this->logError("PBAC Error: Exception in custom condition class '{$conditions['condition_class']}' for rule ID {$rule->id}: " . $e->getMessage());
                return false;
            }
        }
        */

        // If all conditions pass or no conditions are defined
        return true;
    }

    /**
     * Helper function to check if an IP address is within a CIDR range.
     *
     * @param string $ip
     * @param string $cidr
     * @return bool
     */
    protected function isIpInCidr(string $ip, string $cidr): bool
    {
        // For a robust solution, consider using a dedicated IP library like 'php-ip/php-ip'
        // This is a basic implementation suitable for common IPv4 CIDR checks.
        if (!str_contains($cidr, '/')) {
            return $ip === $cidr; // Not a CIDR, just a single IP comparison
        }

        [$subnet, $mask] = explode('/', $cidr);
        $subnetLong = ip2long($subnet);
        $ipLong = ip2long($ip);
        $maskLong = ~((1 << (32 - $mask)) - 1); // Calculate the bitmask

        return ($ipLong & $maskLong) === ($subnetLong & $maskLong);
    }

}
