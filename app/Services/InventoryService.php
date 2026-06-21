<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\GameEvent;
use App\Models\ItemInstance;
use App\Models\ItemTemplate;

class InventoryService
{
    public function __construct(
        private EventStore $eventStore
    ) {}

    public function addItem(int $userId, int $templateId, int $quantity = 1, ?string $correlationId = null, bool $recordEvent = true): ItemInstance
    {
        $template = ItemTemplate::findOrFail($templateId);

        if ($template->is_stackable) {
            $existingItem = ItemInstance::where('owner_id', $userId)
                ->where('template_id', $templateId)
                ->first();

            if ($existingItem) {
                $existingItem->quantity += $quantity;
                $existingItem->save();

                if ($recordEvent) {
                    $this->eventStore->record(
                        GameEvent::ITEM_RECEIVED,
                        'user',
                        $userId,
                        [
                            'template_id' => $templateId,
                            'template_name' => $template->name,
                            'instance_id' => $existingItem->id,
                            'quantity' => $quantity,
                            'new_total' => $existingItem->quantity,
                            'reason' => 'stack_add',
                        ],
                        $userId,
                        $correlationId
                    );
                }

                return $existingItem;
            }
        }

        $item = ItemInstance::create([
            'template_id' => $templateId,
            'owner_id' => $userId,
            'quantity' => $quantity,
            'durability' => 100,
        ]);

        if ($recordEvent) {
            $this->eventStore->record(
                GameEvent::ITEM_RECEIVED,
                'user',
                $userId,
                [
                    'template_id' => $templateId,
                    'template_name' => $template->name,
                    'instance_id' => $item->id,
                    'quantity' => $quantity,
                    'reason' => 'new_item',
                ],
                $userId,
                $correlationId
            );
        }

        return $item;
    }

    public function removeItem(int $userId, int $instanceId, int $quantity = 1, ?string $correlationId = null, string $reason = 'consume', bool $recordEvent = true): bool
    {
        $item = ItemInstance::where('id', $instanceId)
            ->where('owner_id', $userId)
            ->firstOrFail();

        $template = $item->template;
        $removedQuantity = min($quantity, $item->quantity);

        if ($item->quantity > $quantity) {
            $item->quantity -= $quantity;
            $item->save();
        } else {
            $item->delete();
        }

        if ($recordEvent) {
            $this->eventStore->record(
                GameEvent::ITEM_REMOVED,
                'user',
                $userId,
                [
                    'template_id' => $template->id,
                    'template_name' => $template->name,
                    'instance_id' => $instanceId,
                    'quantity' => $removedQuantity,
                    'reason' => $reason,
                ],
                $userId,
                $correlationId
            );
        }

        return true;
    }
    public function getInventory(int $userId): array
    {
        // Read model — просто читаем из таблицы
        return ItemInstance::with('template')
            ->where('owner_id', $userId)
            ->get()
            ->map(function ($item) {
                return [
                    'instance_id' => $item->id,
                    'template_id' => $item->template_id,
                    'name' => $item->template->name,
                    'type' => $item->template->type,
                    'icon' => $item->template->icon,
                    'quantity' => $item->quantity,
                    'durability' => $item->durability,
                    'is_stackable' => $item->template->is_stackable,
                    'stats' => $item->stats,
                ];
            })
            ->toArray();
    }
}
