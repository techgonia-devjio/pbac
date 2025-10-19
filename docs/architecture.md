# PBAC Architecture

This document explains how the PBAC system works internally.

## System Overview

```
┌─────────────┐
│   Request   │ $user->can('edit', $post)
└──────┬──────┘
       │
       ▼
┌─────────────────────────────┐
│  HasPbacAccessControl Trait │ Intercepts can() method
└──────┬──────────────────────┘
       │
       ▼
┌─────────────────────────────┐
│   PolicyEvaluator Service   │ Core evaluation logic
└──────┬──────────────────────┘
       │
       ├──1. Identify targets (user, groups, teams)
       ├──2. Query matching rules from database
       ├──3. Evaluate DENY rules first
       ├──4. Evaluate ALLOW rules
       └──5. Return true/false
       │
       ▼
┌─────────────────────────────┐
│      Database (Rules)       │ pbac_accesses table
└─────────────────────────────┘
```

---

## Components

### 1. Traits

#### HasPbacAccessControl
**Location**: `src/Traits/HasPbacAccessControl.php`

**Purpose**: Adds `can()` method to User model

**Key Methods**:
```php
public function can($ability, $arguments = []): bool
{
    // 1. Check super admin
    if ($this->is_super_admin) {
        return true;
    }

    // 2. Parse arguments (resource, context)
    [$resource, $context] = $this->parseArguments($arguments);

    // 3. Delegate to PolicyEvaluator
    return app(PolicyEvaluator::class)->evaluate(
        $this,
        $ability,
        $resource,
        $context
    );
}
```

#### HasPbacGroups
**Purpose**: Adds `groups()` relationship

```php
public function groups(): BelongsToMany
{
    return $this->belongsToMany(
        PBACAccessGroup::class,
        'pbac_group_user',
        'user_id',
        'pbac_access_group_id'
    );
}
```

#### HasPbacTeams
**Purpose**: Adds `teams()` relationship

```php
public function teams(): BelongsToMany
{
    return $this->belongsToMany(
        PBACAccessTeam::class,
        'pbac_team_user',
        'user_id',
        'pbac_team_id'
    );
}
```

### 2. PolicyEvaluator Service

**Location**: `src/Services/PolicyEvaluator.php`

**Purpose**: Core permission evaluation engine

**Evaluation Flow**:

```php
public function evaluate($user, $action, $resource, $context = null): bool
{
    // Step 1: Get all target IDs for this user
    $targetIds = $this->determineTargets($user);
    // Returns: [
    //   ['type' => User::class, 'id' => 42],
    //   ['type' => Group::class, 'id' => 1],
    //   ['type' => Team::class, 'id' => 5]
    // ]

    // Step 2: Get resource info
    $resourceInfo = $this->determineResource($resource);
    // Returns: ['type' => Post::class, 'id' => 123]

    // Step 3: Query DENY rules
    $denyRules = $this->queryRules(
        $targetIds,
        $action,
        $resourceInfo,
        'deny'
    );

    // Step 4: Check DENY rules (priority order)
    foreach ($denyRules as $rule) {
        if ($this->evaluateConditions($rule, $resource, $context)) {
            return false; // DENIED - stop immediately
        }
    }

    // Step 5: Query ALLOW rules
    $allowRules = $this->queryRules(
        $targetIds,
        $action,
        $resourceInfo,
        'allow'
    );

    // Step 6: Check ALLOW rules (priority order)
    foreach ($allowRules as $rule) {
        if ($this->evaluateConditions($rule, $resource, $context)) {
            return true; // ALLOWED
        }
    }

    // Step 7: Default deny
    return false;
}
```

### 3. Database Schema

#### pbac_accesses (Main Rules Table)

```sql
CREATE TABLE pbac_accesses (
    id BIGINT PRIMARY KEY,
    pbac_access_target_id BIGINT NULL,  -- FK to pbac_access_targets
    target_id BIGINT NULL,               -- Specific target instance
    pbac_access_resource_id BIGINT NULL, -- FK to pbac_access_resources  
    resource_id BIGINT NULL,             -- Specific resource instance
    action JSON,                         -- ['view', 'edit']
    effect ENUM('allow', 'deny'),
    priority INTEGER DEFAULT 0,
    extras JSON NULL,                    -- Conditions
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

**Indexes**:
```sql
-- For fast target lookups
INDEX (pbac_access_target_id, target_id)

-- For fast resource lookups
INDEX (pbac_access_resource_id, resource_id)

-- For action searching (JSON array)
INDEX (action)

-- For priority sorting
INDEX (effect, priority DESC)
```

#### Supporting Tables

```sql
-- Target types registry
pbac_access_targets (id, type, description, is_active)

// Resource types registry
pbac_access_resources (id, type, description, is_active)

-- Groups
pbac_access_groups (id, name, description)
pbac_group_user (pbac_access_group_id, user_id)

-- Teams
pbac_teams (id, name, description, owner_id)
pbac_team_user (pbac_team_id, user_id)
```

### 4. Laravel Integration

#### Service Provider
**Location**: `src/Providers/PbacProvider.php`

**Responsibilities**:
1. Register services (PolicyEvaluator, PbacService)
2. Register Gate::before hook
3. Register Blade directives

```php
public function packageBooted()
{
    // Gate integration
    Gate::before(function ($user, $ability, $arguments) {
        if (!$this->hasPbacTrait($user)) {
            return null; // Skip PBAC
        }

        if ($this->isSuperAdmin($user)) {
            return true; // Super admin bypass
        }

        // Delegate to PBAC
        $resource = $arguments[0] ?? null;
        return $user->can($ability, $resource);
    });

    // Blade directives
    Blade::directive('pbacCan', function ($args) {
        return "<?php if (auth()->check() && auth()->user()->can({$args})): ?>";
    });
}
```

---

## Execution Flow

### Example: `$user->can('edit', $post)`

```
1. USER CALLS
   $user->can('edit', $post)
   
2. TRAIT INTERCEPTS
   HasPbacAccessControl::can()
   - Checks super admin → false
   - Parses arguments → resource = $post
   - Calls PolicyEvaluator
   
3. POLICY EVALUATOR
   PolicyEvaluator::evaluate($user, 'edit', $post)
   
4. DETERMINE TARGETS
   $user belongs to:
   - User #42
   - Group #1 (Editors)  
   - Team #5 (Dev Team)
   
5. QUERY DENY RULES
   SELECT * FROM pbac_accesses
   WHERE action CONTAINS 'edit'
     AND resource_type = 'Post'
     AND (resource_id = 123 OR resource_id IS NULL)
     AND target_type IN ('User', 'Group', 'Team')
     AND target_id IN (42, 1, 5, NULL)
     AND effect = 'deny'
   ORDER BY priority DESC
   
6. CHECK DENY RULES
   For each deny rule:
     - Check extras conditions
     - If matches → return FALSE
   
7. QUERY ALLOW RULES
   (Same query but effect = 'allow')
   
8. CHECK ALLOW RULES
   For each allow rule:
     - Check extras conditions
     - If matches → return TRUE
   
9. DEFAULT DENY
   No rules matched → return FALSE
```

---

## Performance Optimizations

### 1. Eager Loading

```php
// Bad: N+1 queries
$user->groups; // Query 1
$user->teams;  // Query 2

// Good: Eager load
$user = User::with(['groups', 'teams'])->find(1); // 1 query
```

### 2. Query Optimization

```sql
-- Use indexes
CREATE INDEX idx_target_resource ON pbac_accesses(
    pbac_access_target_id, 
    target_id, 
    pbac_access_resource_id,
    resource_id
);

-- Filter inactive rules
WHERE is_active = TRUE
```

### 3. Caching

```php
// Cache rules per user
Cache::remember("pbac.rules.{$user->id}", 3600, function () {
    return PBACAccessControl::forUser($user)->get();
});
```

### 4. Stop on First Match

```php
// Deny rules
foreach ($denyRules as $rule) {
    if ($this->matches($rule)) {
        return false; // Stop immediately
    }
}

// Allow rules
foreach ($allowRules as $rule) {
    if ($this->matches($rule)) {
        return true; // Stop immediately
    }
}
```

---

## Security Model

### 1. Deny-First

```
Priority of evaluation:
1. DENY rules (any priority)
2. ALLOW rules (any priority)
3. DEFAULT DENY
```

### 2. Secure by Default

```php
// No rules defined
$user->can('anything', $resource); // false

// Must explicitly grant
PBACAccessControl::create([...]);
$user->can('anything', $resource); // true
```

### 3. Explicit Over Implicit

```php
// Bad: Implicit group permission
$user->assignRole('admin'); // What can admin do?

// Good: Explicit rules
PBACAccessControl::factory()
    ->allow()
    ->forGroup($admins)
    ->forResource(Post::class, null)
    ->withAction(['view', 'edit', 'delete'])
    ->create();
```

---

## Extension Points

### 1. Custom Conditions

Extend PolicyEvaluator to add custom condition evaluators:

```php
class CustomPolicyEvaluator extends PolicyEvaluator
{
    protected function evaluateCondition($condition, $resource, $context)
    {
        // Add custom condition types
        if (isset($condition['custom_check'])) {
            return $this->evaluateCustomCheck($condition, $resource);
        }

        return parent::evaluateCondition($condition, $resource, $context);
    }

    protected function evaluateCustomCheck($condition, $resource)
    {
        // Your custom logic
        return true;
    }
}
```

### 2. Custom Logging

```php
class AuditedPolicyEvaluator extends PolicyEvaluator
{
    public function evaluate($user, $action, $resource, $context = null): bool
    {
        $result = parent::evaluate($user, $action, $resource, $context);

        // Log all permission checks
        AuditLog::create([
            'user_id' => $user->id,
            'action' => $action,
            'resource' => $resource,
            'result' => $result,
        ]);

        return $result;
    }
}
```

---

## Testing Architecture

### Unit Tests
Test individual components in isolation:
- PolicyEvaluator logic
- Condition evaluation
- Priority ordering

### Integration Tests
Test component interactions:
- Database queries
- Service provider registration
- Gate integration

### Regression Tests
Ensure bug fixes stay fixed:
- Core functionality
- Previously fixed bugs
- API stability

---

## Next Steps

- [Core Concepts](core-concepts.md) - Understand the components
- [Configuration](configuration.md) - Configure the system
- [Use Cases](use-cases.md) - Apply the architecture

