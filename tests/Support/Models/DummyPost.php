<?php

namespace Modules\Pbac\Tests\Support\Models;


// Define a dummy Post model for resource/target instance tests
class DummyPost extends \Illuminate\Database\Eloquent\Model
{
    protected $table = 'dummy_posts';
    protected $guarded = [];
    public $timestamps = false; // No timestamps for simplicity in test

    protected static function booted()
    {
        // Ensure dummy_posts table exists for this test
        if (!\Illuminate\Support\Facades\Schema::hasTable('dummy_posts')) {
            \Illuminate\Support\Facades\Schema::create('dummy_posts', function ($table) {
                $table->id();
                $table->string('title');
            });
        }
    }
}
