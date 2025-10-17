<?php

namespace Modules\Pbac\Tests\Support\Models;



use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\Access\Authorizable;

class PlainUser extends Model implements AuthenticatableContract, AuthorizableContract
{
    use Authenticatable;
    use Authorizable;
    use HasFactory;

    protected $fillable = ['email', 'password', 'name', 'is_super_admin'];

    public $timestamps = false;

    protected $table = 'users';
}
