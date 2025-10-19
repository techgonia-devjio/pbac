# Core Concepts

This document explains all the fundamental concepts in PBAC in detail.

## Table of Contents

1. [Targets](#targets)
2. [Resources](#resources)
3. [Actions](#actions)
4. [Effects](#effects)
5. [Rules](#rules)
6. [Groups](#groups)
7. [Teams](#teams)
8. [Priority](#priority)
9. [Extras (Conditions)](#extras-conditions)

---

## Targets

### What is a Target?

A **target** is **WHO** is trying to access something. It answers the question: "Who is requesting access?"

### Types of Targets

1. **Individual User** - A specific person
2. **Group** - A collection of users (like a role)
3. **Team** - A organizational unit of users

### Examples

```php
// Individual user as target
$user = User::find(1);
Target: User #1 (John Doe)

// Group as target  
$editors = PBACAccessGroup::find(2);
Target: Group #2 (Editors)

// Team as target
$devTeam = PBACAccessTeam::find(3);
Target: Team #3 (Development Team)
```

### Target in Database

Targets are represented by two fields:

- `pbac_access_target_id` - Points to target TYPE (user, group, or team)
- `target_id` - Points to specific instance (which user, which group), if specified otherwise null for ANY

```php
// Rule for specific user
pbac_access_target_id: 1 (points to User type)
target_id: 42 (points to User #42)

// Rule for any user of this type
pbac_access_target_id: 1 (points to User type)  
target_id: null (ANY user)
```

### Why Targets?

Targets let you assign permissions at different levels:

```php
// Fine-grained: Permission for ONE user
forUser($john)

// Medium: Permission for a GROUP of users
forGroup($editors)

// Broad: Permission for entire TEAM
forTeam($devTeam)
```

---

## Resources

### What is a Resource?

A **resource** is **WHAT** is being accessed. It answers: "What are they trying to access?"

### Common Resources

- Blog Posts
- Documents
- User Profiles
- Files
- Settings
- API Endpoints
- Database Records

### Examples

```php
// Specific post
Resource: Post #123

// Any post
Resource: Post (any instance)

// Specific user profile
Resource: User #456

// Application settings
Resource: Settings (class name)
```

### Resource in Database

Resources are represented by two fields:

- `pbac_access_resource_id` - Points to resource TYPE (Post, Document, etc.)
- `resource_id` - Points to specific instance (which post, which document)

```php
// Rule for specific post
pbac_access_resource_id: 1 (points to Post type)
resource_id: 123 (points to Post #123)

// Rule for ANY post
pbac_access_resource_id: 1 (points to Post type)
resource_id: null (ANY post)

// Rule for ANY resource type
pbac_access_resource_id: null
resource_id: null (GLOBAL rule)
```

### Resource Registration

Before using a resource in PBAC, register it:

```php
use Modules\Pbac\Models\PBACAccessResource;

PBACAccessResource::create([
    'type' => Post::class,
    'description' => 'Blog posts',
    'is_active' => true,
]);
```

This tells PBAC: "Posts are protected resources in this application"

### Why Resources?

Resources let you control access at different levels:

```php
// Fine-grained: Permission for ONE specific post
forResource(Post::class, 123)

// Medium: Permission for ALL posts
forResource(Post::class, null)

// Broad: Permission for EVERYTHING
forResource(null, null)
```

---

## Actions

### What is an Action?

An **action** is **WHAT** the target wants to do. It answers: "What operation are they trying to perform?"

### Common Actions

```php
// CRUD operations
'create' - Create a new resource
'view'   - Read/view a resource
'edit'   - Update a resource
'delete' - Remove a resource

// Custom actions
'publish'  - Publish content
'approve'  - Approve a request
'download' - Download a file
'share'    - Share with others
'export'   - Export data
```

### Action Format

Actions are stored as JSON arrays:

```php
// Single action
'action': ['view']

// Multiple actions
'action': ['view', 'edit', 'delete']

// Wildcard (any action)
'action': ['*']
```

### Case Sensitivity

Actions are **case-sensitive**:

```php
'view' ≠ 'View' ≠ 'VIEW'

// These are all different actions!
```

### Best Practices

1. **Use lowercase** - `'view'` not `'View'`
2. **Use verbs** - `'edit'` not `'editing'`
3. **Be consistent** - `'delete'` everywhere, not sometimes `'remove'`
4. **Document custom actions** - Keep a list of your application's actions

### Examples

```php
// Allow user to view and edit
->withAction(['view', 'edit'])

// Allow user to do everything
->withAction('*')

// Allow only viewing
->withAction('view')
```

---

## Effects

### What is an Effect?

An **effect** determines whether to **ALLOW** or **DENY** access.

### Two Types

1. **Allow** - Grant permission
2. **Deny** - Block permission

### The Deny-First Model

This is CRUCIAL to understand:

**DENY RULES ALWAYS WIN**, regardless of:
- Priority level
- Number of allow rules
- Order of creation

```php
// Even with 100 allow rules
for ($i = 0; $i < 100; $i++) {
    PBACAccessControl::factory()
        ->allow()
        ->withPriority(1000) // Very high priority
        ->create();
}

// ONE deny rule blocks everything
PBACAccessControl::factory()
    ->deny()
    ->withPriority(1) // Low priority
    ->create();

// Result: DENIED
```

### Why Deny-First?

Security! It's safer to accidentally block access than to accidentally grant access.

```php
// Scenario: You want to block ONE specific document
// but allow access to all other documents

// Allow access to all documents
PBACAccessControl::factory()
    ->allow()
    ->forResource(Document::class, null)
    ->create();

// Block access to classified document
PBACAccessControl::factory()
    ->deny()
    ->forResource(Document::class, $classifiedDoc->id)
    ->create();

// Even though allow comes first, deny wins for that document
$user->can('view', $normalDoc);      // true (allowed)
$user->can('view', $classifiedDoc); // false (denied)
```

### Examples

```php
// Grant permission
->allow()

// Block permission  
->deny()
```

---

## Rules

### What is a Rule?

A **rule** is a complete permission definition. It combines all the concepts above:

```
IF [target] wants to [action] on [resource]
AND [conditions are met]
THEN [effect]
```

### Rule Components

Every rule has:

1. **Target** - Who (user/group/team)
2. **Resource** - What (post/document/file)
3. **Action** - How (view/edit/delete)
4. **Effect** - Allow or Deny
5. **Priority** - Order of evaluation (optional)
6. **Extras** - Conditions (optional)

### Rule Example

```php
PBACAccessControl::create([
    // TARGET
    'pbac_access_target_id' => 1,  // User type
    'target_id' => 42,              // User #42

    // RESOURCE  
    'pbac_access_resource_id' => 2, // Post type
    'resource_id' => 123,           // Post #123

    // ACTION
    'action' => ['view', 'edit'],

    // EFFECT
    'effect' => 'allow',

    // PRIORITY (optional)
    'priority' => 10,

    // CONDITIONS (optional)
    'extras' => [
        'requires_attribute_value' => [
            'status' => 'draft'
        ]
    ]
]);
```

In plain English:
> "User #42 is allowed to view and edit Post #123, but only if the post status is draft"

### Rule Specificity

Rules can be **specific** or **general**:

```php
// Very specific: ONE user, ONE post, ONE action
forUser($john)
    ->forResource(Post::class, 123)
    ->withAction('view')

// General: ALL users, ALL posts, MANY actions  
forUser(null)
    ->forResource(Post::class, null)
    ->withAction(['view', 'edit', 'delete'])

// Global: EVERYONE, EVERYTHING, ANY action
forResource(null, null)
    ->withAction('*')
```

---

## Groups

### What is a Group?

A **group** is a collection of users who share permissions. Think of it as a **role** in traditional RBAC.

### Examples

- Editors
- Administrators  
- Premium Users
- Free Users
- Moderators

### Group Structure

```php
PBACAccessGroup {
    id: 1
    name: 'Editors'
    description: 'Users who can edit content'
    created_at: ...
    updated_at: ...
}
```

### Creating a Group

```php
$editors = PBACAccessGroup::create([
    'name' => 'Editors',
    'description' => 'Users who can edit content'
]);
```

### Adding Users to Group

```php
// From user side
$user->groups()->attach($editors->id);

// From group side
$editors->users()->attach([$user1->id, $user2->id]);
```

### Granting Permissions to Group

```php
// All group members get this permission
PBACAccessControl::factory()
    ->allow()
    ->forGroup($editors)
    ->forResource(Post::class, null)
    ->withAction(['view', 'edit'])
    ->create();
```

### Groups vs Teams

| Feature | Groups | Teams |
|---------|--------|-------|
| Purpose | Functional roles | Organizational units |
| Example | Editors, Admins | Dev Team, Sales Team |
| Hierarchy | Flat | Can have owner |
| Typical use | Permissions | Multi-tenancy |

---

## Teams

### What is a Team?

A **team** is an organizational unit of users. Perfect for **multi-tenant applications** where you need to isolate data between organizations.

### Examples

- Company A's team
- Project Alpha team
- Department of Engineering
- Client XYZ's team

### Team Structure

```php
PBACAccessTeam {
    id: 1
    name: 'Acme Corporation'
    description: 'Acme team members'
    owner_id: 42 // Optional team owner
    created_at: ...
    updated_at: ...
}
```

### Creating a Team

```php
$team = PBACAccessTeam::create([
    'name' => 'Acme Corporation',
    'description' => 'Acme team members',
    'owner_id' => $owner->id
]);
```

### Multi-Tenant Isolation

```php
// Team A can only see Team A's documents
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

---

## Priority

### What is Priority?

**Priority** determines the **order** in which rules are evaluated. Higher priority = evaluated first.

### Default Priority

```php
priority: 0 // Default if not specified
```

### How It Works

Rules are evaluated in this order:

1. **DENY rules** (highest priority first)
2. **ALLOW rules** (highest priority first)

```php
// Priority 100 (checked first)
PBACAccessControl::factory()
    ->allow()
    ->withPriority(100)
    ->create();

// Priority 50 (checked second)
PBACAccessControl::factory()
    ->allow()
    ->withPriority(50)
    ->create();

// Priority 10 (checked third)
PBACAccessControl::factory()
    ->allow()
    ->withPriority(10)
    ->create();
```

### When to Use Priority

Use priority when you have multiple rules and want to control which one is evaluated first:

```php
// High priority: Simple rule (fast to check)
PBACAccessControl::factory()
    ->allow()
    ->withPriority(100)
    ->create(); // No conditions, quick check

// Low priority: Complex rule (slow to check)
PBACAccessControl::factory()
    ->allow()
    ->withPriority(1)
    ->create([
        'extras' => [...] // Many conditions, slow check
    ]);
```

### Important Note

Priority does NOT override deny-first rule:

```php
// High priority allow
PBACAccessControl::factory()->allow()->withPriority(1000)->create();

// Low priority deny
PBACAccessControl::factory()->deny()->withPriority(1)->create();

// Result: DENIED (deny always wins, regardless of priority)
```

---

## Extras (Conditions)

### What are Extras?

**Extras** are runtime conditions that must be met for a rule to apply. They enable **Attribute-Based Access Control (ABAC)**.

### Supported Conditions

#### 1. Minimum Level

```php
'extras' => [
    'min_level' => 5
]

// Access granted only if context['level'] >= 5
$user->can('view', [
    'resource' => $post,
    'context' => ['level' => 10] // ✅ 10 >= 5
]);
```

#### 2. Allowed IPs

```php
'extras' => [
    'allowed_ips' => [
        '192.168.1.100',      // Specific IP
        '10.0.0.0/24'          // CIDR range
    ]
]

// Access granted only from these IPs
$user->can('access', [
    'resource' => AdminPanel::class,
    'context' => ['ip' => request()->ip()]
]);
```

#### 3. Required Attribute Values

```php
'extras' => [
    'requires_attribute_value' => [
        'status' => 'published',
        'is_featured' => true
    ]
]

// Access granted only if:
// - resource.status === 'published'
// - resource.is_featured === true
```

### Passing Context

Context is passed at runtime:

```php
// Without context
$user->can('view', $post);

// With context
$user->can('view', [
    'resource' => $post,
    'context' => [
        'ip' => request()->ip(),
        'level' => $user->security_level,
        'time' => now()
    ]
]);
```

### Why Use Extras?

Extras enable dynamic permissions:

```php
// Static: User can always edit
->withAction('edit')

// Dynamic: User can edit only draft posts
->withAction('edit')
->create([
    'extras' => [
        'requires_attribute_value' => [
            'status' => 'draft'
        ]
    ]
]);
```

---

## Summary

| Concept | Answers | Example |
|---------|---------|---------|
| Target | WHO? | User #42, Editors group |
| Resource | WHAT? | Post #123, All documents |
| Action | HOW? | view, edit, delete |
| Effect | RESULT? | allow or deny |
| Rule | Complete permission | "User #42 can edit Post #123" |
| Group | Collection of users | Editors, Admins |
| Team | Organizational unit | Acme Corp, Dev Team |
| Priority | Evaluation order | 100, 50, 10 |
| Extras | Conditions | IP restrictions, attribute checks |

## Next Steps

- [Architecture](architecture.md) - How PBAC works internally
- [Basic Usage](basic-usage.md) - Start using these concepts
- [Use Cases](use-cases.md) - See concepts in action

