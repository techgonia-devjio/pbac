<?php

namespace Modules\Pbac\Tests\Unit\Models;


use Modules\Pbac\Models\PBACAccessControl;
use Modules\Pbac\Models\PBACAccessResource;
use Modules\Pbac\Tests\Support\Traits\MigrationLoader;
use Modules\Pbac\Tests\TestCase;

class PBACAccessResourceTest extends TestCase
{

    use MigrationLoader;
    #[\PHPUnit\Framework\Attributes\Test]
    public function it_can_be_created(): void
    {
        $resource = PBACAccessResource::create([
            'type' => 'App\Models\Blog',
            'description' => 'Blog posts resource',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('pbac_access_resources', [
            'type' => 'App\Models\Blog',
            'is_active' => true,
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_can_be_updated(): void
    {
        $resource = PBACAccessResource::factory()->create();
        $resource->update(['is_active' => false]);

        $this->assertDatabaseHas('pbac_access_resources', [
            'id' => $resource->id,
            'is_active' => false,
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_can_be_deleted(): void
    {
        $resource = PBACAccessResource::factory()->create();
        $resource->delete();

        $this->assertDatabaseMissing('pbac_access_resources', [
            'id' => $resource->id,
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_has_access_rules_relationship(): void
    {
        $resource = PBACAccessResource::factory()->create();
        $rule = PBACAccessControl::factory()->forResource($resource->type, 1)->create([
            'pbac_access_resource_id' => $resource->id,
        ]);

        $this->assertTrue($resource->accessRules->contains($rule));
        $this->assertCount(1, $resource->accessRules);
    }
}
