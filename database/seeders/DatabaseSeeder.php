<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Character;
use App\Models\CharacterType;
use App\Models\Resources;
use App\Models\Slot;
use App\Models\Storage;
use App\Models\StorageType;
use App\Models\User;
use App\Services\ContentImportService;
use App\Services\CurrencyService;
use App\Services\StorageProvisioningService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedCharacterTypes();
        $this->seedStorageTypes();
        $this->importQuests();

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
        $this->createPostCharacter();
        $this->createSystemCharacter();
        $this->createTestUser();
    }

    private function importQuests(): void
    {
        $questsPath = base_path('content/quests.json');
        if (!File::exists($questsPath)) {
            $this->command->warn('content/quests.json not found');

            return;
        }

        $data = json_decode(File::get($questsPath), true);
        $report = app(\App\Services\QuestService::class)->importFromArray($data);
        $this->command->info("Quests: {$report['created']} created, {$report['updated']} updated");
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
            ['type' => 'inventory', 'name' => 'Инвентарь', 'allowed_types' => ['slots' => [
                ['slot_type' => 'gold', 'count' => 1, 'hidden' => true],
                ['slot_type' => 'experience', 'count' => 1, 'hidden' => true],
                ['slot_type' => null, 'count' => 36],
            ]]],
            ['type' => 'equipment', 'name' => 'Экипировка', 'allowed_types' => ['slots' => [
                ['slot_type' => 'equipment_head', 'count' => 1],
                ['slot_type' => 'equipment_shoulders', 'count' => 1],
                ['slot_type' => 'equipment_chest', 'count' => 1],
                ['slot_type' => 'equipment_legs', 'count' => 1],
                ['slot_type' => 'equipment_weapon', 'count' => 1],
                ['slot_type' => 'equipment_offhand', 'count' => 1],
                ['slot_type' => 'equipment_ring', 'count' => 2],
                ['slot_type' => 'equipment_amulet', 'count' => 1],
            ]]],
            ['type' => 'bank', 'name' => 'Банк', 'allowed_types' => ['slots' => [['slot_type' => null, 'count' => 80]]], 'metadata' => ['grid_cols' => 15]],
            ['type' => 'guild_bank', 'name' => 'Банк гильдии', 'allowed_types' => ['slots' => [['slot_type' => null, 'count' => 200]]], 'metadata' => ['grid_cols' => 15]],
            ['type' => 'auction', 'name' => 'Аукцион', 'allowed_types' => null],
            ['type' => 'trade', 'name' => 'Обмен', 'allowed_types' => null],
            ['type' => 'special', 'name' => 'Специальное', 'allowed_types' => null],
            ['type' => 'world', 'name' => 'Мир', 'allowed_types' => null],
            ['type' => 'post_outbox', 'name' => 'Исходящая почта', 'allowed_types' => ['slots' => [['slot_type' => null, 'count' => 6]]], 'metadata' => ['grid_cols' => 6]],
            ['type' => 'post_inbox', 'name' => 'Входящая почта', 'allowed_types' => null, 'metadata' => ['grid_cols' => 6]],
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

    private function createPostCharacter(): void
    {
        $post = Character::firstOrCreate(
            ['character_type' => 'post', 'name' => 'Почтовая служба'],
            ['active' => true],
        );

        Storage::firstOrCreate(
            ['characters_uuid' => $post->uuid, 'storage_type' => 'post_inbox'],
            ['name' => 'Входящие', 'active' => true],
        );

        $this->command->info("Post character created: {$post->name} ({$post->uuid})");
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

        Storage::firstOrCreate(
            ['characters_uuid' => $system->uuid, 'storage_type' => 'world'],
            ['name' => 'Мир', 'active' => true]
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

        $provisioning = app(StorageProvisioningService::class);

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
            $provisioning->provisionStorageSlots($inventory);
        }

        if ($equipment->slots()->count() === 0) {
            $provisioning->provisionStorageSlots($equipment);
        }

        if ($bank->slots()->count() === 0) {
            $provisioning->provisionStorageSlots($bank);
        }

        app(CurrencyService::class)->grantStartingGold($character);

        $this->command->info("Test user created: {$user->email} ({$user->uuid})");
        $this->command->info("Test character created: {$character->name} ({$character->uuid})");
        $this->command->info("Starting gold: 1000");
    }
}
