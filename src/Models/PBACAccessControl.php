<?php

namespace Modules\Pbac\Models;

use Illuminate\Database\Eloquent\Model;

class PBACAccessControl extends Model
{
    use \Illuminate\Database\Eloquent\Factories\HasFactory;
    protected $table = 'pbac_accesses';

    protected $fillable = [
        'pbac_access_target_id',
        'target_id',
        'pbac_access_resource_id',
        'resource_id',
        'action',
        'effect',
        'extras',
        'priority',
    ];

    protected $casts = [
        'action' => 'array', // json
        'extras' => 'array', // json
        'target_id' => 'integer',
        'resource_id' => 'integer',
    ];

    public function targetType()
    {
        return $this->belongsTo(PBACAccessTarget::class, 'pbac_access_target_id');
    }

    public function resourceType()
    {
        return $this->belongsTo(PBACAccessResource::class, 'pbac_access_resource_id');
    }

    public function targetInstance()
    {
        $this->loadMissing('targetType');

        if ($this->targetType && $this->target_id !== null) {
            $targetModelClass = $this->targetType->type;
            // Check if the target_type is a valid model class before attempting relationship
            if (class_exists($targetModelClass) && is_subclass_of($targetModelClass, \Illuminate\Database\Eloquent\Model::class)) {
                 return $this->belongsTo($targetModelClass, 'target_id');
            }
        }
        return null; // Rule applies to any instance of target_type or target_type is not a model or target_id is null
    }

    public function resourceInstance()
    {
         // Load the resourceType relationship to get the actual class name
         $this->loadMissing('resourceType');

         if ($this->resourceType && $this->resource_id !== null) {
             $resourceModelClass = $this->resourceType->type;
             // Check if the resource_type is a valid model class before attempting relationship
             if (class_exists($resourceModelClass) && is_subclass_of($resourceModelClass, \Illuminate\Database\Eloquent\Model::class)) {
                return $this->belongsTo($resourceModelClass, 'resource_id');
             }
        }
        return null; // Rule applies to any instance of resource_type or resource_type is not a model or resource_id is null
    }
}
