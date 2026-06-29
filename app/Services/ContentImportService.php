<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuctionLot;
use App\Models\Character;
use App\Models\Formula;
use App\Models\ItemTemplate;
use App\Models\Recipe;
use App\Models\Slot;
use App\Models\SlotType;
use App\Models\Storage;
use App\Models\StorageType;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ContentImportService
{
    public function __construct(
        private StorageProvisioningService $storageProvisioning
    ) {}
    public function import(array $data): array
    {
        $report = [
            'version' => $data['version'] ?? 'unknown',
            'slot_types' => ['created' => 0, 'updated' => 0],
            'templates' => ['created' => 0, 'updated' => 0],
            'recipes' => ['created' => 0, 'updated' => 0],
            'formulas' => ['created' => 0],
            'characters' => ['created' => 0, 'updated' => 0],
            'shop_lots' => ['created' => 0, 'updated' => 0],
        ];

        DB::transaction(function () use ($data, &$report) {
            // 1. Импортируем типы слотов
            foreach ($data['slot_types'] ?? [] as $slotTypeData) {
                $this->importSlotType($slotTypeData, $report);
            }

            // 2. СНАЧАЛА импортируем рецепты (чтобы blueprint мог ссылаться)
            foreach ($data['recipes'] ?? [] as $recipeData) {
                $this->importRecipe($recipeData, $report);
            }

            // 3. ПОТОМ импортируем шаблоны предметов (теперь recipe_slug существует)
            foreach ($data['templates'] ?? [] as $templateData) {
                $this->importTemplate($templateData, $report);
            }

            // 4. Импортируем NPC
            foreach ($data['characters'] ?? [] as $characterData) {
                $this->importCharacter($characterData, $report);
            }

            // 5. Импортируем магазинные лоты
            foreach ($data['shop_lots'] ?? [] as $shopLotData) {
                $this->importShopLot($shopLotData, $report);
            }
        });

        return $report;
    }

    private function importSlotType(array $data, array &$report): void
    {
        $existing = SlotType::where('type', $data['type'])->first();
        $isNew = $existing === null;

        SlotType::updateOrCreate(
            ['type' => $data['type']],
            [
                'parent_type' => $data['parent_type'] ?? null,
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
            ]
        );

        if ($isNew) {
            $report['slot_types']['created']++;
        } else {
            $report['slot_types']['updated']++;
        }
    }

    private function importTemplate(array $data, array &$report): void
    {
        $existing = ItemTemplate::where('slug', $data['slug'])->first();
        $isNew = $existing === null;

        ItemTemplate::updateOrCreate(
            ['slug' => $data['slug']],
            [
                'name' => $data['name'],
                'type' => $data['type'],
                'icon' => $data['icon'] ?? null,
                'is_stackable' => $data['is_stackable'] ?? false,
                'max_stack' => $data['max_stack'] ?? null,
                'description' => $data['description'] ?? null,
                'base_stats' => $data['base_stats'] ?? null,
                'slot_type' => $data['slot_type'] ?? null,
                'recipe_slug' => $data['recipe_slug'] ?? null,
            ]
        );

        if ($isNew) {
            $report['templates']['created']++;
        } else {
            $report['templates']['updated']++;
        }
    }

    private function importRecipe(array $data, array &$report): void
    {
        $existing = Recipe::where('slug', $data['slug'])->first();
        $isNew = $existing === null;

        $recipe = Recipe::updateOrCreate(
            ['slug' => $data['slug']],
            [
                'type' => $data['type'],
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
            ]
        );

        // Удаляем старые формулы
        Formula::where('recipe_slug', $recipe->slug)->delete();

        // Создаём новые формулы
        foreach ($data['formulas'] ?? [] as $formulaData) {
            Formula::create([
                'recipe_slug' => $recipe->slug,
                'type' => $formulaData['type'],
                'priority' => $formulaData['priority'] ?? 100,
                'chance' => $formulaData['chance'] ?? 100,
                'conditions' => $formulaData['conditions'] ?? null,
                'formula' => $formulaData['formula'],
                'is_active' => true,
                'description' => $formulaData['description'] ?? null,
            ]);
            $report['formulas']['created']++;
        }

        if ($isNew) {
            $report['recipes']['created']++;
        } else {
            $report['recipes']['updated']++;
        }
    }

    private function importCharacter(array $data, array &$report): void
    {
        $existing = Character::where('name', $data['name'])->first();
        $isNew = $existing === null;

        $character = Character::updateOrCreate(
            ['name' => $data['name']],
            [
                'character_type' => $data['character_type'],
                'active' => true,
            ]
        );

        // Если это NPC, создаём для него user (для совместимости)
        if ($data['character_type'] === 'npc' && isset($data['email'])) {
            $user = \App\Models\User::updateOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'password' => Hash::make('npc_' . Str::random(16)),
                ]
            );
            $character->update(['user_uuid' => $user->uuid]);
        }

        // Создаём хранилища для NPC (если их ещё нет)
        if ($character->wasRecentlyCreated || $isNew) {
            $this->createDefaultStorages($character);
        }

        if ($isNew) {
            $report['characters']['created']++;
        } else {
            $report['characters']['updated']++;
        }
    }

    private function createDefaultStorages(Character $character): void
    {
        $this->storageProvisioning->grantStorage($character, 'inventory');
    }

    private function importShopLot(array $data, array &$report): void
    {
        $character = Character::where('name', $data['character_name'])->firstOrFail();
        $template = ItemTemplate::where('slug', $data['template_slug'])->firstOrFail();

        $existing = AuctionLot::where('seller_uuid', $character->uuid)
            ->where('template_slug', $template->slug)
            ->where('is_infinite', true)
            ->first();

        $isNew = $existing === null;

        AuctionLot::updateOrCreate(
            [
                'seller_uuid' => $character->uuid,
                'template_slug' => $template->slug,
                'is_infinite' => true,
            ],
            [
                'quantity' => $data['quantity'],
                'price' => $data['price'],
                'commission_percent' => 0,
                'status' => 'active',
            ]
        );

        if ($isNew) {
            $report['shop_lots']['created']++;
        } else {
            $report['shop_lots']['updated']++;
        }
    }
}
