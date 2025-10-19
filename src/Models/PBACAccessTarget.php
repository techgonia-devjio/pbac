<?php

namespace Pbac\Models;

use Illuminate\Database\Eloquent\Model;

class PBACAccessTarget extends Model
{
    use \Illuminate\Database\Eloquent\Factories\HasFactory;
    protected $table = 'pbac_access_targets';
    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function accessRules()
    {
        return $this->hasMany(PBACAccessControl::class, 'pbac_access_target_id');
    }
}
