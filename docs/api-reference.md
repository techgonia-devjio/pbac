# API Reference

Complete API documentation for Laravel PBAC (Policy-Based Access Control).

## Table of Contents

1. [Traits](#traits)
   - [HasPbacAccessControl](#haspbacaccesscontrol)
   - [HasPbacGroups](#haspbacgroups)
   - [HasPbacTeams](#haspbacteams)
2. [Models](#models)
   - [PBACAccessControl](#pbacaccesscontrol)
   - [PBACAccessGroup](#pbacaccessgroup)
   - [PBACAccessTeam](#pbacaccessteam)
   - [PBACAccessResource](#pbacaccessresource)
   - [PBACAccessTarget](#pbacaccesstarget)
3. [Factory Methods](#factory-methods)
4. [Services](#services)
5. [Blade Directives](#blade-directives)
6. [Laravel Gate Integration](#laravel-gate-integration)

---

## Traits

### HasPbacAccessControl

**Namespace**: `Pbac\Traits\HasPbacAccessControl`

Provides permission checking functionality to your User model.

#### Methods

##### can()

Determine if the user has the given ability (action) on a resource.

```php
public function can(
    string $ability, 
    array|string|Model|null $arguments = []
): bool
```

**Parameters**:
- `$ability` (string) - The action name (e.g., 'view', 'edit', 'delete')
- `$arguments` (mixed) - The resource and optional context

**Returns**: `bool`

**Usage Examples**:

```php
// Basic usage with model instance
$user->can('view', $post);

// With class name (for create actions)
$user->can('create', Post::class);

// With context array
$user->can('edit', $post, ['level' => 10]);
$user->can('access', AdminPanel::class, ['ip' => request()->ip()]);

// With named parameters
$user->can('view', [
    'resource' => $post,
    'context' => ['level' => 5]
]);

// Array syntax
$user->can('edit', [$post, ['level' => 10]]);
```

**Context Handling**:

The `$arguments` parameter is flexible and accepts:

| Input Type | Interpretation | Example |
|------------|----------------|---------|
| Model instance | Resource only | `$user->can('view', $post)` |
| String | Class name | `$user->can('create', Post::class)` |
| Array with Model at [0] | Resource + context | `$user->can('edit', [$post, ['ip' => '...']])` |
| Array with 'resource' key | Named parameters | `$user->can('view', ['resource' => $post, 'context' => [...]])` |
| Array without model | Context only | `$user->can('action', ['level' => 5])` |

**Super Admin Bypass**:

If the user has the super admin attribute (configured in `config/pbac.php`), this method **always returns true** without checking any rules.

```php
// User with is_super_admin = true
$superAdmin->can('anything', $anyResource); // Always true
```

**Integration**:

This method is automatically integrated with Laravel's authorization system via the `Gate::before()` hook, so it works seamlessly with:
- `Gate::allows('edit', $post)`
- `@can('edit', $post)` in Blade
- `$this->authorize('edit', $post)` in controllers

---

### HasPbacGroups

**Namespace**: `Pbac\Traits\HasPbacGroups`

Provides group membership functionality to your User model.

#### Methods

##### groups()

The PBAC groups that the user belongs to.

```php
public function groups(): BelongsToMany
```

**Returns**: `BelongsToMany` relationship

**Usage Examples**:

```php
// Get all groups
$groups = $user->groups; // Collection of PBACAccessGroup
$groupsQuery = $user->groups(); // Query builder

// Check group membership
if ($user->groups->contains($adminGroup)) {
    // User is in admin group
}

// Count groups
$count = $user->groups()->count();

// Add user to group
$user->groups()->attach($group->id);

// Remove user from group
$user->groups()->detach($group->id);

// Sync groups (replace all)
$user->groups()->sync([$group1->id, $group2->id]);

// Add to multiple groups
$user->groups()->attach([$group1->id, $group2->id, $group3->id]);

// Check if user is in any of these groups
$hasGroup = $user->groups()
    ->whereIn('id', [$group1->id, $group2->id])
    ->exists();

// Get groups with specific name
$editorGroups = $user->groups()
    ->where('name', 'Editors')
    ->get();
```

**Eager Loading**:

```php
// Eager load groups to avoid N+1 queries
$users = User::with('groups')->get();

foreach ($users as $user) {
    foreach ($user->groups as $group) {
        echo $group->name;
    }
}
```

---

### HasPbacTeams

**Namespace**: `Pbac\Traits\HasPbacTeams`

Provides team membership functionality to your User model.

#### Methods

##### teams()

The PBAC teams that the user belongs to.

```php
public function teams(): BelongsToMany
```

**Returns**: `BelongsToMany` relationship

**Usage Examples**:

```php
// Get all teams
$teams = $user->teams; // Collection of PBACAccessTeam
$teamsQuery = $user->teams(); // Query builder

// Check team membership
if ($user->teams->contains($devTeam)) {
    // User is in dev team
}

// Count teams
$count = $user->teams()->count();

// Add user to team
$user->teams()->attach($team->id);

// Remove user from team
$user->teams()->detach($team->id);

// Sync teams (replace all)
$user->teams()->sync([$team1->id, $team2->id]);

// Check if user owns a team
$ownedTeams = $user->teams()
    ->where('owner_id', $user->id)
    ->get();

// Get active teams only
$activeTeams = $user->teams()
    ->where('is_active', true)
    ->get();
```

**Eager Loading**:

```php
// Eager load teams
$users = User::with('teams')->get();

// Load teams with owner
$user = User::with('teams.owner')->find($id);
```

---

## Models

### PBACAccessControl

**Namespace**: `Pbac\Models\PBACAccessControl`  
**Table**: `pbac_accesses`

Represents a single access control rule.

#### Properties

```php
protected $fillable = [
    'pbac_access_target_id',  // FK to pbac_access_targets
    'target_id',              // Specific target instance ID (user_id, group_id, team_id)
    'pbac_access_resource_id', // FK to pbac_access_resources
    'resource_id',            // Specific resource instance ID
    'action',                 // Array of actions ['view', 'edit']
    'effect',                 // 'allow' or 'deny'
    'extras',                 // Array of conditions
    'priority',               // Integer (higher = evaluated first)
];

protected $casts = [
    'action' => 'array',   // JSON array
    'extras' => 'array',   // JSON object
    'target_id' => 'integer',
    'resource_id' => 'integer',
];
```

#### Relationships

##### targetType()

```php
public function targetType(): BelongsTo
```

Returns the target type definition (User, Group, Team class).

```php
$rule = PBACAccessControl::find(1);
$targetType = $rule->targetType; // PBACAccessTarget instance
echo $targetType->type; // "App\Models\User"
```

##### resourceType()

```php
public function resourceType(): BelongsTo
```

Returns the resource type definition (Post, Document, etc.).

```php
$rule = PBACAccessControl::find(1);
$resourceType = $rule->resourceType; // PBACAccessResource instance
echo $resourceType->type; // "App\Models\Post"
```

##### targetInstance()

```php
public function targetInstance(): ?BelongsTo
```

Returns the actual target instance (specific user, group, or team) if `target_id` is set.

```php
$rule = PBACAccessControl::find(1);
$target = $rule->targetInstance(); // User, PBACAccessGroup, or PBACAccessTeam
```

##### resourceInstance()

```php
public function resourceInstance(): ?BelongsTo
```

Returns the actual resource instance if `resource_id` is set.

```php
$rule = PBACAccessControl::find(1);
$resource = $rule->resourceInstance(); // Post, Document, etc.
```

#### Factory Methods

See [Factory Methods](#factory-methods) section below.

---

### PBACAccessGroup

**Namespace**: `Pbac\Models\PBACAccessGroup`  
**Table**: `pbac_access_groups`

Represents a group (collection of users, similar to roles).

#### Properties

```php
protected $fillable = [
    'name',          // Group name (e.g., 'Administrators')
    'description',   // Optional description
    'is_active',     // Boolean, default true
];

protected $casts = [
    'is_active' => 'boolean',
];
```

#### Relationships

##### users()

```php
public function users(): BelongsToMany
```

Users belonging to this group.

```php
$group = PBACAccessGroup::find(1);

// Get all users in group
$users = $group->users;

// Add user to group
$group->users()->attach($user->id);

// Remove user from group
$group->users()->detach($user->id);

// Count users
$userCount = $group->users()->count();
```

#### Methods

##### Create Group

```php
$group = PBACAccessGroup::create([
    'name' => 'Editors',
    'description' => 'Users who can edit content',
    'is_active' => true,
]);
```

##### Factory

```php
use Pbac\Models\PBACAccessGroup;

$group = PBACAccessGroup::factory()->create([
    'name' => 'Moderators',
]);
```

---

### PBACAccessTeam

**Namespace**: `Pbac\Models\PBACAccessTeam`  
**Table**: `pbac_access_teams`

Represents a team (organizational unit for multi-tenancy).

#### Properties

```php
protected $fillable = [
    'name',          // Team name
    'description',   // Optional description
    'owner_id',      // User ID of team owner (optional)
    'is_active',     // Boolean, default true
];

protected $casts = [
    'is_active' => 'boolean',
];
```

#### Relationships

##### users()

```php
public function users(): BelongsToMany
```

Users belonging to this team.

```php
$team = PBACAccessTeam::find(1);

// Get all users in team
$users = $team->users;

// Add user to team
$team->users()->attach($user->id);

// Remove user from team
$team->users()->detach($user->id);
```

##### owner()

```php
public function owner(): BelongsTo
```

The user who owns this team (if `owner_id` is set).

```php
$team = PBACAccessTeam::find(1);
$owner = $team->owner; // User instance
```

#### Methods

##### Create Team

```php
$team = PBACAccessTeam::create([
    'name' => 'Development Team',
    'description' => 'Software developers',
    'owner_id' => $user->id,
    'is_active' => true,
]);
```

##### Factory

```php
use Pbac\Models\PBACAccessTeam;

$team = PBACAccessTeam::factory()->create([
    'name' => 'Sales Team',
    'owner_id' => $manager->id,
]);
```

---

### PBACAccessResource

**Namespace**: `Pbac\Models\PBACAccessResource`  
**Table**: `pbac_access_resources`

Represents a resource type (e.g., Post, Document, File).

#### Properties

```php
protected $fillable = [
    'type',         // Fully-qualified class name (e.g., 'App\Models\Post')
    'is_active',    // Boolean, default true
];

protected $casts = [
    'is_active' => 'boolean',
];
```

#### Usage

Resources are typically auto-created when you use the factory:

```php
PBACAccessControl::factory()
    ->allow()
    ->forUser($user)
    ->forResource(Post::class, $post->id) // Auto-creates PBACAccessResource for Post
    ->withAction('view')
    ->create();
```

Manual creation:

```php
$resource = PBACAccessResource::firstOrCreate([
    'type' => Post::class,
]);
```

---

### PBACAccessTarget

**Namespace**: `Pbac\Models\PBACAccessTarget`  
**Table**: `pbac_access_targets`

Represents a target type (User, Group, Team).

#### Properties

```php
protected $fillable = [
    'type',         // Fully-qualified class name
    'is_active',    // Boolean, default true
];

protected $casts = [
    'is_active' => 'boolean',
];
```

#### Usage

Targets are typically auto-registered during migration and auto-used by the factory:

```php
// Auto-creates/finds PBACAccessTarget for User::class
PBACAccessControl::factory()
    ->allow()
    ->forUser($user)
    ->forResource(Post::class, null)
    ->withAction('view')
    ->create();
```

Manual creation:

```php
$target = PBACAccessTarget::firstOrCreate([
    'type' => User::class,
]);
```

---

## Factory Methods

The `PBACAccessControl` factory provides a fluent API for creating access rules.

### Factory Chain

```php
PBACAccessControl::factory()
    ->allow()                           // or ->deny()
    ->forUser($user)                    // or ->forGroup() or ->forTeam()
    ->forResource(Post::class, $id)     // Resource type and optional instance ID
    ->withAction('view')                // Single action or array
    ->withPriority(10)                  // Optional priority (default 0)
    ->create([                          // Optional attributes
        'extras' => [...],
    ]);
```

### Methods

#### allow()

Set effect to 'allow'.

```php
public function allow(): self
```

**Returns**: Factory instance for method chaining

```php
PBACAccessControl::factory()->allow()->create();
```

#### deny()

Set effect to 'deny'.

```php
public function deny(): self
```

**Returns**: Factory instance for method chaining

```php
PBACAccessControl::factory()->deny()->create();
```

#### forUser()

Set target to a specific user.

```php
public function forUser(User $user): self
```

**Parameters**:
- `$user` (User) - The user instance

**Returns**: Factory instance

```php
PBACAccessControl::factory()
    ->allow()
    ->forUser($user)
    ->forResource(Post::class, null)
    ->withAction('view')
    ->create();
```

#### forGroup()

Set target to a group (all group members get this permission).

```php
public function forGroup(PBACAccessGroup $group): self
```

**Parameters**:
- `$group` (PBACAccessGroup) - The group instance

**Returns**: Factory instance

```php
$editors = PBACAccessGroup::find(1);

PBACAccessControl::factory()
    ->allow()
    ->forGroup($editors)
    ->forResource(Post::class, null)
    ->withAction(['view', 'edit'])
    ->create();
```

#### forTeam()

Set target to a team (all team members get this permission).

```php
public function forTeam(PBACAccessTeam $team): self
```

**Parameters**:
- `$team` (PBACAccessTeam) - The team instance

**Returns**: Factory instance

```php
$devTeam = PBACAccessTeam::find(1);

PBACAccessControl::factory()
    ->allow()
    ->forTeam($devTeam)
    ->forResource(Project::class, null)
    ->withAction('*')
    ->create();
```

#### forTarget()

Set target manually (advanced usage).

```php
public function forTarget(?string $targetType, ?int $targetId = null): self
```

**Parameters**:
- `$targetType` (string|null) - Fully-qualified class name (null = any target)
- `$targetId` (int|null) - Specific instance ID (null = any instance)

**Returns**: Factory instance

```php
// For any user
PBACAccessControl::factory()
    ->allow()
    ->forTarget(User::class, null)
    ->forResource(Post::class, null)
    ->withAction('view')
    ->create();

// For specific user ID
PBACAccessControl::factory()
    ->allow()
    ->forTarget(User::class, $user->id)
    ->forResource(Post::class, $post->id)
    ->withAction('edit')
    ->create();

// For ANY target type (global rule)
PBACAccessControl::factory()
    ->allow()
    ->forTarget(null, null)
    ->forResource(Post::class, null)
    ->withAction('view')
    ->create();
```

#### forResource()

Set resource type and optional instance.

```php
public function forResource(?string $resourceType, ?int $resourceId = null): self
```

**Parameters**:
- `$resourceType` (string|null) - Fully-qualified class name (null = any resource)
- `$resourceId` (int|null) - Specific instance ID (null = any instance)

**Returns**: Factory instance

```php
// For specific post instance
PBACAccessControl::factory()
    ->allow()
    ->forUser($user)
    ->forResource(Post::class, $post->id)
    ->withAction('view')
    ->create();

// For all posts
PBACAccessControl::factory()
    ->allow()
    ->forUser($user)
    ->forResource(Post::class, null)
    ->withAction('view')
    ->create();

// For any resource (global)
PBACAccessControl::factory()
    ->allow()
    ->forUser($superAdmin)
    ->forResource(null, null)
    ->withAction('*')
    ->create();
```

#### withAction()

Set action(s) for this rule.

```php
public function withAction(string|array $action): self
```

**Parameters**:
- `$action` (string|array) - Single action or array of actions

**Returns**: Factory instance

```php
// Single action
PBACAccessControl::factory()
    ->allow()
    ->forUser($user)
    ->forResource(Post::class, null)
    ->withAction('view')
    ->create();

// Multiple actions
PBACAccessControl::factory()
    ->allow()
    ->forUser($user)
    ->forResource(Post::class, null)
    ->withAction(['view', 'edit', 'delete'])
    ->create();

// Wildcard (all actions)
PBACAccessControl::factory()
    ->allow()
    ->forUser($admin)
    ->forResource(Post::class, null)
    ->withAction('*')
    ->create();
```

#### withPriority()

Set rule priority (higher = evaluated first).

```php
public function withPriority(int $priority): self
```

**Parameters**:
- `$priority` (int) - Priority value (default 0, higher = evaluated first)

**Returns**: Factory instance

```php
// High priority rule
PBACAccessControl::factory()
    ->allow()
    ->forUser($user)
    ->forResource(Post::class, null)
    ->withAction('view')
    ->withPriority(100)
    ->create();

// Low priority fallback rule
PBACAccessControl::factory()
    ->allow()
    ->forUser($user)
    ->forResource(Post::class, null)
    ->withAction('view')
    ->withPriority(1)
    ->create();
```

**Important**: Priority does NOT override the deny-first rule. Deny always wins regardless of priority.

#### create()

Create the access rule with optional extra attributes.

```php
public function create(array $attributes = []): PBACAccessControl
```

**Parameters**:
- `$attributes` (array) - Additional attributes (typically `extras` for conditions)

**Returns**: PBACAccessControl instance

```php
// With conditions
PBACAccessControl::factory()
    ->allow()
    ->forUser($user)
    ->forResource(Post::class, $post->id)
    ->withAction('edit')
    ->create([
        'extras' => [
            'min_level' => 5,
            'allowed_ips' => ['192.168.1.1'],
        ],
    ]);

// Simple rule
$rule = PBACAccessControl::factory()
    ->allow()
    ->forUser($user)
    ->forResource(Post::class, null)
    ->withAction('view')
    ->create();
```

### Complete Examples

#### Example 1: Basic User Permission

```php
use Pbac\Models\PBACAccessControl;
use App\Models\Post;

PBACAccessControl::factory()
    ->allow()
    ->forUser($user)
    ->forResource(Post::class, $post->id)
    ->withAction('view')
    ->create();

// Check
$user->can('view', $post); // true
```

#### Example 2: Group Permissions

```php
use Pbac\Models\PBACAccessGroup;

$editors = PBACAccessGroup::create(['name' => 'Editors']);
$user->groups()->attach($editors->id);

PBACAccessControl::factory()
    ->allow()
    ->forGroup($editors)
    ->forResource(Post::class, null)
    ->withAction(['view', 'edit', 'delete'])
    ->create();

// Check
$user->can('edit', $anyPost); // true (via group)
```

#### Example 3: Conditional Permission

```php
PBACAccessControl::factory()
    ->allow()
    ->forUser($user)
    ->forResource(Post::class, null)
    ->withAction('edit')
    ->create([
        'extras' => [
            'requires_attribute_value' => [
                'user_id' => $user->id, // Only own posts
            ],
        ],
    ]);

// Check
$user->can('edit', $myPost);    // true (user_id matches)
$user->can('edit', $otherPost); // false (user_id doesn't match)
```

#### Example 4: Priority Rules

```php
// Specific high-priority rule
PBACAccessControl::factory()
    ->allow()
    ->forUser($user)
    ->forResource(Post::class, $post->id)
    ->withAction('view')
    ->withPriority(100)
    ->create();

// General low-priority fallback
PBACAccessControl::factory()
    ->deny()
    ->forUser($user)
    ->forResource(Post::class, null)
    ->withAction('view')
    ->withPriority(1)
    ->create();

// Result: Deny wins (deny-first rule)
$user->can('view', $post); // false
```

---

## Services

### PolicyEvaluator

**Namespace**: `Pbac\Services\PolicyEvaluator`

The core service that evaluates permissions.

#### Methods

##### evaluate()

Evaluate if a user has permission for an action on a resource.

```php
public function evaluate(
    User $user,
    string $action,
    mixed $resource = null,
    array $context = []
): bool
```

**Parameters**:
- `$user` (User) - The user requesting access
- `$action` (string) - The action being performed
- `$resource` (Model|string|null) - The resource being accessed
- `$context` (array) - Additional context for condition evaluation

**Returns**: `bool`

**Usage**:

```php
use Pbac\Services\PolicyEvaluator;

$evaluator = app(PolicyEvaluator::class);

// Basic check
$canView = $evaluator->evaluate($user, 'view', $post);

// With context
$canEdit = $evaluator->evaluate($user, 'edit', $post, [
    'level' => $user->level,
    'ip' => request()->ip(),
]);
```

**Note**: You typically don't call this directly. Use `$user->can()` instead, which internally calls the PolicyEvaluator.

#### Singleton

PolicyEvaluator is registered as a singleton in the service container:

```php
$eval1 = app(PolicyEvaluator::class);
$eval2 = app(PolicyEvaluator::class);

$eval1 === $eval2; // true (same instance)
```

---

## Blade Directives

### @pbacCan / @endpbacCan

Custom Blade directive for checking PBAC permissions.

**Syntax**:

```blade
@pbacCan('action', $resource)
    {{-- Content shown if user has permission --}}
@endpbacCan
```

**Examples**:

```blade
{{-- Basic usage --}}
@pbacCan('edit', $post)
    <button>Edit Post</button>
@endpbacCan

{{-- With class name --}}
@pbacCan('create', App\Models\Post::class)
    <button>Create New Post</button>
@endpbacCan

{{-- Multiple checks --}}
@pbacCan('view', $document)
    <h1>{{ $document->title }}</h1>
    
    @pbacCan('edit', $document)
        <button>Edit</button>
    @endpbacCan
    
    @pbacCan('delete', $document)
        <button>Delete</button>
    @endpbacCan
@endpbacCan
```

**Laravel's Built-in Directives Also Work**:

PBAC integrates with Laravel's authorization system, so these work too:

```blade
@can('edit', $post)
    <button>Edit</button>
@endcan

@cannot('delete', $post)
    <p>You cannot delete this post</p>
@endcannot
```

---

## Laravel Gate Integration

PBAC integrates seamlessly with Laravel's Gate system via `Gate::before()` hook.

### Gate Facade

```php
use Illuminate\Support\Facades\Gate;

// Check permission
if (Gate::allows('edit', $post)) {
    // User can edit
}

if (Gate::denies('delete', $post)) {
    // User cannot delete
}

// Throw exception if denied
Gate::authorize('publish', $post);

// Check multiple permissions
if (Gate::any(['edit', 'delete'], $post)) {
    // User has either permission
}

if (Gate::none(['edit', 'delete'], $post)) {
    // User has neither permission
}
```

### Controller Authorization

```php
class PostController extends Controller
{
    public function edit(Post $post)
    {
        // Throws AuthorizationException if denied
        $this->authorize('edit', $post);
        
        return view('posts.edit', compact('post'));
    }
    
    public function update(Request $request, Post $post)
    {
        // Manual check
        if (Gate::denies('edit', $post)) {
            abort(403);
        }
        
        $post->update($request->all());
        
        return redirect()->route('posts.show', $post);
    }
}
```

### Middleware

```php
// In routes/web.php
Route::put('posts/{post}', [PostController::class, 'update'])
    ->middleware('can:edit,post');

// Or in controller constructor
class PostController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:edit,post')->only(['edit', 'update']);
        $this->middleware('can:delete,post')->only('destroy');
    }
}
```

### Form Requests

```php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePostRequest extends FormRequest
{
    public function authorize()
    {
        return $this->user()->can('edit', $this->route('post'));
    }
    
    public function rules()
    {
        return [
            'title' => 'required|max:255',
            'body' => 'required',
        ];
    }
}
```

---

## Helper Methods (Planned)

These methods are commented in the traits but can be implemented:

### HasPbacGroups

```php
// Check if user is in a specific group
public function isInPbacGroup(string|PBACAccessGroup $group): bool
{
    if ($group instanceof PBACAccessGroup) {
        return $this->groups->contains($group);
    }
    
    return $this->groups()->where('name', $group)->exists();
}

// Usage
if ($user->isInPbacGroup('Administrators')) {
    // User is admin
}
```

### HasPbacTeams

```php
// Check if user is in a specific team
public function isInPbacTeam(string|PBACAccessTeam $team): bool
{
    if ($team instanceof PBACAccessTeam) {
        return $this->teams->contains($team);
    }
    
    return $this->teams()->where('name', $team)->exists();
}

// Usage
if ($user->isInPbacTeam('Development')) {
    // User is in dev team
}
```

---

## Best Practices

### 1. Use Eager Loading

```php
// Bad (N+1 queries)
$users = User::all();
foreach ($users as $user) {
    foreach ($user->groups as $group) {
        echo $group->name;
    }
}

// Good
$users = User::with('groups')->get();
foreach ($users as $user) {
    foreach ($user->groups as $group) {
        echo $group->name;
    }
}
```

### 2. Use $user->can() Instead of PolicyEvaluator Directly

```php
// Bad
$evaluator = app(PolicyEvaluator::class);
$canEdit = $evaluator->evaluate($user, 'edit', $post);

// Good
$canEdit = $user->can('edit', $post);
```

### 3. Cache Permission Checks in Loops

```php
// Bad (checks permission for every post)
foreach ($posts as $post) {
    if ($user->can('edit', $post)) {
        // ...
    }
}

// Good (check once if possible)
$canEditAll = $user->can('edit', Post::class);
foreach ($posts as $post) {
    if ($canEditAll) {
        // ...
    }
}
```

### 4. Use Authorization Exceptions

```php
// Bad
if (!$user->can('delete', $post)) {
    abort(403);
}

// Good
Gate::authorize('delete', $post); // Throws AuthorizationException
```

### 5. Organize Rules by Priority

- **100+**: Critical/override rules
- **50-99**: Standard specific rules
- **10-49**: Group/role-based rules
- **1-9**: Fallback/general rules
- **0**: Default rules

---

## Next Steps

- [Basic Usage](basic-usage.md) - Learn how to use PBAC
- [Core Concepts](core-concepts.md) - Understand the fundamentals
- [Configuration](configuration.md) - Configure PBAC settings
- [Use Cases](use-cases.md) - See real-world examples
