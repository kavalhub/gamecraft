<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\GameEvent;
use App\Models\Item;
use App\Models\ItemTemplate;
use App\Models\ResourceBalance;
use Illuminate\Support\Facades\DB;

class InventoryService
{
    public function __construct(
        private EventStore $eventStore
    ) {}

    /**
     * Добавляет ресурс или предмет в инвентарь
     */
    public function addItem(
        int $userId,
        int $templateId,
        int $quantity = 1,
        ?string $correlationId = null,
        bool $recordEvent = true
    ): Item|ResourceBalance {
        $template = ItemTemplate::findOrFail($templateId);

        if ($template->isResource()) {
            return $this->addResource($userId, $templateId, $quantity, $correlationId, $recordEvent);
        } else {
            return $this->addEquipment($userId, $templateId, $quantity, $correlationId, $recordEvent);
        }
    }

    /**
     * Добавляет ресурс (материал, золото) в resource_balances
     */
    private function addResource(
        int $userId,
        int $templateId,
        int $quantity,
        ?string $correlationId,
        bool $recordEvent
    ): ResourceBalance {
        return DB::transaction(function () use ($userId, $templateId, $quantity, $correlationId, $recordEvent) {
            $balance = ResourceBalance::firstOrCreate(
                ['user_id' => $userId, 'template_id' => $templateId],
                ['quantity' => 0]
            );

            $balance->quantity += $quantity;
            $balance->save();

            if ($recordEvent) {
                $this->eventStore->recordResourceEvent(
                    GameEvent::RESOURCE_RECEIVED,
                    $userId,
                    $templateId,
                    [
                        'quantity' => $quantity,
                        'new_balance' => $balance->quantity,
                        'reason' => 'add',
                    ],
                    $correlationId
                );
            }

            return $balance;
        });
    }

    /**
     * Добавляет предмет (equipment, blueprint) в items
     */
    private function addEquipment(
        int $userId,
        int $templateId,
        int $quantity,
        ?string $correlationId,
        bool $recordEvent
    ): Item {
        return DB::transaction(function () use ($userId, $templateId, $quantity, $correlationId, $recordEvent) {
            // Для предметов quantity обычно 1, но поддерживаем стаки если is_stackable
            $template = ItemTemplate::findOrFail($templateId);
            
            if ($template->is_stackable) {
                // Стакаемые предметы (например, чертежи)
                $item = Item::where('owner_id', $userId)
                    ->where('template_id', $templateId)
                    ->whereNull('recipe_id') // Только базовые предметы без рецепта
                    ->first();

                if ($item) {
                    $item->quantity += $quantity;
                    $item->save();
                } else {
                    $item = Item::create([
                        'template_id' => $templateId,
                        'owner_id' => $userId,
                        'quantity' => $quantity,
                        'durability' => 100,
                        'stats' => [],
                    ]);
                }
            } else {
                // Нестакаемые предметы — создаём отдельный экземпляр для каждого
                $item = Item::create([
                    'template_id' => $templateId,
                    'owner_id' => $userId,
                    'quantity' => 1,
                    'durability' => 100,
                    'stats' => [],
                ]);
            }

            if ($recordEvent) {
                $this->eventStore->recordItemEvent(
                    GameEvent::ITEM_RECEIVED,
                    $userId,
                    $templateId,
                    $item->id,
                    [
                        'quantity' => $quantity,
                        'reason' => 'add',
                    ],
                    $correlationId
                );
            }

            return $item;
        });
    }

    /**
     * Удаляет ресурс или предмет из инвентаря
     */
    public function removeItem(
        int $userId,
        int $instanceId,
        int $quantity = 1,
        ?string $correlationId = null,
        string $reason = 'spend',
        bool $recordEvent = true
    ): void {
        $item = Item::where('id', $instanceId)
            ->where('owner_id', $userId)
            ->firstOrFail();

        $template = $item->template;

        if ($template->isResource()) {
            $this->removeResource($userId, $template->id, $quantity, $correlationId, $reason, $recordEvent);
        } else {
            $this->removeEquipment($userId, $instanceId, $quantity, $correlationId, $reason, $recordEvent);
        }
    }

    /**
     * Удаляет ресурс по template_id
     */
    public function removeResource(
        int $userId,
        int $templateId,
        int $quantity,
        ?string $correlationId = null,
        string $reason = 'spend',
        bool $recordEvent = true
    ): void {
        DB::transaction(function () use ($userId, $templateId, $quantity, $correlationId, $reason, $recordEvent) {
            $balance = ResourceBalance::where('user_id', $userId)
                ->where('template_id', $templateId)
                ->first();

            if (!$balance || $balance->quantity < $quantity) {
                $available = $balance ? $balance->quantity : 0;
                throw new \RuntimeException("Недостаточно ресурсов: есть {$available}, нужно {$quantity}");
            }

            $balance->quantity -= $quantity;
            
            if ($balance->quantity <= 0) {
                $balance->delete();
            } else {
                $balance->save();
            }

            if ($recordEvent) {
                $this->eventStore->recordResourceEvent(
                    GameEvent::RESOURCE_REMOVED,
                    $userId,
                    $templateId,
                    [
                        'quantity' => $quantity,
                        'new_balance' => $balance->quantity ?? 0,
                        'reason' => $reason,
                    ],
                    $correlationId
                );
            }
        });
    }

    /**
     * Удаляет предмет по instance_id
     */
    private function removeEquipment(
        int $userId,
        int $instanceId,
        int $quantity,
        ?string $correlationId,
        string $reason,
        bool $recordEvent
    ): void {
        DB::transaction(function () use ($userId, $instanceId, $quantity, $correlationId, $reason, $recordEvent) {
            $item = Item::where('id', $instanceId)
                ->where('owner_id', $userId)
                ->firstOrFail();

            if ($item->quantity < $quantity) {
                throw new \RuntimeException("Недостаточно предметов: есть {$item->quantity}, нужно {$quantity}");
            }

            $item->quantity -= $quantity;

            if ($item->quantity <= 0) {
                $item->delete();
            } else {
                $item->save();
            }

            if ($recordEvent) {
                $this->eventStore->recordItemEvent(
                    GameEvent::ITEM_REMOVED,
                    $userId,
                    $item->template_id,
                    $instanceId,
                    [
                        'quantity' => $quantity,
                        'reason' => $reason,
                    ],
                    $correlationId
                );
            }
        });
    }

    /**
     * Получает инвентарь пользователя (ресурсы + предметы)
     */
    public function getUserInventory(int $userId): array
    {
        // Ресурсы
        $resources = ResourceBalance::where('user_id', $userId)
            ->where('quantity', '>', 0)
            ->with('template')
            ->get()
            ->map(fn($balance) => [
                'instance_id' => null,
                'template_id' => $balance->template_id,
                'name' => $balance->template->name,
                'type' => $balance->template->type,
                'icon' => $balance->template->icon,
                'is_stackable' => true,
                'quantity' => $balance->quantity,
                'description' => $balance->template->description,
                'stats' => [],
            ]);

        // Предметы
        $items = Item::where('owner_id', $userId)
            ->with('template')
            ->get()
            ->map(fn($item) => [
                'instance_id' => $item->id,
                'template_id' => $item->template_id,
                'name' => $item->template->name,
                'type' => $item->template->type,
                'icon' => $item->template->icon,
                'is_stackable' => $item->template->is_stackable,
                'quantity' => $item->template->is_stackable ? $item->quantity : null,
                'description' => $item->template->description,
                'stats' => $item->stats ?? [],
            ]);

        return $resources->merge($items)->values()->toArray();
    }

    /**
     * Проверяет наличие ресурса
     */
    public function hasResource(int $userId, int $templateId, int $quantity): bool
    {
        $balance = ResourceBalance::where('user_id', $userId)
            ->where('template_id', $templateId)
            ->first();

        return $balance && $balance->quantity >= $quantity;
    }

    /**
     * Проверяет наличие предмета
     */
    public function hasItem(int $userId, int $instanceId, int $quantity = 1): bool
    {
        $item = Item::where('id', $instanceId)
            ->where('owner_id', $userId)
            ->first();

        return $item && $item->quantity >= $quantity;
    }
}
