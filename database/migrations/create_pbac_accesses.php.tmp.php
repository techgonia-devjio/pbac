<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        Schema::create('pbac_accesses', function (Blueprint $table) {
            $table->id();

            $table->foreignIdFor(\Modules\Pbac\Models\PBACAccessTarget::class, 'pbac_access_target_id')
                ->nullable()
                ->constrained()
                ->onDelete('cascade');

            $table->unsignedBigInteger('target_id')
                ->nullable();

            $table->foreignIdFor(\Modules\Pbac\Models\PBACAccessResource::class, 'pbac_access_resource_id')
                ->nullable()
                ->constrained()
                ->onDelete('cascade');

            $table
                ->unsignedBigInteger('resource_id')
                ->nullable(); //  Null if rule applies to *any* resource of that type...

            $table->json('action')
                ->nullable();  // e.g., 'view', 'create', 'update', 'delete', 'manage', etc.
            $table->enum('effect', ['allow', 'deny'])
                ->default('allow'); // 'allow' or 'deny'
            $table->json('extras')->nullable(); // JSON object for some other data
            $table->integer('priority')->default(0); // Higher priority rules evaluated first (useful for deny overrides)
            $table->timestamps();

            // Add indexes for performance
            $table->index(['pbac_access_target_id', 'target_id']);
            $table->index(['pbac_access_resource_id', 'resource_id']);
            $table->index('action');
            $table->index(['effect', 'priority']);

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pbac_accesses');
    }
};
