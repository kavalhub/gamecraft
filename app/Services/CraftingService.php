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
     * Получить список всех рецептов с полной информацией
     */
    public function getRecipes(): array
    {
        return Recipe::with(['components.template', 'resultTemplate'])
            ->get()
            ->map(fn(Recipe $recipe) => [
                'recipe_id' => $recipe->id,
                'result_template_id' => $recipe->result_template_id,
                'result_quantity' => $recipe->result_quantity,
                'result' => [
                    'template_id' => $recipe->resultTemplate->id,
                    'template_name' => $recipe->resultTemplate->name,
                    'template_type' => $recipe->resultTemplate->type,
                    'template_icon' => $recipe->resultTemplate->icon,
                    'description' => $recipe->resultTemplate->description ?? '',
                    'name' => $recipe->resultTemplate->name,
                    'type' => $recipe->resultTemplate->type,
                    'icon' => $recipe->resultTemplate->icon,
                    'quantity' => $recipe->result_quantity,
                ],
                'components' => $recipe->components->map(fn($c) => [
                    'template_id' => $c->template_id,
                    'template_name' => $c->template->name,
                    'template_type' => $c->template->type,
                    'template_icon' => $c->template->icon,
                    'description' => $c->template->description ?? '',
                    'name' => $c->template->name,
                    'type' => $c->template->type,
                    'icon' => $c->template->icon,
                    'quantity' => $c->quantity,
                ])->toArray(),
            ])
            ->toArray();
    }

    /**
     * Создать предмет по рецепту
     */
    public function craft(int $userId, int $recipeId, int $quantity = 1): ItemInstance
    {
        return DB::transaction(function () use ($userId, $recipeId, $quantity) {
            $recipe = Recipe::with(['components.template', 'resultTemplate'])->findOrFail($recipeId);

            if (!$recipe->components || $recipe->components->isEmpty()) {
                throw new \RuntimeException('Рецепт не имеет компонентов');
            }

            if (!$recipe->resultTemplate) {
                throw new \RuntimeException('Рецепт не имеет результата');
            }

            $totalNeeded = [];
            foreach ($recipe->components as $component) {
                if (!$component->template) {
                    throw new \RuntimeException('Компонент рецепта не имеет шаблона');
                }
                $needed = $component->quantity * $quantity;
                $totalNeeded[$component->template_id] = $needed;

                $available = ItemInstance::where('owner_id', $userId)
                    ->where('template_id', $component->template_id)
                    ->sum('quantity');

                if ($available < $needed) {
                    throw new \RuntimeException(
                        "Недостаточно {$component->template->name}: нужно {$needed}, есть {$available}"
                    );
                }
            }

            // Снимаем все ингредиенты сразу
            foreach ($recipe->components as $component) {
                $remaining = $totalNeeded[$component->template_id];

                $stacks = ItemInstance::where('owner_id', $userId)
                    ->where('template_id', $component->template_id)
                    ->orderBy('quantity', 'desc')
                    ->get();

                foreach ($stacks as $stack) {
                    if ($remaining <= 0) break;
                    $toRemove = min($remaining, $stack->quantity);
                    $this->inventoryService->removeItem(
                        $userId,
                        $stack->id,
                        $toRemove,
                        null,
                        'craft',
                        false
                    );
                    $remaining -= $toRemove;
                }
            }

            // Крафтим по единице и записываем событие для каждой
            $lastInstance = null;
            for ($i = 0; $i < $quantity; $i++) {
                $resultInstance = $this->inventoryService->addItem(
                    $userId,
                    $recipe->result_template_id,
                    1,
                    null,
                    false
                );

                $this->eventStore->record(
                    GameEvent::ITEM_CRAFTED,
                    'user',
                    $userId,
                    [
                        'recipe_id' => $recipeId,
                        'quantity' => 1,
                        'result' => [
                            'template_id' => $recipe->result_template_id,
                            'template_name' => $recipe->resultTemplate->name,
                            'template_type' => $recipe->resultTemplate->type,
                            'template_icon' => $recipe->resultTemplate->icon,
                            'description' => $recipe->resultTemplate->description ?? '',
                            'instance_id' => $resultInstance->id,
                            'stats' => $resultInstance->stats ?? [],
                            'quantity' => 1,
                        ],
                        'components' => $recipe->components->map(fn($c) => [
                            'template_id' => $c->template_id,
                            'template_name' => $c->template->name,
                            'template_type' => $c->template->type,
                            'template_icon' => $c->template->icon,
                            'description' => $c->template->description ?? '',
                            'quantity' => $c->quantity,
                        ])->toArray(),
                    ],
                    $userId,
                    null
                );

                $lastInstance = $resultInstance;
            }

            return $lastInstance;
        });
    }

    /**
     * Разобрать предмет на материалы
     */
    public function disassemble(int $userId, int $instanceId): array
    {
        return DB::transaction(function () use ($userId, $instanceId) {
            $item = ItemInstance::where('id', $instanceId)
                ->where('owner_id', $userId)
                ->with('template')
                ->firstOrFail();

            $materials = [];

            $disassembleData = $item->template->disassemble_data;

            // Если это строка — декодируем в массив
            if (is_string($disassembleData)) {
                $disassembleData = json_decode($disassembleData, true);
            }

            // Если null или не массив — предмет нельзя разобрать
            if (!is_array($disassembleData) || empty($disassembleData)) {
                throw new \RuntimeException('Этот предмет нельзя разобрать');
            }

            foreach ($disassembleData as $templateId => $quantity) {
                $template = ItemTemplate::find($templateId);
                if (!$template) continue;

                $this->inventoryService->addItem(
                    $userId,
                    $templateId,
                    $quantity,
                    null,
                    false
                );

                $materials[] = [
                    'template_id' => $templateId,
                    'template_name' => $template->name,
                    'template_type' => $template->type,
                    'template_icon' => $template->icon,
                    'description' => $template->description ?? '',
                    'quantity' => $quantity,
                ];
            }

            $this->inventoryService->removeItem(
                $userId,
                $instanceId,
                1,
                null,
                'disassemble',
                false
            );

            $correlationId = Str::uuid()->toString();

            $this->eventStore->record(
                GameEvent::ITEM_DISASSEMBLED,
                'user',
                $userId,
                [
                    'item_name' => $item->template->name,
                    'item_type' => $item->template->type,
                    'item_icon' => $item->template->icon,
                    'description' => $item->template->description ?? '',
                    'materials' => $materials,
                ],
                $userId,
                $correlationId
            );

            return $materials;
        });
    }
}
