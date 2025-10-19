<?php

namespace Pbac\Traits;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Pbac\Models\PBACAccessGroup;

trait HasPbacGroups
{
    /**
     * The PBAC groups that the user belongs to.
     * This relationship is used by the PBAC PolicyEvaluator to identify group targets.
     */
    public function groups(): BelongsToMany
    {
        $userClass = config('pbac.user_model');
        $groupClass = PBACAccessGroup::class; // Use the PBAC Group model
        $pivotTable = config('pbac.table_names.group_user', 'pbac_group_user'); // Get pivot table name from config
        $foreignPivotKey = 'pbac_access_group_id'; // Foreign key for the group in the pivot table
        $relatedPivotKey = 'user_id'; // Foreign key for the user in the pivot table

        // Use the correct pivot table and foreign keys
        return $this->belongsToMany($groupClass, $pivotTable, $relatedPivotKey, $foreignPivotKey);
    }

    // You could add helper methods here related to group membership if needed,
    // e.g., isInPbacGroup(string|PBACAccessGroup $group)
}
