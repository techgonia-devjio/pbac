# Installation Guide

## Requirements

- PHP 8.1+
- Laravel 11.0+
- Database: MySQL 8.0+, PostgreSQL 12+, or SQLite 3.35+

## Installation Steps

### 1. Install via Composer

```bash
composer require modules/pbac
```

### 2. Publish Assets

```bash
php artisan pbac:install
```

### 3. Run Migrations

```bash
php artisan migrate
```

### 4. Add Traits to User Model

```php
use Pbac\Traits\HasPbacAccessControl;
use Pbac\Traits\HasPbacGroups;
use Pbac\Traits\HasPbacTeams;

class User extends Authenticatable
{
    use HasPbacAccessControl;
    use HasPbacGroups;
    use HasPbacTeams;
}
```

## Configuration

Edit `config/pbac.php`:

```php
return [
    'user_model' => \App\Models\User::class,
    'super_admin_attribute' => 'is_super_admin',
];
```

## Verify Installation

```php
$user->can('view', $post); // Should work!
```
