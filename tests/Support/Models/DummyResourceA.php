<?php

namespace Pbac\Tests\Support\Models;


class DummyResourceA extends \Illuminate\Database\Eloquent\Model
{
    protected $table = 'dummy_resource_a';
    protected $guarded = [];
    public $timestamps = false; // No timestamps for simplicity in test

    protected static function booted()
    {
        // Ensure dummy_posts table exists for this test
        if (!\Illuminate\Support\Facades\Schema::hasTable('dummy_resource_a')) {
            \Illuminate\Support\Facades\Schema::create('dummy_resource_a', function ($table) {
                $table->id();
                $table->string('title');
            });
        }
    }
}
