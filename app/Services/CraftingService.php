<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\GameEvent;
use App\Models\ItemInstance;
use App\Models\ItemTemplate;
use App\Models\Recipe;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CraftingService
{
    public function __construct(
        private InventoryService $inventoryService,
        private EventStore $eventStore
    ) {}

    /**
     * Получить все доступные рецепты
     */
    public function getRecipes(): array
    {
        return Recipe::with(['resultTemplate', 'components.template'])
            ->get()
            ->map(function (Recipe $recipe) {
                return [
                    'recipe_id' => $recipe->id,
                    'name' => $recipe->name,
                    'result' => [
                        'template_id' => $recipe->result_template_id,
                        'name' => $recipe->resultTemplate->name,
                        'icon' => $recipe->resultTemplate->icon,
                        'quantity' => $recipe->result_quantity,
                    ],
                    'components' => $recipe->components->map(function ($component) {
                        return [
                            'template_id' => $component->template_id,
                            'name' => $component->template->name,
                            'icon' => $component->template->icon,
                            'quantity' => $component->quantity,
                        ];
                    })->toArray(),
                ];
            })
            ->toArray();
    }

    /**
     * Создать предмет по рецепту
     */
    public function craft(int $userId, int $recipeId): array
    {
        $recipe = Recipe::with(['components.template', 'resultTemplate'])->findOrFail($recipeId);

        return DB::transaction(function () use ($userId, $recipe) {
            $correlationId = Str::uuid()->toString();

            // 1. Проверяем наличие чертежа
            $blueprint = ItemInstance::where('owner_id', $userId)
                ->whereHas('template', function ($q) use ($recipe) {
                    $q->where('type', 'recipe')
                        ->where('stats->recipe_id', $recipe->id);
                })
                ->first();

            if (!$blueprint) {
                throw new \RuntimeException('У вас нет чертежа для этого рецепта');
            }

            // 2. Проверяем наличие материалов
            foreach ($recipe->components as $component) {
                $owned = ItemInstance::where('owner_id', $userId)
                    ->where('template_id', $component->template_id)
                    ->first();

                $ownedQty = $owned ? $owned->quantity : 0;

                if ($ownedQty < $component->quantity) {
                    throw new \RuntimeException(
                        "Недостаточно: {$component->template->name} (нужно {$component->quantity}, есть {$ownedQty})"
                    );
                }
            }

            // 3. Снимаем материалы БЕЗ записи событий
            $componentsData = [];
            foreach ($recipe->components as $component) {
                $item = ItemInstance::where('owner_id', $userId)
                    ->where('template_id', $component->template_id)
                    ->first();

                $this->inventoryService->removeItem(
                    $userId,
                    $item->id,
                    $component->quantity,
                    $correlationId,
                    'consume',
                    false // НЕ записываем событие
                );

                $componentsData[] = [
                    'template_id' => $component->template_id,
                    'name' => $component->template->name,
                    'quantity' => $component->quantity,
                ];
            }

            // 4. Выдаем результат БЕЗ записи события
            $resultItem = $this->inventoryService->addItem(
                $userId,
                $recipe->result_template_id,
                $recipe->result_quantity,
                $correlationId,
                false // НЕ записываем событие
            );

            // 5. Записываем ОДНО событие крафта с полной информацией
            $this->eventStore->record(
                GameEvent::ITEM_CRAFTED,
                'user',
                $userId,
                [
                    'recipe_id' => $recipe->id,
                    'recipe_name' => $recipe->name,
                    'result_template_id' => $recipe->result_template_id,
                    'result_name' => $recipe->resultTemplate->name,
                    'quantity' => $recipe->result_quantity,
                    'components' => $componentsData,
                ],
                $userId,
                $correlationId
            );

            return [
                'message' => 'Предмет создан!',
                'item' => [
                    'name' => $recipe->resultTemplate->name,
                    'quantity' => $recipe->result_quantity,
                ],
            ];
        });
    }

    /**
     * Разобрать предмет на материалы
     */
    public function disassemble(int $userId, int $instanceId): array
    {
        return DB::transaction(function () use ($userId, $instanceId) {
            $item = ItemInstance::with('template')
                ->where('id', $instanceId)
                ->where('owner_id', $userId)
                ->firstOrFail();

            $disassembleData = $item->template->disassemble_data;

            if (empty($disassembleData)) {
                throw new \RuntimeException('Этот предмет нельзя разобрать');
            }

            $correlationId = Str::uuid()->toString();
            $materials = [];
            $itemName = $item->template->name;

            // 1. Собираем информацию о материалах
            foreach ($disassembleData as $templateId => $quantity) {
                $templateId = (int)$templateId;
                $quantity = (int)$quantity;

                $materialTemplate = ItemTemplate::find($templateId);
                $materialName = $materialTemplate ? $materialTemplate->name : 'Неизвестно';

                $materials[] = [
                    'template_id' => $templateId,
                    'name' => $materialName,
                    'quantity' => $quantity,
                ];
            }

            // 2. Добавляем материалы БЕЗ записи событий
            foreach ($disassembleData as $templateId => $quantity) {
                $this->inventoryService->addItem($userId, (int)$templateId, (int)$quantity, $correlationId, false);
            }

            // 3. Удаляем разобранный предмет
            $item->delete();

            // 4. Записываем ОДНО событие разборки с полной информацией
            $this->eventStore->record(
                GameEvent::ITEM_DISASSEMBLED,
                'user',
                $userId,
                [
                    'instance_id' => $instanceId,
                    'item_name' => $itemName,
                    'materials' => $materials,
                ],
                $userId,
                $correlationId
            );

            return [
                'message' => 'Предмет разобран!',
                'materials' => $materials,
            ];
        });
    }
}
