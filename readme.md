# Laravel PBAC (Policy-Based Access Control)

[![Tests](https://img.shields.io/badge/tests-passing-brightgreen)]() [![License](https://img.shields.io/badge/license-MIT-blue)]()

A powerful, flexible, and Policy-Based Access Control (PBAC) system for Laravel 1+.
Combines the best of RBAC (Role-Based), ABAC (Attribute-Based), and ACL (Access Control List) into a unified, fine-grained permission system.

## ✨ Features

- **Fine-Grained Permissions** - Control access at user, group, team, and resource levels
- **Deny-First Security** - Explicit deny rules always override allow rules
- **Flexible Targeting** - Apply rules to individual users, groups, teams, or any combination
- **Priority-Based Rules** - Control rule evaluation order with priority levels
- **Attribute-Based Conditions** - Dynamic permissions based on runtime attributes (IP, user level, resource state)
- **High Performance** - Optimized queries with caching support
- **Laravel Integration** - Seamless integration with Laravel's Gate and Blade directives
- **Super Admin Bypass** - Built-in super admin support
- **100% Test Coverage** - Comprehensive test suite with 212 tests

## 📋 Requirements

- PHP 8.1 or higher
- Laravel 11.0 or 12.0
- Database: MySQL 8.0+, PostgreSQL 12+, or SQLite 3.35+

## Quick Start

```bash
# Install via Composer
composer require techgonia/pbac

# Publish configuration and migrations
php artisan vendor:publish --tag="pbac-config"
php artisan vendor:publish --tag="pbac-migrations"

# Run migrations
php artisan migrate
```

### Add Traits to Your User Model

```php
use Pbac\Traits\HasPbacAccessControl;
use Pbac\Traits\HasPbacGroups;
use Pbac\Traits\HasPbacTeams;

class User extends Authenticatable
{
    use HasPbacAccessControl, HasPbacGroups, HasPbacTeams;
}
```

### Basic Example

```php
use Pbac\Models\PBACAccessControl;

// Grant permission
PBACAccessControl::factory()
    ->allow()
    ->forUser($user)
    ->forResource(Post::class, $post->id)
    ->withAction('edit')
    ->create();

// Check permission
if ($user->can('edit', $post)) {
    // User can edit this post
}
```

## 📚 Documentation

### Getting Started
- [Installation Guide](docs/installation.md) - Step-by-step installation instructions
- [Overview](docs/overview.md) - What is PBAC and why use it
- [Core Concepts](docs/core-concepts.md) - Understanding targets, resources, actions, and rules

### Usage Guides
- [Basic Usage](docs/basic-usage.md) - Creating and checking permissions
- [Use Cases](docs/use-cases.md) - Real-world application patterns and examples
- [Configuration](docs/configuration.md) - Complete configuration reference

### Technical Reference
- [Architecture](docs/architecture.md) - Internal architecture and design decisions
- [API Reference](docs/api-reference.md) - Complete API documentation

## 💡 Core Concepts

### The PBAC Model

PBAC uses a rule-based system where each rule defines:
- **Target**: Who (user, group, team)
- **Resource**: What (post, file, setting, user, impersonation)
- **Action**: How (view, edit, delete, custom actions)
- **Effect**: Allow or Deny
- **Conditions**(optional): When (IP restrictions, attribute checks)

### Security Model: Deny-First

Deny rules ALWAYS override allow rules, regardless of priority:

```php
// Even with high priority allow...
PBACAccessControl::factory()->allow()->withPriority(1000)->create();

// ...a low priority deny wins
PBACAccessControl::factory()->deny()->withPriority(1)->create();

// Result: Access DENIED (secure by default)
```

## 🎯 Common Use Cases

### 1. Group-Based Permissions

```php
$editors = PBACAccessGroup::create(['name' => 'Editors']);
$user->groups()->attach($editors->id);

PBACAccessControl::factory()
    ->allow()
    ->forGroup($editors)
    ->forResource(Post::class, null) // All posts
    ->withAction(['view', 'edit', 'publish'])
    ->create();
```

### 2. IP-Based Restrictions

```php
PBACAccessControl::factory()
    ->allow()
    ->forUser($admin)
    ->forResource(AdminPanel::class, null)
    ->withAction('access')
    ->create([
        'extras' => ['allowed_ips' => ['192.168.1.0/24']]
    ]);
```

### 3. Attribute-Based Access

```php
PBACAccessControl::factory()
    ->allow()
    ->forUser($user)
    ->forResource(Post::class, null)
    ->withAction('edit')
    ->create([
        'extras' => [
            'requires_attribute_value' => ['status' => 'draft']
        ]
    ]);
```

### 4. Team Isolation

```php
$team = PBACAccessTeam::create(['name' => 'Team Alpha']);
$user->teams()->attach($team->id);

PBACAccessControl::factory()
    ->allow()
    ->forTeam($team)
    ->forResource(Document::class, null)
    ->withAction('*')
    ->create();
```

## 🔥 Advanced Features

### Super Admin Bypass

```php
$user->is_super_admin = true;
$user->can('anything', $anything); // always true
```

### Laravel Gate Integration

```php
Gate::allows('edit', $post);
Gate::authorize('publish', $post);
```

### Blade Directives

```blade
@pbacCan('edit', $post)
    <button>Edit</button>
@endpbacCan
```

### Factory Helpers

```php
PBACAccessControl::factory()
    ->allow()                           // Set effect
    ->forUser($user)                   // Set target
    ->forResource(Post::class, $id)    // Set resource
    ->withAction(['view', 'edit'])      // Set actions
    ->withPriority(10)                  // Set priority
    ->create(['extras' => [...]]);      // Add conditions
```

## 🧪 Testing

```bash
# Run all tests
./vendor/bin/phpunit

# Run with Pest
./vendor/bin/pest

# Test coverage
./vendor/bin/phpunit --coverage-html coverage
```

**Test Suite**: 212 tests
- 133 Unit tests
- 70 Integration tests
- 68 Regression tests

## 🤝 Contributing

Contributions welcome! Please see [CONTRIBUTING.md](CONTRIBUTING.md).

## 📝 License

MIT License - see [LICENSE.md](LICENSE.md)

## 🙏 Credits

Built with ❤️ for Laravel developers

