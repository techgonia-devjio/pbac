# Use Cases - When to Use What

This document explains common scenarios and which PBAC pattern to use.

## Quick Decision Tree

```
Need permissions for your app?
├─ Simple blog with 2-3 roles? → Use Laravel Gates (PBAC is overkill)
├─ Multi-tenant SaaS? → Use PBAC Teams
├─ Multi-level access control (Company(App Owner) -> (Tenants) -> (Tenants clients))? → Use PBAC
|  └─ A company Developed the app for a client, and client has customers, and its inner teams(marketting, sales etc.)?  then client's customers might have there teams too → Use PBAC Teams + Groups
├─ Complex permissions per resource? → Use PBAC Rules
├─ Access depends on attributes? → Use PBAC with Extras
└─ Document management with file-level permissions? → Use PBAC Rules + Extras
```

---

## Use Case 1: Blog Platform

### Scenario
A blog where authors write posts, editors can edit anything, and readers can only view published content.

### PBAC Pattern: Groups

**Why Groups?**
- Fixed roles (author, editor, reader)
- Permissions based on role, not individual
- Simple hierarchy

### Implementation
```php
// Create groups (one-time setup)
$authors = PBACAccessGroup::create(['name' => 'Authors']);
$editors = PBACAccessGroup::create(['name' => 'Editors']);

// Authors: Can create and edit own posts
PBACAccessControl::factory()
    ->allow()
    ->forGroup($authors)
    ->forResource(Post::class, null)
    ->withAction(['create', 'edit', 'delete'])
    ->create([
        'extras' => [
            'requires_attribute_value' => ['user_id' => '$target.id']
        ]
    ]);

// Editors: Can edit any post
PBACAccessControl::factory()
    ->allow()
    ->forGroup($editors)
    ->forResource(Post::class, null)
    ->withAction(['view', 'edit', 'delete', 'publish'])
    ->create();
```

**Key Points:**
- ✅ Use groups for role-based permissions
- ✅ Use attribute conditions for "own resource" checks
- ✅ Deny rules not needed here (simple allow logic)

---

## Use Case 2: Multi-Tenant SaaS

### Scenario
A SaaS where each company has its own team, and data must be completely isolated between teams.

### PBAC Pattern: Teams + Attribute Conditions

**Why Teams?**
- Natural organizational boundary
- Perfect for data isolation
- Scalable (add teams without code changes)

### Implementation
```php
// Each company gets a team
$acmeCorp = PBACAccessTeam::create(['name' => 'Acme Corporation']);
$betaInc = PBACAccessTeam::create(['name' => 'Beta Inc']);

// Team members can only access their team's data
PBACAccessControl::factory()
    ->allow()
    ->forTeam($acmeCorp)
    ->forResource(Document::class, null)
    ->withAction(['view', 'create', 'edit', 'delete'])
    ->create([
        'extras' => [
            'requires_attribute_value' => ['team_id' => $acmeCorp->id]
        ]
    ]);
```

**Key Points:**
- ✅ One team per tenant
- ✅ ALWAYS use attribute conditions to check team_id
- ✅ Prevent cross-team access with strict conditions
- ⚠️ Apply team filter at database query level for performance

---

## Use Case 3: Document Management

### Scenario
A document system where:
- Drafts visible only to author
- Under review visible to reviewers
- Published visible to everyone
- Confidential restricted by IP

### PBAC Pattern: Multiple Rules with Conditions

**Why Multiple Rules?**
- Different permissions for different document states
- Complex conditions (IP, status, ownership)

### Implementation
```php
// 1. Drafts: Only author can see
PBACAccessControl::factory()
    ->allow()
    ->forGroup($users)
    ->forResource(Document::class, null)
    ->withAction(['view', 'edit'])
    ->create([
        'extras' => [
            'requires_attribute_value' => [
                'status' => 'draft',
                'author_id' => '$target.id'
            ]
        ]
    ]);

// 2. Under review: Reviewers can see
PBACAccessControl::factory()
    ->allow()
    ->forGroup($reviewers)
    ->forResource(Document::class, null)
    ->withAction(['view', 'approve', 'reject'])
    ->create([
        'extras' => ['requires_attribute_value' => ['status' => 'review']]
    ]);

// 3. Published: Everyone can see
PBACAccessControl::factory()
    ->allow()
    ->forGroup($users)
    ->forResource(Document::class, null)
    ->withAction('view')
    ->create([
        'extras' => ['requires_attribute_value' => ['status' => 'published']]
    ]);

// 4. Confidential: IP restricted
PBACAccessControl::factory()
    ->allow()
    ->forGroup($management)
    ->forResource(Document::class, null)
    ->withAction('view')
    ->create([
        'extras' => [
            'requires_attribute_value' => ['classification' => 'confidential'],
            'allowed_ips' => ['192.168.1.0/24']
        ]
    ]);
```

**Key Points:**
- ✅ Multiple rules for different scenarios
- ✅ Layer conditions (status + ownership)
- ✅ Use IP restrictions for sensitive data
- 💡 Consider caching rules for performance

---

## Use Case 4: E-Commerce Platform

### Scenario
- Customers manage their orders
- Vendors manage their products  
- Admins manage everything

### PBAC Pattern: Groups + Ownership Conditions

```php
// Customers: Own orders only
PBACAccessControl::factory()
    ->allow()
    ->forGroup($customers)
    ->forResource(Order::class, null)
    ->withAction(['view', 'cancel'])
    ->create([
        'extras' => [
            'requires_attribute_value' => ['customer_id' => '$target.id']
        ]
    ]);

// Vendors: Own products only
PBACAccessControl::factory()
    ->allow()
    ->forGroup($vendors)
    ->forResource(Product::class, null)
    ->withAction(['create', 'view', 'edit', 'delete'])
    ->create([
        'extras' => [
            'requires_attribute_value' => ['vendor_id' => '$target.id']
        ]
    ]);

// Admins: Everything
PBACAccessControl::factory()
    ->allow()
    ->forGroup($admins)
    ->forResource(null, null)
    ->withAction('*')
    ->create();
```

---

## Use Case 5: Healthcare/Compliance

### Scenario
HIPAA-compliant patient records with strict access control and audit requirements.

### PBAC Pattern: Deny Rules + Fine-Grained Permissions

**Why Deny Rules?**
- Security-critical (healthcare data)
- Explicit blocks override any allows
- Compliance requirements

### Implementation
```php
// Default: Allow doctors to see their patients
PBACAccessControl::factory()
    ->allow()
    ->forGroup($doctors)
    ->forResource(PatientRecord::class, null)
    ->withAction('view')
    ->create([
        'extras' => [
            'requires_attribute_value' => ['assigned_doctor_id' => '$target.id']
        ]
    ]);

// Deny: Block access to VIP patients (except assigned doctor)
PBACAccessControl::factory()
    ->deny()
    ->forGroup($doctors)
    ->forResource(PatientRecord::class, null)
    ->withAction('*')
    ->create([
        'extras' => [
            'requires_attribute_value' => [
                'is_vip' => true,
                // Except: NOT their assigned doctor
            ]
        ]
    ]);

// Allow: Emergency access (override with high priority)
PBACAccessControl::factory()
    ->allow()
    ->forGroup($emergencyStaff)
    ->forResource(PatientRecord::class, null)
    ->withAction('view')
    ->withPriority(1000) // High priority
    ->create();
```

**Key Points:**
- ✅ Use deny rules for sensitive data
- ✅ Layer multiple rules for defense in depth
- ✅ Audit all access (enable PBAC logging)
- ⚠️ Emergency access needs special handling

---

## Use Case 6: Project Management

### Scenario
Projects with managers, members, and guests having different permissions.

### PBAC Pattern: Hierarchical Groups

```php
// Project-specific groups
$managers = PBACAccessGroup::create(['name' => 'Project 1 Managers']);
$members = PBACAccessGroup::create(['name' => 'Project 1 Members']);
$guests = PBACAccessGroup::create(['name' => 'Project 1 Guests']);

// Managers: Full control
PBACAccessControl::factory()
    ->allow()
    ->forGroup($managers)
    ->forResource(Project::class, $project->id)
    ->withAction('*')
    ->create();

// Members: View and edit tasks
PBACAccessControl::factory()
    ->allow()
    ->forGroup($members)
    ->forResource(Task::class, null)
    ->withAction(['view', 'edit', 'comment'])
    ->create([
        'extras' => [
            'requires_attribute_value' => ['project_id' => $project->id]
        ]
    ]);

// Guests: View only
PBACAccessControl::factory()
    ->allow()
    ->forGroup($guests)
    ->forResource(Task::class, null)
    ->withAction('view')
    ->create([
        'extras' => [
            'requires_attribute_value' => ['project_id' => $project->id]
        ]
    ]);
```

---

## Use Case 7: Admin Panel

### Scenario
Admin panel with different access levels and IP restrictions.

### PBAC Pattern: Priority + IP Conditions

```php
// Super Admin: Unrestricted
PBACAccessControl::factory()
    ->allow()
    ->forUser($superAdmin)
    ->forResource(AdminPanel::class, null)
    ->withAction('*')
    ->withPriority(100)
    ->create();

// Regular Admin: Office IP only
PBACAccessControl::factory()
    ->allow()
    ->forGroup($admins)
    ->forResource(AdminPanel::class, null)
    ->withAction(['view', 'edit'])
    ->withPriority(50)
    ->create([
        'extras' => [
            'allowed_ips' => ['192.168.1.0/24', '10.0.0.50']
        ]
    ]);

// Deny: Block after hours (low priority, checked after allows)
PBACAccessControl::factory()
    ->deny()
    ->forGroup($admins)
    ->forResource(AdminPanel::class, null)
    ->withAction('*')
    ->withPriority(1)
    ->create([
        'extras' => [
            // Custom: Check if outside business hours
        ]
    ]);
```

---

## Pattern Comparison

| Scenario | Pattern | Complexity | Scalability |
|----------|---------|------------|-------------|
| Simple roles | Groups | Low | Medium |
| Multi-tenant | Teams + Conditions | Medium | High |
| State-based permissions | Multiple rules + Conditions | Medium | Medium |
| Ownership | Groups + Attribute conditions | Low | High |
| Security-critical | Deny rules + Fine-grained | High | Medium |
| Hierarchical | Nested groups + Priority | High | Low |

---

## Best Practices by Use Case

### For SaaS / Multi-Tenant
✅ Always use Teams  
✅ Always check team_id in conditions  
✅ Filter at query level first  
✅ One rule per team (scalable)

### For Content Management
✅ Use status-based conditions  
✅ Separate rules for each status  
✅ Cache rules aggressively  
✅ Use priority for performance

### For Compliance / Healthcare
✅ Use deny rules liberally  
✅ Enable detailed logging  
✅ Regular audit of rules  
✅ Emergency access procedures

### For E-Commerce
✅ Ownership conditions for customers  
✅ Vendor isolation with conditions  
✅ Admin override rules  
✅ Order status transitions

---

## Anti-Patterns (What NOT to Do)

❌ **Too Many Individual User Rules**
```php
// Bad: 10,000 rules for 10,000 users
foreach ($users as $user) {
    PBACAccessControl::factory()->allow()->forUser($user)->create();
}

// Good: 1 rule for a group
PBACAccessControl::factory()->allow()->forGroup($users)->create();
```

❌ **No Conditions on Multi-Tenant**
```php
// Bad: Allows cross-team access\!
->forTeam($team)
->create(); // Missing team_id check\!

// Good: Enforces isolation
->forTeam($team)
->create(['extras' => ['requires_attribute_value' => ['team_id' => $team->id]]]);
```

❌ **Using Priority Instead of Deny**
```php
// Bad: Trying to "override" with high priority
->allow()->withPriority(1000)

// Good: Use deny for blocking
->deny() // Always wins
```

---

## Next Steps

- [Core Concepts](core-concepts.md) - Understand the building blocks
- [Basic Usage](basic-usage.md) - Implement these patterns
- [Architecture](architecture.md) - How it works internally

