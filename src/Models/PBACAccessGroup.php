<?php

namespace Modules\Pbac\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class PBACAccessGroup extends Model
{
    use HasFactory;
    protected $table = 'pbac_access_groups';

    protected $fillable = [
        'name',
        'description',
    ];

    public function users(): BelongsToMany
    {
        $userClass = config('pbac.user_model');
        return $this->belongsToMany($userClass, 'pbac_group_user', 'pbac_access_group_id', 'user_id');
    }

}
