<?php

namespace Tests\Unit;

use App\Models\ItemInstance;
use App\Models\ItemTemplate;
use App\Models\User;
use App\Services\InventoryService;
use Tests\TestCase;

class InventoryServiceTest extends TestCase
{
    private InventoryService $service;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(InventoryService::class);
        $this->user = User::factory()->create(['gold' => 1000]);
    }

    public function test_add_item_creates_new_instance(): void
    {
        $template = ItemTemplate::factory()->material()->create(['name' => 'Дерево', 'max_stack' => 200]);

        $item = $this->service->addItem($this->user->id, $template->id, 10);

        $this->assertDatabaseHas('item_instances', [
            'owner_id' => $this->user->id,
            'template_id' => $template->id,
            'quantity' => 10,
        ]);
        $this->assertEquals(10, $item->quantity);
    }

    public function test_add_item_stacks_to_existing(): void
    {
        $template = ItemTemplate::factory()->material()->create(['max_stack' => 200]);
        ItemInstance::factory()->create([
            'owner_id' => $this->user->id,
            'template_id' => $template->id,
            'quantity' => 50,
        ]);

        $this->service->addItem($this->user->id, $template->id, 30);

        // Проверяем только записи для этого пользователя и шаблона
        $instances = ItemInstance::where('owner_id', $this->user->id)
            ->where('template_id', $template->id)
            ->get();

        $this->assertCount(1, $instances);
        $this->assertEquals(80, $instances->first()->quantity);
    }

    public function test_add_item_respects_max_stack(): void
    {
        $template = ItemTemplate::factory()->material()->create(['max_stack' => 100]);
        ItemInstance::factory()->create([
            'owner_id' => $this->user->id,
            'template_id' => $template->id,
            'quantity' => 80,
        ]);

        $this->service->addItem($this->user->id, $template->id, 50);

        $instances = ItemInstance::where('owner_id', $this->user->id)->get();
        $this->assertCount(2, $instances);
        $this->assertEquals(130, $instances->sum('quantity'));
    }

    public function test_remove_item_decrements_stack(): void
    {
        $template = ItemTemplate::factory()->material()->create();
        $item = ItemInstance::factory()->create([
            'owner_id' => $this->user->id,
            'template_id' => $template->id,
            'quantity' => 50,
        ]);

        $this->service->removeItem($this->user->id, $item->id, 20);

        $this->assertDatabaseHas('item_instances', ['id' => $item->id, 'quantity' => 30]);
    }

    public function test_remove_item_deletes_when_zero(): void
    {
        $template = ItemTemplate::factory()->material()->create();
        $item = ItemInstance::factory()->create([
            'owner_id' => $this->user->id,
            'template_id' => $template->id,
            'quantity' => 10,
        ]);

        $this->service->removeItem($this->user->id, $item->id, 10);

        $this->assertDatabaseMissing('item_instances', ['id' => $item->id]);
    }

    public function test_remove_item_fails_for_other_user(): void
    {
        $otherUser = User::factory()->create();
        $template = ItemTemplate::factory()->material()->create();
        $item = ItemInstance::factory()->create([
            'owner_id' => $otherUser->id,
            'template_id' => $template->id,
            'quantity' => 10,
        ]);

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        $this->service->removeItem($this->user->id, $item->id, 5);
    }
}
