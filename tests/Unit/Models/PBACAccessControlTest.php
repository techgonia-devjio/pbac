<?php

namespace Modules\Pbac\Tests\Unit\Models;


use Modules\Pbac\Models\PBACAccessControl;
use Modules\Pbac\Models\PBACAccessResource;
use Modules\Pbac\Models\PBACAccessTarget;
use Modules\Pbac\Tests\Support\Models\DummyPost;
use Modules\Pbac\Tests\Support\Models\TestUser;
use Modules\Pbac\Tests\TestCase;


class PBACAccessControlTest extends TestCase
{

    use \Modules\Pbac\Tests\Support\Traits\MigrationLoader;


    protected function tearDown(): void
    {
        // Drop the dummy_posts table after tests
        (new DummyPost())->getConnection()->getSchemaBuilder()->dropIfExists('dummy_posts');
        parent::tearDown();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_can_be_created(): void
    {
        $targetType = PBACAccessTarget::factory()->create(['type' => TestUser::class]);
        $resourceType = PBACAccessResource::factory()->create(['type' => DummyPost::class]);

        $rule = PBACAccessControl::create([
            'pbac_access_target_id' => $targetType->id,
            'target_id' => 1,
            'pbac_access_resource_id' => $resourceType->id,
            'resource_id' => 10,
            'action' => ['view'],
            'effect' => 'allow',
            'extras' => ['foo' => 'bar'],
            'priority' => 10,
        ]);

        $this->assertDatabaseHas('pbac_accesses', [
            'pbac_access_target_id' => $targetType->id,
            'target_id' => 1,
            'pbac_access_resource_id' => $resourceType->id,
            'resource_id' => 10,
            'action' => json_encode(['view']),
            'effect' => 'allow',
            'extras' => json_encode(['foo' => 'bar']),
            'priority' => 10,
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_can_be_updated(): void
    {
        $rule = PBACAccessControl::factory()->create();
        $rule->update(['effect' => 'deny', 'priority' => 5]);

        $this->assertDatabaseHas('pbac_accesses', [
            'id' => $rule->id,
            'effect' => 'deny',
            'priority' => 5,
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_can_be_deleted(): void
    {
        $rule = PBACAccessControl::factory()->create();
        $rule->delete();

        $this->assertDatabaseMissing('pbac_accesses', [
            'id' => $rule->id,
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_has_target_type_relationship(): void
    {
        $targetType = PBACAccessTarget::factory()->create(['type' => TestUser::class]);
        $rule = PBACAccessControl::factory()->create(['pbac_access_target_id' => $targetType->id]);

        $this->assertTrue($rule->targetType->is($targetType));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_has_resource_type_relationship(): void
    {
        $resourceType = PBACAccessResource::factory()->create(['type' => DummyPost::class]);
        $rule = PBACAccessControl::factory()->create(['pbac_access_resource_id' => $resourceType->id]);

        $this->assertTrue($rule->resourceType->is($resourceType));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function target_instance_relationship_returns_model_when_target_id_is_set(): void
    {
        $user = TestUser::factory()->create();
        $targetType = PBACAccessTarget::factory()->create(['type' => TestUser::class]);

        $rule = PBACAccessControl::factory()->create([
            'pbac_access_target_id' => $targetType->id,
            'target_id' => $user->id,
        ]);

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class, $rule->targetInstance());
        $this->assertTrue($rule->targetInstance()->getResults()->is($user));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function target_instance_relationship_returns_null_when_target_id_is_null(): void
    {
        $targetType = PBACAccessTarget::factory()->create(['type' => TestUser::class]);

        $rule = PBACAccessControl::factory()->create([
            'pbac_access_target_id' => $targetType->id,
            'target_id' => null, // Rule applies to any user
        ]);

        $this->assertNull($rule->targetInstance());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function target_instance_relationship_returns_null_when_target_type_is_not_a_model(): void
    {
        $targetType = PBACAccessTarget::factory()->create(['type' => 'NonExistentClass']); // Not a model
        $rule = PBACAccessControl::factory()->create([
            'pbac_access_target_id' => $targetType->id,
            'target_id' => 1,
        ]);

        $this->assertNull($rule->targetInstance());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function resource_instance_relationship_returns_model_when_resource_id_is_set(): void
    {
        $post = DummyPost::create(['title' => 'Test Post']);
        $resourceType = PBACAccessResource::factory()->create(['type' => DummyPost::class]);

        $rule = PBACAccessControl::factory()->create([
            'pbac_access_resource_id' => $resourceType->id,
            'resource_id' => $post->id,
        ]);

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class, $rule->resourceInstance());
        $this->assertTrue($rule->resourceInstance()->getResults()->is($post));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function resource_instance_relationship_returns_null_when_resource_id_is_null(): void
    {
        $resourceType = PBACAccessResource::factory()->create(['type' => DummyPost::class]);

        $rule = PBACAccessControl::factory()->create([
            'pbac_access_resource_id' => $resourceType->id,
            'resource_id' => null, // Rule applies to any post
        ]);

        $this->assertNull($rule->resourceInstance());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function resource_instance_relationship_returns_null_when_resource_type_is_not_a_model(): void
    {
        $resourceType = PBACAccessResource::factory()->create(['type' => 'NonExistentResourceClass']); // Not a model
        $rule = PBACAccessControl::factory()->create([
            'pbac_access_resource_id' => $resourceType->id,
            'resource_id' => 1,
        ]);

        $this->assertNull($rule->resourceInstance());
    }
}
