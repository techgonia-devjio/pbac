# Configuration Reference

This guide explains all configuration options available in `config/pbac.php`.

## Table of Contents

1. [User Model](#user-model)
2. [Super Admin Attribute](#super-admin-attribute)
3. [Strict Registration](#strict-registration)
4. [Traits](#traits)
5. [Condition Handlers](#condition-handlers)
6. [Models](#models)
7. [Supported Actions](#supported-actions)
8. [Caching](#caching)
9. [Logging](#logging)

---

## User Model

```php
'user_model' => \App\Models\User::class,
```

**Type**: `string` (Fully-qualified class name)  
**Default**: `\App\Models\User::class`  
**Required**: Yes

The fully-qualified class name of your User model. PBAC uses this to:
- Register the User target type automatically
- Enable polymorphic relationships with groups and teams
- Provide the `can()` method via traits

**When to change**: If your user model is in a different namespace (e.g., `\Domain\Users\Models\User::class`).

---

## Super Admin Attribute

```php
'super_admin_attribute' => 'is_super_admin',
```

**Type**: `string|null`  
**Default**: `'is_super_admin'`  
**Required**: No

The name of a boolean attribute/column on your User model that, when `true`, grants the user complete bypass access to all permissions via Laravel's `Gate::before()` hook.

### How It Works

```php
// In your User model
class User extends Authenticatable
{
    protected $casts = [
        'is_super_admin' => 'boolean',
    ];
}

// Create super admin
$admin = User::create([
    'name' => 'Admin',
    'is_super_admin' => true
]);

// Super admin bypasses ALL permission checks
$admin->can('view', $anyPost);    // true
$admin->can('delete', $anyPost);  // true
$admin->can('anything', $anyPost); // true
```

### Options

| Value | Behavior |
|-------|----------|
| `'is_super_admin'` | Checks `$user->is_super_admin` attribute |
| `'is_admin'` | Checks `$user->is_admin` attribute |
| `null` | Disables super admin bypass completely |

**Security Note**: Only set this attribute to `true` for trusted administrators. Super admins bypass ALL permission checks including explicit deny rules.

---

## Strict Registration

### Strict Resource Registration

```php
'strict_resource_registration' => false,
```

**Type**: `boolean`  
**Default**: `false`  
**Environment Variable**: Not available

Controls how PBAC handles resource types that aren't registered in the `pbac_access_resources` table.

| Value | Behavior |
|-------|----------|
| `true` | **Strict Mode**: Access denied immediately if resource type not registered or inactive |
| `false` | **Permissive Mode**: Evaluation proceeds with wildcard rules only |

#### Strict Mode (true)

```php
// Resource NOT registered in pbac_access_resources table
$user->can('view', $unknownPost); // Returns FALSE immediately

// Resource exists but is_active = false
$user->can('edit', $inactiveResource); // Returns FALSE immediately
```

**Use when**: You want tight control over which resource types can be used in your application. Good for security-critical systems.

#### Permissive Mode (false)

```php
// Resource NOT registered - continues with wildcard rules
PBACAccessControl::factory()
    ->allow()
    ->forUser($user)
    ->forResource(null, null) // Wildcard resource
    ->withAction('view')
    ->create();

$user->can('view', $unknownPost); // Returns TRUE (matches wildcard rule)
```

**Use when**: You want flexibility to work with resources before formally registering them.

### Strict Target Registration

```php
'strict_target_registration' => false,
```

**Type**: `boolean`  
**Default**: `false`  
**Environment Variable**: Not available

Controls how PBAC handles target types (users, groups, teams) that aren't registered in the `pbac_access_targets` table.

| Value | Behavior |
|-------|----------|
| `true` | **Strict Mode**: Access denied if target type not registered or inactive |
| `false` | **Permissive Mode**: Evaluation proceeds normally |

**Recommendation**: Leave as `false` unless you have specific security requirements. PBAC automatically registers User, Group, and Team types during migration.

---

## Traits

```php
'traits' => [
    'groups' => \Modules\Pbac\Traits\HasPbacGroups::class,
    'teams' => \Modules\Pbac\Traits\HasPbacTeams::class,
    'access_control' => \Modules\Pbac\Traits\HasPbacAccessControl::class
],
```

**Type**: `array`  
**Default**: As shown above  
**Required**: Yes

Defines which traits provide PBAC functionality. These are used by the User model to enable permissions.

### Trait Descriptions

| Trait | Purpose | Methods Added |
|-------|---------|---------------|
| `HasPbacAccessControl` | Core permission checking | `can()`, `cannot()`, `hasAccess()` |
| `HasPbacGroups` | Group membership | `groups()`, relationship methods |
| `HasPbacTeams` | Team membership | `teams()`, relationship methods |

### Usage

```php
// In your User model
use Modules\Pbac\Traits\HasPbacAccessControl;
use Modules\Pbac\Traits\HasPbacGroups;
use Modules\Pbac\Traits\HasPbacTeams;

class User extends Authenticatable
{
    use HasPbacAccessControl;
    use HasPbacGroups;
    use HasPbacTeams;
}
```

**When to change**: If you've extended the traits with custom functionality, update the class paths here.

---

## Condition Handlers

```php
'condition_handlers' => [
    'min_level' => \Modules\Pbac\Support\ConditionerHandlers\MinLevelHandler::class,
    'allowed_ips' => \Modules\Pbac\Support\ConditionerHandlers\AllowedIpsHandler::class,
    'requires_attribute_value' => \Modules\Pbac\Support\ConditionerHandlers\RequiresAttributeValueHandler::class,
],
```

**Type**: `array`  
**Default**: Three built-in handlers  
**Required**: No (empty array is valid)

Defines custom condition evaluators for attribute-based access control. Each handler processes specific conditions in the `extras` field of access rules.

### Built-in Handlers

#### 1. MinLevelHandler

Requires user to have a minimum level/rank.

```php
PBACAccessControl::factory()
    ->allow()
    ->forUser($user)
    ->forResource(Post::class, $post->id)
    ->withAction('edit')
    ->create([
        'extras' => ['min_level' => 5]
    ]);

// Check with context
$user->can('edit', $post, ['level' => 10]); // true (10 >= 5)
$user->can('edit', $post, ['level' => 3]);  // false (3 < 5)
```

#### 2. AllowedIpsHandler

Restricts access to specific IP addresses.

```php
PBACAccessControl::factory()
    ->allow()
    ->forUser($user)
    ->forResource(AdminPanel::class, null)
    ->withAction('access')
    ->create([
        'extras' => [
            'allowed_ips' => ['192.168.1.1', '10.0.0.0/8']
        ]
    ]);

// Check with context
$user->can('access', AdminPanel::class, ['ip' => '192.168.1.1']); // true
$user->can('access', AdminPanel::class, ['ip' => '8.8.8.8']);      // false
```

#### 3. RequiresAttributeValueHandler

Requires resource attributes to match specific values (e.g., ownership checks).

```php
PBACAccessControl::factory()
    ->allow()
    ->forUser($user)
    ->forResource(Post::class, null)
    ->withAction('edit')
    ->create([
        'extras' => [
            'requires_attribute_value' => [
                'user_id' => $user->id // Only posts where user_id matches
            ]
        ]
    ]);

// Check ownership
$myPost = Post::create(['user_id' => $user->id]);
$otherPost = Post::create(['user_id' => $otherUser->id]);

$user->can('edit', $myPost);    // true (user_id matches)
$user->can('edit', $otherPost); // false (user_id doesn't match)
```

### Creating Custom Handlers

```php
namespace App\Pbac\Conditions;

use Modules\Pbac\Contracts\ConditionHandlerInterface;

class BusinessHoursHandler implements ConditionHandlerInterface
{
    public function handle(array $ruleExtras, mixed $resource = null, array $context = []): bool
    {
        $now = now();
        $startHour = $ruleExtras['start_hour'] ?? 9;
        $endHour = $ruleExtras['end_hour'] ?? 17;
        
        return $now->hour >= $startHour && $now->hour < $endHour;
    }
}

// Register in config/pbac.php
'condition_handlers' => [
    'is_business_hours' => \App\Pbac\Conditions\BusinessHoursHandler::class,
    // ... other handlers
],

// Use in rules
PBACAccessControl::factory()
    ->allow()
    ->forUser($user)
    ->forResource(Document::class, null)
    ->withAction('download')
    ->create([
        'extras' => [
            'is_business_hours' => [
                'start_hour' => 9,
                'end_hour' => 17
            ]
        ]
    ]);
```

---

## Models

```php
'models' => [
    'access_control' => \Modules\Pbac\Models\PBACAccessControl::class,
    'access_resource' => \Modules\Pbac\Models\PBACAccessResource::class,
    'access_target' => \Modules\Pbac\Models\PBACAccessTarget::class,
    'access_group' => \Modules\Pbac\Models\PBACAccessGroup::class,
    'access_team' => \Modules\Pbac\Models\PBACAccessTeam::class,
],
```

**Type**: `array`  
**Default**: As shown above  
**Required**: Yes

Defines which Eloquent models represent PBAC entities. This allows you to extend or replace default models.

### When to Extend Models

```php
namespace App\Models;

use Modules\Pbac\Models\PBACAccessControl as BasePBACAccessControl;

class PBACAccessControl extends BasePBACAccessControl
{
    // Add custom scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
    
    // Add custom relationships
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

// Update config/pbac.php
'models' => [
    'access_control' => \App\Models\PBACAccessControl::class,
    // ... rest unchanged
],
```

---

## Supported Actions

```php
'supported_actions' => [
    'view',
    'viewAny',
    'create',
    'update',
    'delete',
    'restore',
    'forceDelete',
    'publish',
    'archive',
],
```

**Type**: `array`  
**Default**: Standard Laravel policy actions  
**Required**: No (empty array is valid)

Defines the standard actions used in your application. This is **documentation only** and doesn't restrict which actions can be used.

### Standard Laravel Actions

| Action | Typical Use |
|--------|-------------|
| `view` | View a single resource |
| `viewAny` | View list of resources |
| `create` | Create new resource |
| `update` | Edit existing resource |
| `delete` | Soft delete resource |
| `restore` | Restore soft-deleted resource |
| `forceDelete` | Permanently delete resource |

### Custom Actions

You can use ANY action name - not limited to this list:

```php
// Custom actions
$user->can('publish', $post);
$user->can('archive', $document);
$user->can('approve', $request);
$user->can('reject', $application);
$user->can('export', $report);
$user->can('share', $file);

// Add to config for documentation
'supported_actions' => [
    'view', 'create', 'update', 'delete',
    'publish', 'archive', 'approve', 'reject',
    'export', 'share',
],
```

**Note**: Action matching is **case-sensitive**. `'view'` and `'View'` are different actions.

---

## Caching

```php
'cache' => [
    'enabled' => env('PBAC_CACHE_ENABLED', true),
    'ttl' => env('PBAC_CACHE_TTL', 60 * 24),
    'key_prefix' => 'pbac:',
],
```

**Type**: `array`  
**Default**: Enabled with 24-hour TTL

Configures caching for PBAC permission checks to improve performance.

### Options

#### `enabled`

**Type**: `boolean`  
**Default**: `true`  
**Environment Variable**: `PBAC_CACHE_ENABLED`

Enable or disable permission caching globally.

```bash
# .env
PBAC_CACHE_ENABLED=true   # Enable caching (production)
PBAC_CACHE_ENABLED=false  # Disable caching (development)
```

#### `ttl`

**Type**: `integer` (seconds)  
**Default**: `1440` (24 hours)  
**Environment Variable**: `PBAC_CACHE_TTL`

How long to cache permission results.

```bash
# .env
PBAC_CACHE_TTL=3600     # 1 hour
PBAC_CACHE_TTL=86400    # 24 hours (default)
PBAC_CACHE_TTL=604800   # 1 week
```

**Recommendation**: 
- **Development**: Disable caching or use short TTL (60 seconds)
- **Production**: Use 1-24 hours based on how frequently permissions change

#### `key_prefix`

**Type**: `string`  
**Default**: `'pbac:'`

Prefix for all cache keys to avoid collisions with other cached data.

```php
// Generated cache keys look like:
// pbac:user:123:view:Post:456
// pbac:user:456:create:Document
```

### Cache Invalidation

Cache is automatically cleared when:
- Access rules are created, updated, or deleted
- Users are added/removed from groups or teams

Manual cache clearing:

```php
// Clear all PBAC cache
Cache::tags(['pbac'])->flush();

// Clear specific user's cache
Cache::forget("pbac:user:{$userId}:view:Post:123");
```

---

## Logging

```php
'logging' => [
    'enabled' => env('PBAC_LOGGING_ENABLED', true),
    'channel' => env('PBAC_LOGGING_CHANNEL', 'stderr'),
    'level' => env('LOG_LEVEL', 'warning'),
],
```

**Type**: `array`  
**Default**: Enabled with stderr channel at warning level

Configures logging for PBAC operations and permission checks.

### Options

#### `enabled`

**Type**: `boolean`  
**Default**: `true`  
**Environment Variable**: `PBAC_LOGGING_ENABLED`

Enable or disable PBAC logging globally.

```bash
# .env
PBAC_LOGGING_ENABLED=true   # Log permission checks (recommended)
PBAC_LOGGING_ENABLED=false  # Disable logging
```

#### `channel`

**Type**: `string|null`  
**Default**: `'stderr'`  
**Environment Variable**: `PBAC_LOGGING_CHANNEL`

Which Laravel logging channel to use. Set to `null` to use the default application channel.

```bash
# .env
PBAC_LOGGING_CHANNEL=stderr      # Use stderr channel
PBAC_LOGGING_CHANNEL=daily       # Use daily file logs
PBAC_LOGGING_CHANNEL=stack       # Use stack channel
PBAC_LOGGING_CHANNEL=            # Use default app channel
```

#### `level`

**Type**: `string`  
**Default**: `'warning'`  
**Environment Variable**: `LOG_LEVEL`

Minimum log level for PBAC messages.

```bash
# .env
LOG_LEVEL=debug     # Log everything (development)
LOG_LEVEL=info      # Log permission grants/denies
LOG_LEVEL=warning   # Log issues only (default)
LOG_LEVEL=error     # Log errors only (production)
```

**Available levels**: `debug`, `info`, `notice`, `warning`, `error`, `critical`, `alert`, `emergency`

### What Gets Logged

```php
// Permission denied
[2024-10-19 14:30:15] local.WARNING: PBAC: Access denied for user 123 on action 'delete' for resource Post:456

// Permission granted (debug level)
[2024-10-19 14:30:20] local.DEBUG: PBAC: Access granted for user 123 on action 'view' for resource Post:456 via rule #789

// Missing resource registration (warning)
[2024-10-19 14:30:25] local.WARNING: PBAC: Resource type App\Models\UnknownModel not registered

// Condition evaluation failure (info)
[2024-10-19 14:30:30] local.INFO: PBAC: Condition 'allowed_ips' failed for rule #789 (IP: 8.8.8.8 not in whitelist)
```

---

## Environment Variables

### Complete .env Configuration

```bash
# PBAC Caching
PBAC_CACHE_ENABLED=true
PBAC_CACHE_TTL=86400

# PBAC Logging
PBAC_LOGGING_ENABLED=true
PBAC_LOGGING_CHANNEL=stderr
LOG_LEVEL=warning
```

### Development vs Production

#### Development Settings

```bash
# Development - Disable caching, enable detailed logging
PBAC_CACHE_ENABLED=false
PBAC_LOGGING_ENABLED=true
LOG_LEVEL=debug
```

#### Production Settings

```bash
# Production - Enable caching, minimal logging
PBAC_CACHE_ENABLED=true
PBAC_CACHE_TTL=86400
PBAC_LOGGING_ENABLED=true
LOG_LEVEL=warning
```

---

## Best Practices

### 1. Super Admin Attribute

✅ **DO**: Use for trusted administrators only  
✅ **DO**: Add this column to your users migration  
❌ **DON'T**: Set this via user input  
❌ **DON'T**: Use for regular role-based permissions  

### 2. Strict Registration

✅ **DO**: Use `strict_resource_registration => true` for security-critical apps  
✅ **DO**: Register all resource types explicitly if using strict mode  
❌ **DON'T**: Use strict mode if you need flexibility for dynamic resources  

### 3. Caching

✅ **DO**: Enable caching in production for performance  
✅ **DO**: Use shorter TTL if permissions change frequently  
✅ **DO**: Disable caching in development for immediate feedback  
❌ **DON'T**: Use very long TTL (> 7 days) unless permissions are static  

### 4. Logging

✅ **DO**: Keep logging enabled in production at warning+ level  
✅ **DO**: Use debug level in development  
✅ **DO**: Monitor logs for unauthorized access attempts  
❌ **DON'T**: Use debug logging in production (performance impact)  

### 5. Custom Condition Handlers

✅ **DO**: Implement `ConditionHandlerInterface` for custom handlers  
✅ **DO**: Keep handler logic simple and fast  
✅ **DO**: Return boolean from `handle()` method  
❌ **DON'T**: Make database queries in condition handlers (use caching)  

---

## Next Steps

- [Basic Usage](basic-usage.md) - Start using PBAC
- [Core Concepts](core-concepts.md) - Understand PBAC fundamentals
- [Use Cases](use-cases.md) - See real-world examples
- [API Reference](api-reference.md) - Complete API documentation
