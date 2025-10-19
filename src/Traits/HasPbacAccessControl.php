<?php

namespace Modules\Pbac\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
use Modules\Pbac\Services\PolicyEvaluator; // Import the service


trait HasPbacAccessControl
{

    /**
     *  Determine if the user has the given ability (action) on a resource.
     *  This method delegates the check to the PolicyEvaluator service.
     *
     * @param  string  $ability  The ability name (actions such as view, edit, etc.)
     * @param  array|string|Model|null  $arguments  The resource as model or just model string in case of any record of model
     * @return bool
     */
    public function can($ability, $arguments = []): bool
    {
        $superAdminAttribute = Config::get('pbac.super_admin_attribute');
        if ($superAdminAttribute && $this->{$superAdminAttribute} ?? false) {
            return true;
        }

        $resource = null;
        $context = null;
        if ($arguments instanceof Model) {
            $resource = $arguments;
            $context = null;
        } elseif (is_array($arguments)) {
            // Check if array has named keys for 'resource' and/or 'context'
            if (array_key_exists('resource', $arguments) || array_key_exists('context', $arguments)) {
                $resource = $arguments['resource'] ?? null;
                $context = $arguments['context'] ?? null;
            }
            // Check if positional array with Model at index 0
            elseif (isset($arguments[0]) && $arguments[0] instanceof Model) {
                $resource = $arguments[0];
                $context = $arguments[1] ?? null;
            }
            // Check if positional array with string (class name) at index 0
            elseif (isset($arguments[0]) && is_string($arguments[0])) {
                $resource = $arguments[0];
                $context = $arguments[1] ?? null;
            }
            // Otherwise treat the whole array as context
            else {
                $resource = null;
                $context = $arguments;
            }
        } elseif (is_string($arguments)) {
            $resource = $arguments;
            $context = null;
        } else {
            $resource = null;
            $context = $arguments; // Fallback: treat anything else as context
        }

        // Ensure context is always an array if not null, otherwise normalize scalar to an array
        if (is_null($context)) {
            $context = [];
        } elseif (!is_array($context) && !is_object($context)) {
            // Wrap scalar (like a string or int) into an array for consistent handling in PolicyEvaluator
            $context = ['_value' => $context];
        }


        return app(PolicyEvaluator::class)->evaluate($this, $ability, $resource, $context);
    }


}

