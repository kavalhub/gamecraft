<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Character;
use App\Models\CharacterQuest;
use App\Models\Item;
use App\Models\Resources;
use App\Models\Slot;
use App\Models\Storage;
use App\Models\User;
use App\Services\CraftingService;
use App\Services\InventoryService;
use App\Services\QuestService;
use App\Services\QuestStorageService;
use App\Services\SpecialSlotService;
use App\Services\WorldStorageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\Support\DisassembleStationHelper;
use Tests\Support\WorkbenchHelper;
use Tests\TestCase;

class QuestServiceTest extends TestCase
{
    use RefreshDatabase;

    private QuestService $questService;
    private InventoryService $inventoryService;
    private CraftingService $craftingService;
    private Character $character;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\DatabaseSeeder::class);

        $this->questService = app(QuestService::class);
        $this->inventoryService = app(InventoryService::class);
        $this->craftingService = app(CraftingService::class);

        $user = User::where('email', 'test@example.com')->first();
        $this->character = $user->characters()->where('character_type', 'player')->first();
    }

    public function test_import_and_list_available_quests(): void
    {
        $quests = $this->questService->listForCharacter($this->character);

        $this->assertNotEmpty($quests['available']);
        $this->assertContains('tutorial_planks', array_column($quests['available'], 'slug'));
        $this->assertNotContains('first_wooden_sword', array_column($quests['available'], 'slug'));

        $this->grantQuestStarter();
        $quests = $this->questService->listForCharacter($this->character);
        $this->assertContains('first_wooden_sword', array_column($quests['available'], 'slug'));
    }

    public function test_accept_autoloads_grants_to_inventory(): void
    {
        $this->grantQuestStarter();
        $this->questService->accept($this->character, 'first_wooden_sword');

        $this->assertTrue(
            Item::where('template_slug', 'recipe_wooden_sword')
                ->whereIn('slot_uuid', $this->inventorySlotUuids())
                ->whereNull('temporary_slot_uuid')
                ->exists()
        );

        $this->assertGreaterThanOrEqual(5, $this->inventoryService->getResourceQuantity($this->character, 'wood'));

        $grantSlots = app(QuestStorageService::class)->getGrantTemporarySlots($this->character, 'first_wooden_sword');
        $this->assertCount(0, $grantSlots);
    }

    public function test_accept_and_complete_resource_quest_via_craft(): void
    {
        $this->questService->accept($this->character, 'tutorial_planks');

        $this->inventoryService->addResource($this->character, 'wood', 2);
        DisassembleStationHelper::placeResource($this->character, 'wood', 1);
        $this->craftingService->disassembleResource($this->character, 'wood');

        $quests = $this->questService->listForCharacter($this->character);
        $this->assertCount(1, $quests['active']);
        $this->assertTrue($quests['active'][0]['ready_to_turn_in']);
        $this->assertEquals('tutorial_planks', $quests['active'][0]['slug']);
    }

    public function test_turn_in_grants_rewards_to_inventory(): void
    {
        $this->questService->accept($this->character, 'tutorial_planks');
        $this->inventoryService->addResource($this->character, 'wood', 2);
        DisassembleStationHelper::placeResource($this->character, 'wood', 1);
        $this->craftingService->disassembleResource($this->character, 'wood');

        $woodBefore = $this->inventoryService->getResourceQuantity($this->character, 'wood');
        $this->questService->turnIn($this->character, 'tutorial_planks');
        $woodAfter = $this->inventoryService->getResourceQuantity($this->character, 'wood');

        $this->assertEquals($woodBefore + 5, $woodAfter);

        $state = CharacterQuest::where('character_uuid', $this->character->uuid)
            ->where('quest_slug', 'tutorial_planks')
            ->first();
        $this->assertEquals(CharacterQuest::STATUS_TURNED_IN, $state->status);

        $quests = $this->questService->listForCharacter($this->character);
        $this->assertContains('tutorial_planks', array_column($quests['finished'], 'slug'));
    }

    public function test_have_item_objective_tracks_inventory(): void
    {
        $this->grantQuestStarter();
        $this->questService->accept($this->character, 'first_wooden_sword');
        $this->inventoryService->addItem($this->character, 'wooden_sword', 'item', null, 'craft_wooden_sword');

        $quests = $this->questService->listForCharacter($this->character);
        $active = collect($quests['active'])->firstWhere('slug', 'first_wooden_sword');
        $this->assertNotNull($active);
        $this->assertTrue($active['ready_to_turn_in']);
        $this->assertEquals(1, $active['objectives'][0]['current']);
    }

    public function test_turn_in_deposits_crafted_item_to_world_and_grants_experience(): void
    {
        $sword = $this->inventoryService->addItem($this->character, 'wooden_sword', 'item', null, 'craft_wooden_sword');
        $this->grantQuestStarter();
        $this->questService->accept($this->character, 'first_wooden_sword');

        $goldBefore = $this->inventoryService->getResourceQuantity($this->character, 'gold');
        $xpBefore = app(SpecialSlotService::class)->getExperienceQuantity($this->character);
        $this->questService->turnIn($this->character, 'first_wooden_sword');

        $this->assertEquals($goldBefore + 5, $this->inventoryService->getResourceQuantity($this->character, 'gold'));
        $this->assertEquals($xpBefore + 10, app(SpecialSlotService::class)->getExperienceQuantity($this->character));

        $inventory = $this->character->storages()->where('storage_type', 'inventory')->first();
        $gridUuids = app(SpecialSlotService::class)->getGridSlots($inventory)->pluck('uuid');
        $this->assertFalse(
            Resources::whereIn('slot_uuid', $gridUuids)->where('template_slug', 'experience')->exists()
        );

        $worldStorage = app(WorldStorageService::class)->ensureWorldStorage();
        $worldSlotUuids = $worldStorage->slots()->pluck('uuid');

        $this->assertTrue(
            Item::where('uuid', $sword->uuid)
                ->whereIn('slot_uuid', $worldSlotUuids)
                ->exists()
        );
    }

    public function test_prerequisite_quest_unlocks_next(): void
    {
        $quests = $this->questService->listForCharacter($this->character);
        $this->assertNotContains('tutorial_sword', array_column($quests['available'], 'slug'));

        $this->questService->accept($this->character, 'tutorial_planks');
        $this->inventoryService->addResource($this->character, 'wood', 2);
        DisassembleStationHelper::placeResource($this->character, 'wood', 1);
        $this->craftingService->disassembleResource($this->character, 'wood');
        $this->questService->turnIn($this->character, 'tutorial_planks');

        $quests = $this->questService->listForCharacter($this->character);
        $this->assertContains('tutorial_sword', array_column($quests['available'], 'slug'));
    }

    public function test_collect_resource_quest_tracks_inventory(): void
    {
        $this->inventoryService->addResource($this->character, 'wood', 20);
        $this->questService->accept($this->character, 'stock_wood');

        $quests = $this->questService->listForCharacter($this->character);
        $active = collect($quests['active'])->firstWhere('slug', 'stock_wood');
        $this->assertNotNull($active);
        $this->assertTrue($active['ready_to_turn_in']);
        $this->assertEquals(20, $active['objectives'][0]['current']);
        $this->assertCount(1, $quests['active']);
    }

    public function test_quests_json_exists(): void
    {
        $this->assertTrue(File::exists(base_path('content/quests.json')));
    }

    private function grantQuestStarter(): void
    {
        $this->inventoryService->addItem(
            $this->character,
            'quest_first_wooden_sword',
            'item',
            null,
            'quest_item_stub'
        );
    }

    private function inventorySlotUuids()
    {
        $storageUuids = Storage::where('characters_uuid', $this->character->uuid)
            ->where('storage_type', 'inventory')
            ->pluck('uuid');

        return Slot::whereIn('storage_uuid', $storageUuids)->pluck('uuid');
    }
}
