<?php

namespace Modules\Pbac\Tests\Unit\Models;

use Modules\Pbac\Models\PBACAccessControl;
use Modules\Pbac\Models\PBACAccessTarget;
use Modules\Pbac\Tests\TestCase;

class PBACAccessTargetTest extends TestCase
{
    use \Modules\Pbac\Tests\Support\Traits\MigrationLoader;

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_can_be_created(): void
    {
        $target = PBACAccessTarget::create([
            'type' => 'App\Models\User',
            'description' => 'User target type',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('pbac_access_targets', [ // Note: table name is 'access_targets'
            'type' => 'App\Models\User',
            'is_active' => true,
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_can_be_updated(): void
    {
        $target = PBACAccessTarget::factory()->create();
        $target->update(['is_active' => false]);

        $this->assertDatabaseHas('pbac_access_targets', [
            'id' => $target->id,
            'is_active' => false,
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_can_be_deleted(): void
    {
        $target = PBACAccessTarget::factory()->create();
        $target->delete();

        $this->assertDatabaseMissing('pbac_access_targets', [
            'id' => $target->id,
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_has_access_rules_relationship(): void
    {
        $target = PBACAccessTarget::factory()->create();
        $rule = PBACAccessControl::factory()->forTarget($target->type, 1)->create([
            'pbac_access_target_id' => $target->id,
        ]);

        $this->assertTrue($target->accessRules->contains($rule));
        $this->assertCount(1, $target->accessRules);
    }
}
