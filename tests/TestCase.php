<?php

namespace Modules\Pbac\Tests;

use Illuminate\Support\Facades\Config;
use Modules\Pbac\Tests\Support\Models\TestUser;
use PHPUnit\Framework\TestCase  as BaseTestCase;

abstract class TestCase extends BaseTestCase
{

    protected function setUp(): void
    {
        parent::setUp();
        // Set the user model for the PBAC package config
        Config::set('pbac.user_model', TestUser::class);
        Config::set('pbac.super_admin_attribute', 'is_super_admin');
        //Config::set('pbac.logging.enabled', true); // Disable logging during tests unless specifically needed
    }

}

