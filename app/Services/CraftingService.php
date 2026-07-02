<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Character;
use App\Models\Formula;
use App\Models\Item;
use App\Models\ItemTemplate;
use App\Models\Recipe;
use App\Models\Resources;
use App\Support\ItemMaterialsUsed;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CraftingService
{
    public function __construct(
        private InventoryService $inventoryService,
        private EventStore $eventStore,
        private CraftStationService $craftStationService,
        private DisassembleStationService $disassembleStationService,
        private QuestService $questService,
        private CraftingActionResolver $craftingActionResolver,
    ) {}

    public function getAvailableRecipes(Character $character): Collection
    {
        return Recipe::all()->map(function (Recipe $recipe) {
            $craftFormula = $recipe->craftFormulas()->with('action')->first();
            $disassembleFormula = $recipe->disassembleFormulas()->with('action')->first();

            return [
                'slug' => $recipe->slug,
                'type' => $recipe->type,
                'name' => $recipe->name,
                'description' => $recipe->description,
                'craft_formula' => $craftFormula ? $craftFormula->formula : [],
                'disassemble_formula' => $disassembleFormula ? $disassembleFormula->formula : [],
                'craft_action' => $craftFormula ? [
                    'slug' => $craftFormula->action_slug ?? 'create',
                    'label' => $this->craftingActionResolver->actionLabelForFormula($craftFormula),
                ] : null,
                'disassemble_action' => $disassembleFormula ? [
                    'slug' => $disassembleFormula->action_slug ?? 'disassemble',
                    'label' => $this->craftingActionResolver->actionLabelForFormula($disassembleFormula),
                ] : null,
                'result_template_slug' => $this->resolveResultTemplateSlug($recipe),
                'result_quantity' => $this->resolveResultQuantity($recipe),
            ];
        });
    }

    public function resolveResultQuantity(Recipe $recipe): int
    {
        if ($recipe->result_quantity && $recipe->result_quantity > 0) {
            return (int) $recipe->result_quantity;
        }

        return 1;
    }

    public function resolveResultTemplateSlug(Recipe $recipe): ?string
    {
        if ($recipe->result_template_slug) {
            return $recipe->result_template_slug;
        }

        try {
            if ($recipe->type === 'blueprint') {
                return $this->getResultTemplateSlug($recipe->slug);
            }
        } catch (\Throwable) {
            return null;
        }

        return null;
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
            if ($inputs === []) {
                throw new \RuntimeException("У рецепта {$recipeSlug} не задана формула преобразования");
            }

            $available = $this->craftStationService->getCombinedIngredientQuantities($character);

            foreach ($inputs as $templateSlug => $quantity) {
                $needed = $quantity * $times;
                $have = $available[$templateSlug] ?? 0;
                if ($have < $needed) {
                    throw new \RuntimeException(
                        "Недостаточно ресурса {$templateSlug} на станции создания: есть {$have}, нужно {$needed}"
                    );
                }
            }

            foreach ($inputs as $templateSlug => $quantity) {
                $this->craftStationService->removeIngredients($character, $templateSlug, $quantity * $times);
            }

            $resultTemplateSlug = $this->resolveResultTemplateSlug($recipe);
            if (!$resultTemplateSlug) {
                throw new \RuntimeException("У рецепта {$recipeSlug} не задан результат преобразования");
            }
            $resultQuantity = $this->resolveResultQuantity($recipe) * $times;

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

            $this->questService->handleGameEvent($character, 'resource.crafted', [
                'recipe_slug' => $recipeSlug,
                'result_template_slug' => $resultTemplateSlug,
                'times' => $times,
            ]);

            $this->craftStationService->finalizeAfterCraft($character);

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

            $blueprintSlot = $blueprint->slot;
            $blueprintStorage = $blueprintSlot?->storage;
            if (!$blueprintStorage || $blueprintStorage->characters_uuid !== $character->uuid) {
                throw new \RuntimeException('Чертёж не принадлежит персонажу');
            }

            $this->craftStationService->assertBlueprintOnStation($blueprint, $character);

            $inputs = $formula->formula;
            $stationResources = $this->craftStationService->getMaterialQuantities($character);

            foreach ($inputs as $templateSlug => $quantity) {
                $available = $stationResources[$templateSlug] ?? 0;
                if ($available < $quantity) {
                    throw new \RuntimeException(
                        "Недостаточно ресурса {$templateSlug} на станции создания: есть {$available}, нужно {$quantity}"
                    );
                }
            }

            $materialsUsed = ItemMaterialsUsed::build($character, []);
            foreach ($inputs as $templateSlug => $quantity) {
                $materialsUsed['resources'][$templateSlug] = $quantity;
                $this->craftStationService->removeIngredients($character, $templateSlug, $quantity);
            }

            // Определяем template_slug для предмета (не blueprint!)
            $itemTemplateSlug = $this->getResultTemplateSlug($recipeSlug);
            $itemTemplate = ItemTemplate::where('slug', $itemTemplateSlug)->firstOrFail();

            // Трансформируем чертёж в предмет
            $blueprint->update([
                'stage' => 'item',
                'template_slug' => $itemTemplateSlug,
                'slot_type' => $itemTemplate->slot_type,
                'custom_name' => $customName ?? $this->generateItemName($recipe, $materialsUsed['resources']),
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
                    'template_slug' => $itemTemplateSlug,
                    'custom_name' => $blueprint->custom_name,
                    'icon' => $itemTemplate->icon,
                    'materials_used' => $materialsUsed,
                    'stats' => $blueprint->stats,
                ],
                $character->uuid,
                $correlationUuid
            );

            $this->questService->handleGameEvent($character, 'item.crafted', [
                'recipe_slug' => $recipeSlug,
                'item_template_slug' => $itemTemplateSlug,
            ]);

            $this->craftStationService->finalizeAfterCraft($character);

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

            $slot = $item->slot;
            $storage = $slot?->storage;
            if (!$storage || $storage->characters_uuid !== $character->uuid) {
                throw new \RuntimeException('Предмет не принадлежит персонажу');
            }

            $this->disassembleStationService->assertItemOnStation($item, $character);

            $recipe = Recipe::where('slug', $item->recipe_slug)->firstOrFail();
            $formula = $this->selectDisassembleFormula($recipe, $context);
            if (!$formula) {
                throw new \RuntimeException('Нет доступной формулы разбора');
            }

            $blueprintTemplateSlug = $this->getBlueprintTemplateSlug($recipe->slug);
            $disassembledTemplateSlug = $item->template_slug;
            $disassembledName = $item->custom_name;

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
                $returnedResources[$templateSlug] = $quantity;
            }

            $this->disassembleStationService->depositOutputs($character, $returnedResources);

            $correlationUuid = Str::uuid()->toString();

            $this->eventStore->record(
                'item.disassembled',
                'item',
                $item->uuid,
                [
                    'recipe_slug' => $recipe->slug,
                    'item_template_slug' => $disassembledTemplateSlug,
                    'custom_name' => $disassembledName,
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

    public function disassembleResource(
        Character $character,
        string $recipeSlug,
        int $times = 1,
        array $context = []
    ): array {
        return DB::transaction(function () use ($character, $recipeSlug, $times, $context) {
            if ($times < 1) {
                throw new \RuntimeException('Количество должно быть больше 0');
            }

            $recipe = Recipe::where('slug', $recipeSlug)->where('type', 'resource')->firstOrFail();
            $formula = $this->selectDisassembleFormula($recipe, $context);
            if (!$formula) {
                throw new \RuntimeException('Нет доступной формулы разбора');
            }

            $centerSlot = $this->disassembleStationService->getCenterTemporarySlot($character);
            $resource = Resources::where('temporary_slot_uuid', $centerSlot->uuid)->firstOrFail();

            $this->disassembleStationService->assertResourceOnStation($resource, $character);

            if ($resource->template_slug !== $recipe->slug) {
                throw new \RuntimeException('Ресурс на станции разбора не соответствует рецепту разбора');
            }

            if ($resource->quantity < $times) {
                throw new \RuntimeException(
                    "Недостаточно ресурса {$resource->template_slug} на станции разбора: есть {$resource->quantity}, нужно {$times}"
                );
            }

            if ($resource->quantity <= $times) {
                $resource->delete();
            } else {
                $resource->update(['quantity' => $resource->quantity - $times]);
            }

            $returnedResources = [];
            foreach ($formula->formula as $templateSlug => $quantity) {
                $total = $quantity * $times;
                $returnedResources[$templateSlug] = $total;
            }

            $this->disassembleStationService->depositOutputs($character, $returnedResources);

            $correlationUuid = Str::uuid()->toString();

            $this->eventStore->record(
                'resource.disassembled',
                'character',
                $character->uuid,
                [
                    'recipe_slug' => $recipeSlug,
                    'times' => $times,
                    'source_template_slug' => $recipe->slug,
                    'returned_resources' => $returnedResources,
                    'formula_description' => $formula->description,
                ],
                $character->uuid,
                $correlationUuid
            );

            $this->questService->handleGameEvent($character, 'resource.disassembled', [
                'recipe_slug' => $recipeSlug,
                'returned_resources' => $returnedResources,
                'times' => $times,
            ]);

            return [
                'recipe' => $recipe,
                'formula' => $formula,
                'returned_resources' => $returnedResources,
                'times' => $times,
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
