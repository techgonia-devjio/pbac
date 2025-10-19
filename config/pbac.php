<?php


return [

    'user_model' => \App\Models\User::class,

    /*
    |--------------------------------------------------------------------------
    | Super Admin Attribute
    |--------------------------------------------------------------------------
    |
    | This is the name of the boolean attribute on your User model that, if true,
    | will grant the user full bypass access via a Gate::before check.
    | Set to null to disable this bypass via attribute.
    |
    */
    'super_admin_attribute' => 'is_super_admin', // Example: Add a boolean column 'is_super_admin' to your users table

    /*≤≤
    |--------------------------------------------------------------------------
    | Strict Resource Registration
    |--------------------------------------------------------------------------
    |
    | If true, access will be denied immediately if a resource type passed to
    | the PolicyEvaluator is not registered in the 'pbac_access_resources' table
    | or is marked as inactive.
    |
    | If false, if a resource type is not found or is inactive, the evaluation
    | will proceed as if no specific resource was provided (i.e., only rules
    | applying to *any* resource type will be considered for that resource).
    |
    */
    'strict_resource_registration' => false,

    /*
    | --------------------------------------------------------------------------
    | Strict Target Registration
    | --------------------------------------------------------------------------
    |
    | If true, access will be denied immediately if a target type passed to
    | the PolicyEvaluator is not registered in the 'pbac_access_targets' table
    | or is marked as inactive.
    |
    | If false, if a target type is not found or is inactive, the evaluation
    | will proceed as if no specific target was provided (i.e., only rules
    | applying to *any* target type will be considered for that target).
    |
    */
    'strict_target_registration' => false,

    'traits' => [
        'groups' => \Modules\Pbac\Traits\HasPbacGroups::class,
        'teams' => \Modules\Pbac\Traits\HasPbacTeams::class,
        'access_control' => \Modules\Pbac\Traits\HasPbacAccessControl::class
    ],

    'models' => [
        'access_control' => \Modules\Pbac\Models\PBACAccessControl::class,
        'access_resource' => \Modules\Pbac\Models\PBACAccessResource::class,
        'access_target' => \Modules\Pbac\Models\PBACAccessTarget::class,
        'access_group' => \Modules\Pbac\Models\PBACAccessGroup::class,
        'access_team' => \Modules\Pbac\Models\PBACAccessTeam::class,
    ],

    'supported_actions' => [
        'view',
        'viewAny',
        'create',
        'update',
        'delete',
        'restore',
        'forceDelete',
        'publish',
        'archive',
        // Add more actions as needed for your application...
    ],

    'cache' => [
        'enabled' => env('PBAC_CACHE_ENABLED', true),
        'ttl' => env('PBAC_CACHE_TTL', 60 * 24), // Cache duration in seconds (e.g., 24 hours)
        'key_prefix' => 'pbac:', // Prefix for cache keys
    ],

    'logging' => [
        'enabled' => env('PBAC_LOGGING_ENABLED', true),
        'channel' => env('PBAC_LOGGING_CHANNEL', 'stderr'), // Log channel to use (null for default)
        'level'   => env('LOG_LEVEL', 'warning'), // Add this line
    ]

];
