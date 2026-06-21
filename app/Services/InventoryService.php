<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\GameEvent;
use App\Models\ItemInstance;
use App\Models\ItemTemplate;
use Illuminate\Support\Facades\DB;

class InventoryService
{
    public function __construct(
        private EventStore $eventStore
    ) {}

    public function addItem(
        int $userId,
        int $templateId,
        int $quantity = 1,
        ?string $correlationId = null,
        bool $recordEvent = true
    ): ItemInstance {
        return DB::transaction(function () use ($userId, $templateId, $quantity, $correlationId, $recordEvent) {
            $template = ItemTemplate::findOrFail($templateId);
            $maxStack = $template->max_stack ?? 999;

            $remaining = $quantity;
            $firstInstance = null;

            if ($template->is_stackable) {
                $existingItems = ItemInstance::where('owner_id', $userId)
                    ->where('template_id', $templateId)
                    ->orderBy('id')
                    ->get();

                foreach ($existingItems as $existing) {
                    if ($remaining <= 0) break;
                    $space = $maxStack - $existing->quantity;
                    if ($space > 0) {
                        $toAdd = min($remaining, $space);
                        $existing->quantity += $toAdd;
                        $existing->save();
                        $remaining -= $toAdd;

                        if (!$firstInstance) $firstInstance = $existing;

                        if ($recordEvent) {
                            $this->eventStore->recordItemEvent(
                                GameEvent::ITEM_RECEIVED,
                                $userId,
                                $templateId,
                                $existing->id,
                                [
                                    'quantity' => $toAdd,
                                    'new_total' => $existing->quantity,
                                    'reason' => 'stack_add',
                                ],
                                $correlationId
                            );
                        }
                    }
                }
            }

            while ($remaining > 0) {
                $toAdd = min($remaining, $maxStack);
                $item = ItemInstance::create([
                    'template_id' => $templateId,
                    'owner_id' => $userId,
                    'quantity' => $toAdd,
                    'durability' => 100,
                    'stats' => [],
                ]);

                if (!$firstInstance) $firstInstance = $item;
                $remaining -= $toAdd;

                if ($recordEvent) {
                    $this->eventStore->recordItemEvent(
                        GameEvent::ITEM_RECEIVED,
                        $userId,
                        $templateId,
                        $item->id,
                        [
                            'quantity' => $toAdd,
                            'reason' => 'new_item',
                        ],
                        $correlationId
                    );
                }
            }

            return $firstInstance;
        });
    }

    public function removeItem(
        int $userId,
        int $instanceId,
        int $quantity = 1,
        ?string $correlationId = null,
        string $reason = 'spend',
        bool $recordEvent = true
    ): void {
        DB::transaction(function () use ($userId, $instanceId, $quantity, $correlationId, $reason, $recordEvent) {
            $item = ItemInstance::where('id', $instanceId)
                ->where('owner_id', $userId)
                ->firstOrFail();

            $template = $item->template;

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
                    $template->id,
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

    public function getUserInventory(int $userId): array
    {
        return ItemInstance::where('owner_id', $userId)
            ->with('template')
            ->get()
            ->map(fn($item) => [
                'instance_id' => $item->id,
                'template_id' => $item->template_id,
                'name' => $item->template->name,
                'type' => $item->template->type,
                'icon' => $item->template->icon,
                'quantity' => $item->quantity,
                'description' => $item->template->description,
                'stats' => $item->stats ?? [],
            ])
            ->toArray();
    }
}
