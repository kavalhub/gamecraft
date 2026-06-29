<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Character;
use App\Models\Formula;
use App\Models\Item;
use App\Models\ItemTemplate;
use App\Models\Recipe;
use App\Models\Slot;
use App\Models\Storage;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CraftingService
{
    public function __construct(
        private InventoryService $inventoryService,
        private EventStore $eventStore
    ) {}

    public function getAvailableRecipes(Character $character): Collection
    {
        return Recipe::all()->map(function (Recipe $recipe) {
            $craftFormula = $recipe->craftFormulas()->first();
            return [
                'slug' => $recipe->slug,
                'type' => $recipe->type,
                'name' => $recipe->name,
                'description' => $recipe->description,
                'craft_formula' => $craftFormula ? $craftFormula->formula : [],
            ];
        });
    }

    public function craftResource(
        Character $character,
        string $recipeSlug,
        int $times = 1
    ): array {
        return DB::transaction(function () use ($character, $recipeSlug, $times) {
            $recipe = Recipe::where('slug', $recipeSlug)->where('type', 'resource')->firstOrFail();
            $formula = $recipe->craftFormulas()->firstOrFail();

            if ($times < 1) {
                throw new \RuntimeException('Количество должно быть больше 0');
            }

            $inputs = $formula->formula;
            foreach ($inputs as $templateSlug => $quantity) {
                $available = $this->inventoryService->getResourceQuantity($character, $templateSlug);
                $needed = $quantity * $times;
                if ($available < $needed) {
                    throw new \RuntimeException(
                        "Недостаточно ресурса {$templateSlug}: есть {$available}, нужно {$needed}"
                    );
                }
            }

            foreach ($inputs as $templateSlug => $quantity) {
                $this->inventoryService->removeResource($character, $templateSlug, $quantity * $times);
            }

            $resultTemplateSlug = $this->determineResourceResult($recipeSlug);
            $resultQuantity = $this->determineResourceResultQuantity($recipeSlug) * $times;

            $result = $this->inventoryService->addResource(
                $character,
                $resultTemplateSlug,
                $resultQuantity
            );

            $correlationUuid = Str::uuid()->toString();

            $this->eventStore->record(
                'resource.crafted',
                'character',
                $character->uuid,
                [
                    'recipe_slug' => $recipeSlug,
                    'times' => $times,
                    'inputs' => $inputs,
                    'result_template_slug' => $resultTemplateSlug,
                    'result_quantity' => $resultQuantity,
                ],
                $character->uuid,
                $correlationUuid
            );

            return [
                'recipe' => $recipe,
                'inputs' => $inputs,
                'result_template_slug' => $resultTemplateSlug,
                'result_quantity' => $resultQuantity,
                'times' => $times,
            ];
        });
    }

    public function craftItem(
        Character $character,
        string $recipeSlug,
        string $blueprintItemUuid,
        ?string $customName = null
    ): Item {
        return DB::transaction(function () use ($character, $recipeSlug, $blueprintItemUuid, $customName) {
            $recipe = Recipe::where('slug', $recipeSlug)->where('type', 'blueprint')->firstOrFail();
            $formula = $recipe->craftFormulas()->firstOrFail();

            $blueprint = Item::where('uuid', $blueprintItemUuid)
                ->where('stage', 'blueprint')
                ->where('recipe_slug', $recipeSlug)
                ->firstOrFail();

            $blueprintSlot = Slot::where('uuid', $blueprint->slot_uuid)->firstOrFail();
            $blueprintStorage = Storage::where('uuid', $blueprintSlot->storage_uuid)->firstOrFail();
            if ($blueprintStorage->characters_uuid !== $character->uuid) {
                throw new \RuntimeException('Чертёж не принадлежит персонажу');
            }

            $inputs = $formula->formula;
            foreach ($inputs as $templateSlug => $quantity) {
                $available = $this->inventoryService->getResourceQuantity($character, $templateSlug);
                if ($available < $quantity) {
                    throw new \RuntimeException(
                        "Недостаточно ресурса {$templateSlug}: есть {$available}, нужно {$quantity}"
                    );
                }
            }

            $materialsUsed = [];
            foreach ($inputs as $templateSlug => $quantity) {
                $materialsUsed[$templateSlug] = $quantity;
                $this->inventoryService->removeResource($character, $templateSlug, $quantity);
            }

            // Определяем template_slug для предмета (не blueprint!)
            $itemTemplateSlug = $this->getResultTemplateSlug($recipeSlug);
            $itemTemplate = ItemTemplate::where('slug', $itemTemplateSlug)->firstOrFail();

            // Трансформируем чертёж в предмет
            $blueprint->update([
                'stage' => 'item',
                'template_slug' => $itemTemplateSlug,
                'slot_type' => $itemTemplate->slot_type,
                'custom_name' => $customName ?? $this->generateItemName($recipe, $materialsUsed),
                'materials_used' => $materialsUsed,
                'stats' => $this->generateItemStats($itemTemplateSlug),
            ]);

            $correlationUuid = Str::uuid()->toString();

            $this->eventStore->record(
                'item.crafted',
                'item',
                $blueprint->uuid,
                [
                    'recipe_slug' => $recipeSlug,
                    'blueprint_uuid' => $blueprintItemUuid,
                    'item_template_slug' => $itemTemplateSlug,
                    'custom_name' => $blueprint->custom_name,
                    'materials_used' => $materialsUsed,
                    'stats' => $blueprint->stats,
                ],
                $character->uuid,
                $correlationUuid
            );

            return $blueprint->fresh();
        });
    }

    public function disassembleItem(
        Character $character,
        string $itemUuid,
        array $context = []
    ): array {
        return DB::transaction(function () use ($character, $itemUuid, $context) {
            $item = Item::where('uuid', $itemUuid)
                ->where('stage', 'item')
                ->firstOrFail();

            $slot = Slot::where('uuid', $item->slot_uuid)->firstOrFail();
            $storage = Storage::where('uuid', $slot->storage_uuid)->firstOrFail();
            if ($storage->characters_uuid !== $character->uuid) {
                throw new \RuntimeException('Предмет не принадлежит персонажу');
            }

            $recipe = Recipe::where('slug', $item->recipe_slug)->firstOrFail();
            $formula = $this->selectDisassembleFormula($recipe, $context);
            if (!$formula) {
                throw new \RuntimeException('Нет доступной формулы разбора');
            }

            $blueprintTemplateSlug = $this->getBlueprintTemplateSlug($recipe->slug);

            $item->update([
                'stage' => 'blueprint',
                'template_slug' => $blueprintTemplateSlug,
                'slot_type' => 'blueprint',
                'custom_name' => null,
                'materials_used' => null,
                'stats' => null,
            ]);

            $returnedResources = [];
            foreach ($formula->formula as $templateSlug => $quantity) {
                $this->inventoryService->addResource($character, $templateSlug, $quantity);
                $returnedResources[$templateSlug] = $quantity;
            }

            $correlationUuid = Str::uuid()->toString();

            $this->eventStore->record(
                'item.disassembled',
                'item',
                $item->uuid,
                [
                    'recipe_slug' => $recipe->slug,
                    'formula_description' => $formula->description,
                    'returned_resources' => $returnedResources,
                ],
                $character->uuid,
                $correlationUuid
            );

            return [
                'item' => $item->fresh(),
                'formula' => $formula,
                'returned_resources' => $returnedResources,
            ];
        });
    }

    public function createBlueprint(
        Character $character,
        string $recipeSlug
    ): Item {
        $recipe = Recipe::where('slug', $recipeSlug)->where('type', 'blueprint')->firstOrFail();
        $templateSlug = $this->getBlueprintTemplateSlug($recipeSlug);

        return $this->inventoryService->addItem(
            $character,
            $templateSlug,
            'blueprint',
            null,
            $recipeSlug,
            null,
            null,
            'inventory'
        );
    }

    private function selectDisassembleFormula(Recipe $recipe, array $context = []): ?Formula
    {
        $formulas = $recipe->disassembleFormulas()->orderBy('priority')->get();

        foreach ($formulas as $formula) {
            if ($formula->shouldApply($context)) {
                return $formula;
            }
        }

        return null;
    }

    private function determineResourceResult(string $recipeSlug): string
    {
        $mapping = [
            'craft_wooden_plank' => 'wooden_plank',
            'craft_iron_ingot' => 'iron_ingot',
        ];

        return $mapping[$recipeSlug] ?? throw new \RuntimeException("Неизвестный ресурсный рецепт: {$recipeSlug}");
    }

    private function determineResourceResultQuantity(string $recipeSlug): int
    {
        $mapping = [
            'craft_wooden_plank' => 5,
            'craft_iron_ingot' => 2,
        ];

        return $mapping[$recipeSlug] ?? 1;
    }

    private function getBlueprintTemplateSlug(string $recipeSlug): string
    {
        $mapping = [
            'craft_wooden_sword' => 'recipe_wooden_sword',
            'craft_iron_sword' => 'recipe_iron_sword',
        ];

        return $mapping[$recipeSlug] ?? throw new \RuntimeException("Неизвестный blueprint рецепт: {$recipeSlug}");
    }

    private function getResultTemplateSlug(string $recipeSlug): string
    {
        $mapping = [
            'craft_wooden_sword' => 'wooden_sword',
            'craft_iron_sword' => 'iron_sword',
        ];

        return $mapping[$recipeSlug] ?? throw new \RuntimeException("Неизвестный результат для рецепта: {$recipeSlug}");
    }

    private function generateItemName(Recipe $recipe, array $materialsUsed): string
    {
        $templateSlug = $this->getResultTemplateSlug($recipe->slug);
        $template = ItemTemplate::where('slug', $templateSlug)->first();
        return $template ? $template->name : $recipe->name;
    }

    private function generateItemStats(string $templateSlug): ?array
    {
        $template = ItemTemplate::where('slug', $templateSlug)->first();

        if (!$template || !$template->base_stats) {
            return null;
        }

        $stats = [];
        foreach ($template->base_stats as $statName => $range) {
            if (is_array($range) && isset($range['min'], $range['max'])) {
                $stats[$statName] = (int) round(($range['min'] + $range['max']) / 2);
            } else {
                $stats[$statName] = $range;
            }
        }

        return $stats;
    }
}
