<?php

namespace Pbac\Traits;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Models\Team;
use Pbac\Models\PBACAccessTeam;

// Assuming your Team model is in App\Models

trait HasPbacTeams
{
    /**
     * The PBAC teams that the user belongs to.
     * This relationship is used by the PBAC PolicyEvaluator to identify team targets.
     */
    public function teams(): BelongsToMany
    {
        $userClass = config('pbac.user_model');
        $teamClass = PBACAccessTeam::class; // Use the PBAC Team model
        $pivotTable = config('pbac.table_names.team_user', 'pbac_team_user'); // Get pivot table name from config
        $foreignPivotKey = 'pbac_team_id'; // Foreign key for the team in the pivot table
        $relatedPivotKey = 'user_id'; // Foreign key for the user in the pivot table

        // Use the correct pivot table and foreign keys
        return $this->belongsToMany($teamClass, $pivotTable, $relatedPivotKey, $foreignPivotKey);
    }

    // You could add helper methods here related to team membership if needed,
    // e.g., isInPbacTeam(string|PBACAccessTeam $team)
}
