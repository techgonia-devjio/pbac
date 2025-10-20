# PBAC Facade Usage Guide

The PBAC Facade provides a convenient, expressive API for working with Policy-Based Access Control in your Laravel application.

## Table of Contents

1. [Setup](#setup)
2. [Permission Checks](#permission-checks)
3. [Creating Rules](#creating-rules)
4. [Managing Groups](#managing-groups)
5. [Managing Teams](#managing-teams)
6. [Utility Methods](#utility-methods)
7. [Advanced Usage](#advanced-usage)

## Setup

Import the facade in your code:

```php
use Pbac\Facades\Pbac;
```

## Permission Checks

### Check if user can perform an action

```php
use Pbac\Facades\Pbac;

$user = User::find(1);
$post = Post::find(1);

// Check permission
if (Pbac::can($user, 'edit', $post)) {
    // User can edit this post
}

// Check negative permission
if (Pbac::cannot($user, 'delete', $post)) {
    // User cannot delete this post
}

// Check on class (for actions like 'create')
if (Pbac::can($user, 'create', Post::class)) {
    // User can create posts
}

// Check with context
if (Pbac::can($user, 'access', AdminPanel::class, ['ip' => request()->ip()])) {
    // User can access admin panel from this IP
}
```

### Use in Controllers

```php
use Pbac\Facades\Pbac;

class PostController extends Controller
{
    public function edit(Post $post)
    {
        $user = auth()->user();

        if (Pbac::cannot($user, 'update', $post)) {
            abort(403, 'You cannot edit this post');
        }

        // Allow edit
    }
}
```

### Use in Policies

```php
use Pbac\Facades\Pbac;

class PostPolicy
{
    public function update(User $user, Post $post): bool
    {
        // Published posts cannot be edited
        if ($post->status === 'published') {
            return false;
        }

        // Otherwise, delegate to PBAC
        return Pbac::can($user, 'update', $post);
    }
}
```

### Use in Views

```php
@if(Pbac::can(auth()->user(), 'edit', $post))
    <button>Edit Post</button>
@endif

@if(Pbac::cannot(auth()->user(), 'delete', $post))
    <p>You cannot delete this post</p>
@endif
```

## Creating Rules

### Create Allow Rule

```php
use Pbac\Facades\Pbac;

// Basic allow rule
Pbac::allow()
    ->forUser($user)
    ->forResource(Post::class, $post->id)
    ->withAction('edit')
    ->create();

// Multiple actions
Pbac::allow()
    ->forUser($user)
    ->forResource(Post::class, null) // All posts
    ->withAction(['view', 'edit', 'delete'])
    ->withPriority(10)
    ->create();

// With conditions
Pbac::allow()
    ->forGroup($editors)
    ->forResource(Post::class, null)
    ->withAction('edit')
    ->create([
        'extras' => [
            'requires_attribute_value' => ['status' => 'draft']
        ]
    ]);

// With ownership condition
Pbac::allow()
    ->forGroup($authors)
    ->forResource(Post::class, null)
    ->withAction(['update', 'delete'])
    ->create([
        'extras' => [
            'requires_ownership' => true
        ]
    ]);
```

### Create Deny Rule

```php
use Pbac\Facades\Pbac;

// Deny access to specific resource
Pbac::deny()
    ->forUser($user)
    ->forResource(Post::class, $classifiedPost->id)
    ->withAction('*') // All actions
    ->create();

// Deny with IP restriction
Pbac::deny()
    ->forGroup($users)
    ->forResource(AdminPanel::class, null)
    ->withAction('access')
    ->create([
        'extras' => [
            'allowed_ips' => ['192.168.1.0/24'] // Only allow from these IPs
        ]
    ]);
```

## Managing Groups

### Create Group

```php
use Pbac\Facades\Pbac;

$editors = Pbac::createGroup('Editors', 'Users who can edit content');
$admins = Pbac::createGroup('Administrators');
```

### Assign User to Group

```php
$user = User::find(1);
$editors = PBACAccessGroup::where('name', 'Editors')->first();

// Assign user to group
Pbac::assignToGroup($user, $editors);

// Or by ID
Pbac::assignToGroup($user, $editors->id);
```

### Remove User from Group

```php
Pbac::removeFromGroup($user, $editors);
```

### Check Group Membership

```php
// By name
if (Pbac::hasGroup($user, 'Editors')) {
    // User is an editor
}

// By ID
if (Pbac::hasGroup($user, 5)) {
    // User is in group 5
}
```

### Get User's Groups

```php
$groups = Pbac::getUserGroups($user);

foreach ($groups as $group) {
    echo $group['name'];
}
```

### Get All Groups

```php
$allGroups = Pbac::getAllGroups();
```

## Managing Teams

### Create Team

```php
use Pbac\Facades\Pbac;

$devTeam = Pbac::createTeam(
    'Development Team',
    'Software developers',
    $owner->id // Optional owner
);
```

### Assign User to Team

```php
Pbac::assignToTeam($user, $devTeam);
```

### Remove User from Team

```php
Pbac::removeFromTeam($user, $devTeam);
```

### Check Team Membership

```php
// By name
if (Pbac::hasTeam($user, 'Development Team')) {
    // User is in dev team
}

// By ID
if (Pbac::hasTeam($user, 3)) {
    // User is in team 3
}
```

### Get User's Teams

```php
$teams = Pbac::getUserTeams($user);

foreach ($teams as $team) {
    echo $team['name'];
}
```

### Get All Teams

```php
$allTeams = Pbac::getAllTeams();
```

## Utility Methods

### Get Rules for User

```php
// Get all rules for user
$rules = Pbac::getRulesFor($user);

// Filter by action
$viewRules = Pbac::getRulesFor($user, 'view');

// Filter by resource
$postRules = Pbac::getRulesFor($user, null, Post::class);

// Filter by both
$viewPostRules = Pbac::getRulesFor($user, 'view', Post::class);
```

### Get Permissions for User

```php
// Get all actions user can perform globally
$permissions = Pbac::getPermissionsFor($user);
// Example: ['view', 'edit', 'create', 'delete']

// Get actions user can perform on specific resource
$postPermissions = Pbac::getPermissionsFor($user, Post::class);
// Example: ['view', 'edit']
```

### Check Super Admin

```php
if (Pbac::isSuperAdmin($user)) {
    // User bypasses all permission checks
}
```

### Clear Cache

```php
// Clear cache for specific user
Pbac::clearCache($user);

// Clear all PBAC cache
Pbac::clearCache();
```

### Get All Rules

```php
$allRules = Pbac::getAllRules();

foreach ($allRules as $rule) {
    echo $rule->effect . ': ' . implode(', ', $rule->action);
}
```

## Advanced Usage

### Building a Permission Matrix

```php
use Pbac\Facades\Pbac;

$users = User::all();
$resources = [Post::class, Comment::class, Category::class];
$actions = ['view', 'create', 'update', 'delete'];

$matrix = [];

foreach ($users as $user) {
    $matrix[$user->id] = [
        'name' => $user->name,
        'groups' => Pbac::getUserGroups($user),
        'permissions' => []
    ];

    foreach ($resources as $resource) {
        foreach ($actions as $action) {
            $can = Pbac::can($user, $action, $resource);
            $matrix[$user->id]['permissions'][$resource][$action] = $can;
        }
    }
}

return $matrix;
```

### Bulk Rule Creation

```php
use Pbac\Facades\Pbac;

$editors = PBACAccessGroup::where('name', 'Editors')->first();
$resources = [Post::class, Comment::class, Category::class];
$actions = ['create', 'view', 'update', 'delete'];

foreach ($resources as $resource) {
    Pbac::allow()
        ->forGroup($editors)
        ->forResource($resource, null)
        ->withAction($actions)
        ->withPriority(80)
        ->create();
}
```

### Dynamic Rule Creation Based on User Level

```php
use Pbac\Facades\Pbac;

function grantAccessByLevel(User $user, int $level)
{
    switch ($level) {
        case 1: // Basic
            Pbac::allow()
                ->forUser($user)
                ->forResource(Post::class, null)
                ->withAction('view')
                ->create();
            break;

        case 2: // Intermediate
            Pbac::allow()
                ->forUser($user)
                ->forResource(Post::class, null)
                ->withAction(['view', 'create'])
                ->create();
            break;

        case 3: // Advanced
            Pbac::allow()
                ->forUser($user)
                ->forResource(Post::class, null)
                ->withAction(['view', 'create', 'update', 'delete'])
                ->create();
            break;
    }
}
```

### Conditional Permissions

```php
use Pbac\Facades\Pbac;

// Only allow editing during business hours
Pbac::allow()
    ->forGroup($editors)
    ->forResource(Post::class, null)
    ->withAction('edit')
    ->create([
        'extras' => [
            'is_business_hours' => true // Requires custom condition handler
        ]
    ]);

// Only allow from specific IP ranges
Pbac::allow()
    ->forUser($admin)
    ->forResource(AdminPanel::class, null)
    ->withAction('access')
    ->create([
        'extras' => [
            'allowed_ips' => ['192.168.1.0/24', '10.0.0.0/8']
        ]
    ]);

// Require minimum level
Pbac::allow()
    ->forUser($user)
    ->forResource(SensitiveData::class, null)
    ->withAction('view')
    ->create([
        'extras' => [
            'min_level' => 5
        ]
    ]);
```

### Audit User Permissions

```php
use Pbac\Facades\Pbac;

function auditUserPermissions(User $user): array
{
    return [
        'user' => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ],
        'is_super_admin' => Pbac::isSuperAdmin($user),
        'groups' => Pbac::getUserGroups($user),
        'teams' => Pbac::getUserTeams($user),
        'rules' => Pbac::getRulesFor($user),
        'permissions' => [
            'posts' => Pbac::getPermissionsFor($user, Post::class),
            'comments' => Pbac::getPermissionsFor($user, Comment::class),
        ],
    ];
}
```

### Permission Migration

```php
use Pbac\Facades\Pbac;

// Migrate from old permission system to PBAC
function migratePermissions()
{
    $oldPermissions = DB::table('old_permissions')->get();

    foreach ($oldPermissions as $perm) {
        $user = User::find($perm->user_id);

        Pbac::allow()
            ->forUser($user)
            ->forResource($perm->resource_type, $perm->resource_id)
            ->withAction($perm->action)
            ->create();
    }
}
```

## Best Practices

### ✅ DO

- Use `Pbac::can()` in policies for complex business logic
- Use group-based rules instead of user-specific rules
- Clear cache after bulk rule changes: `Pbac::clearCache()`
- Use `Pbac::getRulesFor()` for debugging permission issues
- Use `Pbac::getPermissionsFor()` for UI (showing/hiding buttons)

### ❌ DON'T

- Don't create individual rules for thousands of users (use groups)
- Don't check permissions in loops (pre-fetch permissions)
- Don't forget to register custom condition handlers
- Don't bypass PBAC with direct database queries

## Examples from Demo App

Check the demo application for real-world examples:

- **PostPolicy**: Shows how to use Pbac facade in policies
- **BlogDemoSeeder**: Shows how to create rules for groups
- **OwnershipHandler**: Shows how to create custom condition handlers
- **PBAC_IMPLEMENTATION.md**: Complete guide to the demo app

## See Also

- [Core Concepts](core-concepts.md)
- [Basic Usage](basic-usage.md)
- [Use Cases](use-cases.md)
- [API Reference](api-reference.md)
