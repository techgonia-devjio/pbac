# Basic Usage Guide

## Table of Contents

1. [Creating Permissions](#creating-permissions)
2. [Checking Permissions](#checking-permissions)
3. [Working with Groups](#working-with-groups)
4. [Working with Teams](#working-with-teams)
5. [Understanding Effects](#understanding-effects)
6. [Common Patterns](#common-patterns)

## Creating Permissions

### Grant Permission to a User

```php
use Pbac\Models\PBACAccessControl;
use App\Models\Post;

// Allow a user to view a specific post
PBACAccessControl::factory()
    ->allow()
    ->forUser($user)
    ->forResource(Post::class, $post->id)
    ->withAction('view')
    ->create();
```

### Grant Multiple Permissions

```php
// Allow multiple actions on the same resource
PBACAccessControl::factory()
    ->allow()
    ->forUser($user)
    ->forResource(Post::class, $post->id)
    ->withAction(['view', 'edit', 'delete'])
    ->create();
```

### Grant Permission for All Resources of a Type

```php
// Allow user to view ALL posts (resource_id = null)
PBACAccessControl::factory()
    ->allow()
    ->forUser($user)
    ->forResource(Post::class, null) // null = any post
    ->withAction('view')
    ->create();
```

### Create Permissions (Using Class Name)

```php
// For "create" actions, use class name since resource doesn't exist yet
PBACAccessControl::factory()
    ->allow()
    ->forUser($user)
    ->forResource(Post::class, null)
    ->withAction('create')
    ->create();

// Check permission
if ($user->can('create', Post::class)) {
    // User can create posts
}
```

## Checking Permissions

### Basic Permission Check

```php
if ($user->can('view', $post)) {
    // User can view this post
}

if ($user->cannot('edit', $post)) {
    // User cannot edit this post
}
```

### Laravel Gate Integration

```php
use Illuminate\Support\Facades\Gate;

// Using Gate facade
if (Gate::allows('edit', $post)) {
    // Allowed
}

if (Gate::denies('delete', $post)) {
    // Denied
}

// Throw exception if denied
Gate::authorize('publish', $post);
```

### In Blade Templates

```blade
@can('edit', $post)
    <button>Edit Post</button>
@endcan

@cannot('delete', $post)
    <p>You cannot delete this post</p>
@endcannot

{{-- Using PBAC directive --}}
@pbacCan('publish', $post)
    <button>Publish</button>
@endpbacCan
```

### Check Multiple Permissions

```php
// Check if user has ANY of the permissions
if (Gate::any(['edit', 'delete'], $post)) {
    // User has either edit OR delete permission
}

// Check if user has NONE of the permissions
if (Gate::none(['edit', 'delete'], $post)) {
    // User has neither edit NOR delete permission
}
```

## Working with Groups

### Create a Group

```php
use Pbac\Models\PBACAccessGroup;

$editors = PBACAccessGroup::create([
    'name' => 'Editors',
    'description' => 'Users who can edit content'
]);
```

### Add Users to Group

```php
// Add single user
$user->groups()->attach($editors->id);

// Add multiple users
$editors->users()->attach([$user1->id, $user2->id, $user3->id]);
```

### Grant Permission to Group

```php
// All group members will have this permission
PBACAccessControl::factory()
    ->allow()
    ->forGroup($editors)
    ->forResource(Post::class, null)
    ->withAction(['view', 'edit'])
    ->create();
```

### Check User's Groups

```php
// Get all groups for a user
$groups = $user->groups;

// Check if user belongs to a group
if ($user->groups->contains($editors)) {
    // User is an editor
}

// Count groups
$groupCount = $user->groups()->count();
```

### Remove User from Group

```php
// Remove single user
$user->groups()->detach($editors->id);

// Remove from all groups
$user->groups()->detach();
```

## Working with Teams

### Create a Team

```php
use Pbac\Models\PBACAccessTeam;

$devTeam = PBACAccessTeam::create([
    'name' => 'Development Team',
    'description' => 'Software developers',
    'owner_id' => $user->id // Optional
]);
```

### Add Users to Team

```php
// Add users to team
$user->teams()->attach($devTeam->id);

// Or from team side
$devTeam->users()->attach([$user1->id, $user2->id]);
```

### Grant Permission to Team

```php
// All team members will have this permission
PBACAccessControl::factory()
    ->allow()
    ->forTeam($devTeam)
    ->forResource(Project::class, null)
    ->withAction('*') // All actions
    ->create();
```

### Team Isolation

Teams are perfect for multi-tenant applications:

```php
// Team A can only see Team A's data
PBACAccessControl::factory()
    ->allow()
    ->forTeam($teamA)
    ->forResource(Document::class, null)
    ->withAction('view')
    ->create([
        'extras' => [
            'requires_attribute_value' => [
                'team_id' => $teamA->id
            ]
        ]
    ]);
```

## Understanding Effects

### Allow vs Deny

```php
// Allow effect - grants permission
PBACAccessControl::factory()
    ->allow() // effect = 'allow'
    ->forUser($user)
    ->forResource(Post::class, $post->id)
    ->withAction('view')
    ->create();

// Deny effect - blocks permission
PBACAccessControl::factory()
    ->deny() // effect = 'deny'
    ->forUser($user)
    ->forResource(Post::class, $secretPost->id)
    ->withAction('view')
    ->create();
```

### Deny-First Rule

**IMPORTANT**: Deny rules ALWAYS override allow rules, regardless of priority!

```php
// Even with very high priority...
PBACAccessControl::factory()
    ->allow()
    ->forUser($user)
    ->forResource(Post::class, $post->id)
    ->withAction('view')
    ->withPriority(1000) // Very high priority
    ->create();

// ...a deny with low priority still wins
PBACAccessControl::factory()
    ->deny()
    ->forUser($user)
    ->forResource(Post::class, $post->id)
    ->withAction('view')
    ->withPriority(1) // Low priority
    ->create();

// Result: Access DENIED (secure by default)
$user->can('view', $post); // false
```

## Common Patterns

### Pattern 1: Default Permissions for All Users

```php
// Create a "Users" group
$users = PBACAccessGroup::create(['name' => 'All Users']);

// Add all new users to this group automatically
// In your User model or registration logic:
public static function boot()
{
    parent::boot();

    static::created(function ($user) {
        $usersGroup = PBACAccessGroup::where('name', 'All Users')->first();
        $user->groups()->attach($usersGroup->id);
    });
}

// Grant default permissions to the group
PBACAccessControl::factory()
    ->allow()
    ->forGroup($users)
    ->forResource(Post::class, null)
    ->withAction('view')
    ->create();
```

### Pattern 2: Owner Permissions

```php
// Allow users to edit their own posts
PBACAccessControl::factory()
    ->allow()
    ->forUser($user)
    ->forResource(Post::class, null)
    ->withAction(['edit', 'delete'])
    ->create([
        'extras' => [
            'requires_attribute_value' => [
                'user_id' => $user->id // Only if post.user_id matches
            ]
        ]
    ]);
```

### Pattern 3: Hierarchical Permissions

```php
// Admins get all permissions
PBACAccessControl::factory()
    ->allow()
    ->forGroup($admins)
    ->forResource(null, null) // Any resource
    ->withAction('*') // Any action
    ->create();

// Moderators get limited permissions
PBACAccessControl::factory()
    ->allow()
    ->forGroup($moderators)
    ->forResource(Post::class, null)
    ->withAction(['view', 'edit'])
    ->create();

// Users get basic permissions
PBACAccessControl::factory()
    ->allow()
    ->forGroup($users)
    ->forResource(Post::class, null)
    ->withAction('view')
    ->create();
```

### Pattern 4: Temporary Permissions

```php
// Grant access that expires
$expiresAt = now()->addDays(7);

PBACAccessControl::factory()
    ->allow()
    ->forUser($user)
    ->forResource(Document::class, $doc->id)
    ->withAction('view')
    ->create([
        'extras' => [
            'expires_at' => $expiresAt->toDateTimeString()
        ]
    ]);

// You would need to implement expiration check in PolicyEvaluator
// or clean up expired rules with a scheduled job
```

### Pattern 5: Read vs Write Separation

```php
// Readers can view
PBACAccessControl::factory()
    ->allow()
    ->forGroup($readers)
    ->forResource(Post::class, null)
    ->withAction(['view', 'list'])
    ->create();

// Writers can view and modify
PBACAccessControl::factory()
    ->allow()
    ->forGroup($writers)
    ->forResource(Post::class, null)
    ->withAction(['view', 'list', 'create', 'edit', 'delete'])
    ->create();
```

## Quick Reference

### Factory Methods

| Method | Purpose |
|--------|---------|
| `allow()` | Set effect to 'allow' |
| `deny()` | Set effect to 'deny' |
| `forUser($user)` | Set target to specific user |
| `forGroup($group)` | Set target to group |
| `forTeam($team)` | Set target to team |
| `forResource($class, $id)` | Set resource type and ID |
| `withAction($action)` | Set action(s) - string or array |
| `withPriority($int)` | Set priority (higher = evaluated first) |
| `create($attributes)` | Create the rule |

### Common Actions

- `view` - Read a resource
- `create` - Create new resource
- `edit` / `update` - Modify existing resource
- `delete` - Remove a resource
- `publish` - Publish/activate a resource
- `*` - Wildcard for all actions

## Next Steps

- [Advanced Usage](advanced-usage.md) - Conditions, priorities, and complex scenarios
- [Use Cases](use-cases.md) - Common application patterns
