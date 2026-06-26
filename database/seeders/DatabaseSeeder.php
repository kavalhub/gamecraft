<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Character;
use App\Models\CharacterType;
use App\Models\Resource;
use App\Models\Slot;
use App\Models\Storage;
use App\Models\StorageType;
use App\Models\User;
use App\Services\ContentImportService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedCharacterTypes();
        $this->seedStorageTypes();

        $importService = app(ContentImportService::class);
        $baseContentPath = base_path('content/base.json');
        
        if (File::exists($baseContentPath)) {
            $data = json_decode(File::get($baseContentPath), true);
            $report = $importService->import($data);

            $this->command->info('Content imported:');
            $this->command->info("  Slot types: {$report['slot_types']['created']} created, {$report['slot_types']['updated']} updated");
            $this->command->info("  Templates: {$report['templates']['created']} created, {$report['templates']['updated']} updated");
            $this->command->info("  Recipes: {$report['recipes']['created']} created, {$report['recipes']['updated']} updated");
            $this->command->info("  Formulas: {$report['formulas']['created']} created");
            $this->command->info("  Characters: {$report['characters']['created']} created, {$report['characters']['updated']} updated");
            $this->command->info("  Shop lots: {$report['shop_lots']['created']} created, {$report['shop_lots']['updated']} updated");
        } else {
            $this->command->warn('content/base.json not found');
        }

        $this->createAuctionCharacter();
        $this->createSystemCharacter();
        $this->createTestUser();
    }

    private function seedCharacterTypes(): void
    {
        $types = [
            ['type' => 'player', 'name' => 'Персонаж', 'parent_type' => null],
            ['type' => 'npc', 'name' => 'Неигровой персонаж', 'parent_type' => null],
            ['type' => 'npc_merchant', 'name' => 'Торговец', 'parent_type' => 'npc'],
            ['type' => 'npc_quest_giver', 'name' => 'Квестодатель', 'parent_type' => 'npc'],
            ['type' => 'auction', 'name' => 'Аукцион', 'parent_type' => null],
            ['type' => 'guild', 'name' => 'Гильдия', 'parent_type' => null],
            ['type' => 'alliance', 'name' => 'Альянс', 'parent_type' => null],
            ['type' => 'location', 'name' => 'Локация', 'parent_type' => null],
            ['type' => 'chest', 'name' => 'Сундук', 'parent_type' => null],
            ['type' => 'post', 'name' => 'Почта', 'parent_type' => null],
            ['type' => 'system', 'name' => 'Система', 'parent_type' => null],
        ];

        foreach ($types as $type) {
            CharacterType::updateOrCreate(['type' => $type['type']], $type);
        }
    }

    private function seedStorageTypes(): void
    {
        $types = [
            ['type' => 'inventory', 'name' => 'Инвентарь', 'allowed_types' => ['slots' => [['slot_type' => null, 'count' => 50]]]],
            ['type' => 'equipment', 'name' => 'Экипировка', 'allowed_types' => ['slots' => [
                ['slot_type' => 'equipment_head', 'count' => 1],
                ['slot_type' => 'equipment_chest', 'count' => 1],
                ['slot_type' => 'equipment_legs', 'count' => 1],
                ['slot_type' => 'equipment_weapon', 'count' => 1],
                ['slot_type' => 'equipment_offhand', 'count' => 1],
                ['slot_type' => 'equipment_ring', 'count' => 2],
                ['slot_type' => 'equipment_amulet', 'count' => 1],
            ]]],
            ['type' => 'bank', 'name' => 'Банк', 'allowed_types' => ['slots' => [['slot_type' => null, 'count' => 100]]]],
            ['type' => 'auction', 'name' => 'Аукцион', 'allowed_types' => null],
            ['type' => 'trade', 'name' => 'Обмен', 'allowed_types' => null],
            ['type' => 'special', 'name' => 'Специальное', 'allowed_types' => null],
            ['type' => 'world', 'name' => 'Мир', 'allowed_types' => null],
            ['type' => 'mail', 'name' => 'Почта', 'allowed_types' => ['slots' => [['slot_type' => null, 'count' => 100]]]],
        ];

        foreach ($types as $type) {
            StorageType::updateOrCreate(['type' => $type['type']], $type);
        }
    }

    private function createAuctionCharacter(): void
    {
        $auction = Character::firstOrCreate(
            ['character_type' => 'auction', 'name' => 'Городской аукцион'],
            ['active' => true]
        );

        Storage::firstOrCreate(
            ['characters_uuid' => $auction->uuid, 'storage_type' => 'auction'],
            ['name' => 'Аукцион', 'active' => true]
        );

        $this->command->info("Auction character created: {$auction->name} ({$auction->uuid})");
    }

    private function createSystemCharacter(): void
    {
        $system = Character::firstOrCreate(
            ['character_type' => 'system', 'name' => 'System'],
            ['active' => true]
        );

        Storage::firstOrCreate(
            ['characters_uuid' => $system->uuid, 'storage_type' => 'trade'],
            ['name' => 'Обмен', 'active' => true]
        );

        $this->command->info("System character created: {$system->name} ({$system->uuid})");
    }

    private function createTestUser(): void
    {
        $user = User::firstOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'password' => bcrypt('password'),
            ]
        );

        $character = Character::firstOrCreate(
            ['user_uuid' => $user->uuid, 'character_type' => 'player', 'name' => 'Test Character'],
            ['active' => true]
        );

        $inventory = Storage::firstOrCreate(
            ['characters_uuid' => $character->uuid, 'storage_type' => 'inventory'],
            ['name' => 'Инвентарь', 'active' => true]
        );

        $equipment = Storage::firstOrCreate(
            ['characters_uuid' => $character->uuid, 'storage_type' => 'equipment'],
            ['name' => 'Экипировка', 'active' => true]
        );

        $bank = Storage::firstOrCreate(
            ['characters_uuid' => $character->uuid, 'storage_type' => 'bank'],
            ['name' => 'Банк', 'active' => true]
        );

        if ($inventory->slots()->count() === 0) {
            for ($i = 0; $i < 50; $i++) {
                Slot::create(['storage_uuid' => $inventory->uuid, 'slot_type' => null]);
            }
        }

        if ($equipment->slots()->count() === 0) {
            $slotTypes = ['equipment_head', 'equipment_chest', 'equipment_legs', 'equipment_weapon', 'equipment_offhand', 'equipment_ring', 'equipment_ring', 'equipment_amulet'];
            foreach ($slotTypes as $slotType) {
                Slot::create(['storage_uuid' => $equipment->uuid, 'slot_type' => $slotType]);
            }
        }

        if ($bank->slots()->count() === 0) {
            for ($i = 0; $i < 100; $i++) {
                Slot::create(['storage_uuid' => $bank->uuid, 'slot_type' => null]);
            }
        }

        $goldSlot = $inventory->slots()->first();
        if ($goldSlot && !Resource::where('slot_uuid', $goldSlot->uuid)->where('template_slug', 'gold')->exists()) {
            Resource::create([
                'slot_uuid' => $goldSlot->uuid,
                'recipe_slug' => 'gold',
                'template_slug' => 'gold',
                'slot_type' => 'gold',
                'max_stack' => null,
                'quantity' => 1000,
            ]);
        }

        $this->command->info("Test user created: {$user->email} ({$user->uuid})");
        $this->command->info("Test character created: {$character->name} ({$character->uuid})");
        $this->command->info("Starting gold: 1000");
    }
}
