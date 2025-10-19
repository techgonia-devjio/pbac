# PBAC Package Overview

## What is PBAC?

**PBAC (Policy-Based Access Control)** is a flexible authorization system for Laravel applications that combines the best features of three access control models:

- **RBAC** (Role-Based Access Control) - Using groups and teams
- **ABAC** (Attribute-Based Access Control) - Using runtime conditions  
- **ACL** (Access Control Lists) - Individual permissions per resource

Instead of rigid roles and permissions, PBAC uses **rules** to define who can do what, when, and under what conditions.

## Why Use PBAC?

### Traditional RBAC Limitations

Traditional role-based systems are simple but inflexible:

```php
// Traditional RBAC - rigid
$user->assignRole('editor');
$user->hasPermission('edit-posts'); // true for ALL posts

// Problem: What if user should only edit THEIR posts? Use policies? then what if particular user needs special access without the code and redeploy?
// Problem: What if access depends on post status (draft/published)?
// Problem: What if access depends on user location/IP?
```

### PBAC Solution

PBAC gives you fine-grained control:

```php
// PBAC - flexible
PBACAccessControl::create([
    'target' => $user,              // WHO
    'action' => 'edit',              // WHAT action
    'resource' => Post::class,      // ON WHAT resource
    'effect' => 'allow',            // ALLOW or DENY
    'extras' => [                   // UNDER WHAT conditions
        'requires_attribute_value' => [
            'user_id' => $user->id,      // Only their posts
            'status' => 'draft'        // Only draft posts
        ]
    ]
]);
```

### Key Benefits

1. **Fine-Grained Control** - Permissions at individual resource level
2. **Dynamic Conditions** - Access based on runtime attributes
3. **Deny-First Security** - Explicit denies always win (secure by default)
4. **Flexibility** - Combine user, group, and team permissions
5. **Auditable** - All rules stored in database, easy to track changes
6. **No Code Deployments** - Change permissions without deploying code

## When to Use PBAC

### Perfect For:

✅ **Multi-tenant applications** - Isolate data between tenants  
✅ **Complex permission requirements** - Multiple conditions  
✅ **Document management systems** - File-level permissions  
✅ **SaaS platforms** - Per-customer access control  
✅ **Content management** - Draft vs published content  
✅ **Healthcare/Finance** - Compliance requirements (HIPAA, SOC2)  

### Overkill For:

❌ **Simple blog** with just admins and users  
❌ **Read-only applications** with no authorization needs  
❌ **Single-user applications**  

## How It Works

### The Rule-Based Model

Every permission in PBAC is a **rule** that defines:

```
IF [target] wants to [action] on [resource]
AND [conditions are met]
THEN [allow or deny]
```

Example rule in plain English:
```
IF user John wants to edit a Post
AND the post.user_id equals John's ID
AND the post.status is 'draft'
THEN allow
```

### Rule Evaluation Flow

When checking `$user->can('edit', $post)`:

1. **Find matching rules** for this user, action, and resource
2. **Check DENY rules first** (deny-first security model)
3. If any deny rule matches → **Access DENIED** ❌
4. If no deny rules, check ALLOW rules
5. If any allow rule matches → **Access GRANTED** ✅
6. If no rules match → **Access DENIED** (secure by default) ❌

### Example Flow

```php
// User wants to edit a post
$user->can('edit', $post);

// Step 1: Find rules for (user, 'edit', Post)
Rules found:
  - Rule 1: ALLOW user to edit posts where post.user_id = user.id
  - Rule 2: DENY user to edit posts where post.status = 'published'

// Step 2: Check DENY rules first
Rule 2: Does post.status = 'published'?
  - If YES → Access DENIED (stop here)
  - If NO → Continue to allow rules

// Step 3: Check ALLOW rules
Rule 1: Does post.user_id = user.id?
  - If YES → Access GRANTED
  - If NO → Access DENIED (no matching allow)
```

## Core Philosophy

### 1. Secure by Default

```php
// No rules defined
$user->can('view', $post); // false (denied)

// Must explicitly grant access
PBACAccessControl::create([...]);
$user->can('view', $post); // true (allowed)
```

### 2. Deny Always Wins

```php
// Even if there are 100 allow rules...
PBACAccessControl::factory()->allow()->create();

// ...a single deny rule overrides them all
PBACAccessControl::factory()->deny()->create();

// Result: DENIED (security first)
```

### 3. Explicit is Better Than Implicit

```php
// Bad: Implicit permission through role
$user->assignRole('admin'); // What can admin do? Unclear\!

// Good: Explicit permission rules
PBACAccessControl::factory()
    ->allow()
    ->forUser($admin)
    ->forResource(Post::class, null)
    ->withAction(['view', 'edit', 'delete'])
    ->create();
// Crystal clear what admin can do
```

## Real-World Analogy

Think of PBAC like a **bouncer at a club**:

- **Target** = Person trying to enter (you)
- **Resource** = Section of the club (VIP area)
- **Action** = What you want to do (enter)
- **Conditions** = Extra requirements (age >21, proper dress code)
- **Effect** = Allow or deny entry

The bouncer checks:
1. Are you on the **ban list**? (DENY rules) → If yes, **rejected**
2. Are you on the **guest list**? (ALLOW rules) → If yes, **allowed**
3. Do you meet **requirements**? (conditions) → Check age, dress code
4. Default: **No entry** (secure by default)

## Comparison with Alternatives

| Feature | RBAC | Gates/Policies | Spatie Permission | PBAC |
|---------|------|----------------|-------------------|------|
| Role-based | ✅ | ❌ | ✅ | ✅ (via groups) |
| Resource-level | ❌ | ✅ | ❌ | ✅ |
| Attribute-based | ❌ | ⚠️ (custom) | ❌ | ✅ |
| Deny rules | ❌ | ⚠️ (custom) | ❌ | ✅ |
| Database-driven | ✅ | ❌ | ✅ | ✅ |
| No deployments | ✅ | ❌ | ✅ | ✅ |
| Teams support | ❌ | ❌ | ❌ | ✅ |
| Priority system | ❌ | ❌ | ❌ | ✅ |

## Next Steps

Now that you understand what PBAC is, learn about:

1. [Core Concepts](core-concepts.md) - Deep dive into targets, resources, rules, etc.
2. [Architecture](architecture.md) - How PBAC works internally
3. [Basic Usage](basic-usage.md) - Start using PBAC in your application
4. [Use Cases](use-cases.md) - Real-world scenarios

