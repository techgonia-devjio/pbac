<?php

namespace Pbac\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo; // If you have an owner

class PBACAccessTeam extends Model
{
    use HasFactory;

    protected $table = 'pbac_access_teams';

    protected $fillable = [
        'name',
        'description',
        'owner_id'
    ];


    public function users(): BelongsToMany
    {
        $userClass = config('pbac.user_model');
        return $this->belongsToMany($userClass, 'pbac_team_user', 'pbac_team_id', 'user_id');
    }


     public function owner(): BelongsTo
     {
         $userClass = config('pbac.user_model');
         return $this->belongsTo($userClass, 'owner_id');
     }

}
