<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Character;
use App\Models\Resources;
use App\Models\Slot;
use App\Models\User;
use App\Services\InventoryResourcePlacementService;
use App\Services\InventoryService;
use App\Services\SpecialSlotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\InventoryPlacementTestHelper;
use Tests\TestCase;

class InventoryResourcePlacementServiceTest extends TestCase
{
    use RefreshDatabase;

    private InventoryResourcePlacementService $placementService;
    private InventoryService $inventoryService;
    private Character $character;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\DatabaseSeeder::class);

        $this->placementService = app(InventoryResourcePlacementService::class);
        $this->inventoryService = app(InventoryService::class);

        $user = User::where('email', 'test@example.com')->first();
        $this->character = $user->characters()->where('character_type', 'player')->first();
    }

    public function test_merges_partial_grid_stack_before_filling_typed_wood_slot(): void
    {
        $gridSlot = InventoryPlacementTestHelper::firstGridSlot($this->character);
        $woodSlot = InventoryPlacementTestHelper::addInventoryTypedSlot($this->character, 'wood');

        $partial = InventoryPlacementTestHelper::placeWood($this->character, 15, $gridSlot);

        $steps = $this->placementService->plan(
            InventoryPlacementTestHelper::inventory($this->character),
            'wood',
            20,
        );

        $this->assertCount(2, $steps);
        $this->assertEquals($partial->uuid, $steps[0]->mergeIntoResourceUuid);
        $this->assertEquals(5, $steps[0]->quantity);
        $this->assertEquals($gridSlot->uuid, $steps[0]->targetSlotUuid);

        $this->assertNull($steps[1]->mergeIntoResourceUuid);
        $this->assertEquals(15, $steps[1]->quantity);
        $this->assertEquals($woodSlot->uuid, $steps[1]->targetSlotUuid);
    }

    public function test_deposit_to_inventory_follows_same_placement_order(): void
    {
        $gridSlot = InventoryPlacementTestHelper::firstGridSlot($this->character);
        InventoryPlacementTestHelper::placeWood($this->character, 15, $gridSlot);
        $woodSlot = InventoryPlacementTestHelper::addInventoryTypedSlot($this->character, 'wood');

        $this->inventoryService->addResource($this->character, 'wood', 20);

        $partial = Resources::where('slot_uuid', $gridSlot->uuid)->firstOrFail();
        $typed = Resources::where('slot_uuid', $woodSlot->uuid)->firstOrFail();

        $this->assertEquals(20, $partial->quantity);
        $this->assertEquals(15, $typed->quantity);
        $this->assertEquals(35, $this->inventoryService->getResourceQuantity($this->character, 'wood'));
    }

    public function test_prefers_typed_empty_slot_over_empty_grid_slot_for_new_stack(): void
    {
        $woodSlot = InventoryPlacementTestHelper::addInventoryTypedSlot($this->character, 'wood');

        $steps = $this->placementService->plan(
            InventoryPlacementTestHelper::inventory($this->character),
            'wood',
            10,
        );

        $this->assertCount(1, $steps);
        $this->assertEquals($woodSlot->uuid, $steps[0]->targetSlotUuid);
        $this->assertEquals(10, $steps[0]->quantity);
    }

    public function test_gold_deposits_into_gold_special_slot_without_using_grid(): void
    {
        $inventory = InventoryPlacementTestHelper::inventory($this->character);
        $goldSlot = app(SpecialSlotService::class)->getGoldSlot($inventory);
        $this->assertNotNull($goldSlot);

        $steps = $this->placementService->plan($inventory, 'gold', 100);

        $this->assertCount(1, $steps);
        $this->assertEquals($goldSlot->uuid, $steps[0]->targetSlotUuid);
        $this->assertNotNull($steps[0]->mergeIntoResourceUuid);
    }

    public function test_calculate_capacity_counts_typed_slots_and_partial_stacks(): void
    {
        $gridSlot = InventoryPlacementTestHelper::firstGridSlot($this->character);
        InventoryPlacementTestHelper::placeWood($this->character, 15, $gridSlot);
        InventoryPlacementTestHelper::addInventoryTypedSlot($this->character, 'wood');

        $capacity = $this->placementService->calculateCapacity(
            InventoryPlacementTestHelper::inventory($this->character),
            'wood',
        );

        // 5 в partial + 20 в typed + 35*20 в пустых grid
        $emptyGrid = app(SpecialSlotService::class)
            ->getGridSlots(InventoryPlacementTestHelper::inventory($this->character))
            ->filter(fn (Slot $slot) => $slot->uuid !== $gridSlot->uuid)
            ->count();

        $this->assertEquals(5 + 20 + ($emptyGrid * 20), $capacity);
    }

    public function test_plan_fails_when_not_enough_room(): void
    {
        $inventory = InventoryPlacementTestHelper::inventory($this->character);
        $gridSlots = app(SpecialSlotService::class)->getGridSlots($inventory);

        foreach ($gridSlots as $slot) {
            InventoryPlacementTestHelper::placeWood($this->character, 20, $slot);
        }

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Недостаточно места в инвентаре для размещения ресурса');
        $this->placementService->plan($inventory, 'wood', 1);
    }

    public function test_reserved_grid_slots_are_not_reused_in_same_batch(): void
    {
        $inventory = InventoryPlacementTestHelper::inventory($this->character);
        $freedSlot = InventoryPlacementTestHelper::firstGridSlot($this->character);

        $steps = $this->placementService->plan(
            $inventory,
            'wood',
            20,
            prependEmptyGridSlotUuids: [$freedSlot->uuid],
            reservedSlotUuids: [$freedSlot->uuid],
        );

        $this->assertFalse(collect($steps)->contains(fn ($step) => $step->targetSlotUuid === $freedSlot->uuid));
    }

    public function test_can_fit_matches_plan_result(): void
    {
        $inventory = InventoryPlacementTestHelper::inventory($this->character);

        $this->assertTrue($this->placementService->canFit($inventory, 'wood', 20));
        $this->assertFalse($this->placementService->canFit($inventory, 'wood', 20_000));
    }

    public function test_does_not_place_into_backing_slot_with_station_overlay(): void
    {
        $gridSlot = InventoryPlacementTestHelper::firstGridSlot($this->character);
        $blueprint = app(\App\Services\CraftingService::class)->createBlueprint($this->character, 'craft_wooden_sword');
        $blueprint->update([
            'slot_uuid' => $gridSlot->uuid,
            'buffer_slot_uuid' => app(\App\Services\CraftStationService::class)
                ->getCenterTemporarySlot($this->character)->uuid,
        ]);

        $this->inventoryService->addResource($this->character, 'wood', 10);

        $this->assertEquals(
            0,
            Resources::where('slot_uuid', $gridSlot->uuid)->count()
        );
    }
}
