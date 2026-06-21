<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\GameEvent;
use App\Models\ItemInstance;
use App\Models\ItemTemplate;
use App\Models\TradeItem;
use App\Models\TradeOffer;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TradeService
{
    public function __construct(
        private InventoryService $inventoryService,
        private EventStore $eventStore
    ) {}

    public function getActiveTrades(int $userId): array
    {
        return TradeOffer::with(['initiator', 'partner', 'items.template'])
            ->where(function ($q) use ($userId) {
                $q->where('initiator_id', $userId)
                    ->orWhere('partner_id', $userId);
            })
            ->whereIn('status', ['pending', 'active'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn(TradeOffer $t) => $this->formatTrade($t, $userId))
            ->toArray();
    }

    public function getTrade(int $tradeId, int $userId): array
    {
        $trade = TradeOffer::with(['initiator', 'partner', 'items.template', 'items.instance'])
            ->findOrFail($tradeId);

        if (!$trade->isParticipant($userId)) {
            throw new \RuntimeException('Вы не участвуете в этом обмене');
        }

        return $this->formatTrade($trade, $userId);
    }

    public function createTrade(int $initiatorId, int $partnerId): TradeOffer
    {
        if ($initiatorId === $partnerId) {
            throw new \RuntimeException('Нельзя торговать с самим собой');
        }

        $initiator = User::find($initiatorId);
        $partner = User::find($partnerId);

        if (!$initiator || !$partner) {
            throw new \RuntimeException('Игрок не найден');
        }

        $existing = TradeOffer::where(function ($q) use ($initiatorId, $partnerId) {
            $q->where(function ($sub) use ($initiatorId, $partnerId) {
                $sub->where('initiator_id', $initiatorId)->where('partner_id', $partnerId);
            })->orWhere(function ($sub) use ($initiatorId, $partnerId) {
                $sub->where('initiator_id', $partnerId)->where('partner_id', $initiatorId);
            });
        })->whereIn('status', ['pending', 'active'])->first();

        if ($existing) {
            throw new \RuntimeException('У вас уже есть активный обмен с этим игроком');
        }

        $trade = TradeOffer::create([
            'initiator_id' => $initiatorId,
            'partner_id' => $partnerId,
            'status' => 'active',
        ]);

        $correlationId = Str::uuid()->toString();
        $payload = [
            'trade_id' => $trade->id,
            'initiator_id' => $initiatorId,
            'initiator_name' => $initiator->name,
            'partner_id' => $partnerId,
            'partner_name' => $partner->name,
        ];

        $this->eventStore->record(
            GameEvent::TRADE_CREATED, 'trade', $trade->id,
            $payload, $initiatorId, $correlationId
        );

        $this->eventStore->record(
            GameEvent::TRADE_CREATED, 'trade', $trade->id,
            $payload, $partnerId, $correlationId
        );

        return $trade;
    }

    public function addItem(int $userId, int $tradeId, int $templateId, int $quantity): TradeOffer
    {
        return DB::transaction(function () use ($userId, $tradeId, $templateId, $quantity) {
            $trade = TradeOffer::findOrFail($tradeId);
            $side = $trade->getSide($userId);

            if (!$side) throw new \RuntimeException('Вы не участвуете в этом обмене');
            if ($trade->status !== 'active') throw new \RuntimeException('Обмен не активен');
            if ($quantity <= 0) throw new \RuntimeException('Количество должно быть больше 0');

            $template = ItemTemplate::findOrFail($templateId);

            if ($template->type === 'recipe') {
                throw new \RuntimeException('Чертежи нельзя обменивать');
            }

            $items = ItemInstance::where('owner_id', $userId)
                ->where('template_id', $templateId)
                ->orderBy('quantity', 'desc')
                ->get();

            $totalAvailable = $items->sum('quantity');

            if ($totalAvailable < $quantity) {
                throw new \RuntimeException("В инвентаре только {$totalAvailable} шт., нельзя передать {$quantity}");
            }

            $existingTradeItem = TradeItem::where('trade_id', $tradeId)
                ->where('side', $side)
                ->where('template_id', $templateId)
                ->first();

            if ($existingTradeItem) {
                $existingTradeItem->quantity += $quantity;
                $existingTradeItem->save();
            } else {
                $firstInstance = $items->first();
                TradeItem::create([
                    'trade_id' => $tradeId,
                    'side' => $side,
                    'template_id' => $templateId,
                    'item_instance_id' => $firstInstance->id,
                    'quantity' => $quantity,
                ]);
            }

            $this->resetAcceptances($trade);
            $this->emitTradeUpdated($trade, $userId);

            return $trade->fresh();
        });
    }

    public function reduceItem(int $userId, int $tradeId, int $tradeItemId, int $quantity): TradeOffer
    {
        return DB::transaction(function () use ($userId, $tradeId, $tradeItemId, $quantity) {
            $trade = TradeOffer::findOrFail($tradeId);
            $side = $trade->getSide($userId);

            if (!$side) throw new \RuntimeException('Вы не участвуете в этом обмене');
            if ($quantity <= 0) throw new \RuntimeException('Количество должно быть больше 0');

            $tradeItem = TradeItem::where('id', $tradeItemId)
                ->where('trade_id', $tradeId)
                ->where('side', $side)
                ->firstOrFail();

            if ($tradeItem->quantity > $quantity) {
                $tradeItem->quantity -= $quantity;
                $tradeItem->save();
            } else {
                $tradeItem->delete();
            }

            $this->resetAcceptances($trade);
            $this->emitTradeUpdated($trade, $userId);

            return $trade->fresh();
        });
    }

    public function removeItem(int $userId, int $tradeId, int $tradeItemId): TradeOffer
    {
        return DB::transaction(function () use ($userId, $tradeId, $tradeItemId) {
            $trade = TradeOffer::findOrFail($tradeId);
            $side = $trade->getSide($userId);

            if (!$side) throw new \RuntimeException('Вы не участвуете в этом обмене');

            $tradeItem = TradeItem::where('id', $tradeItemId)
                ->where('trade_id', $tradeId)
                ->where('side', $side)
                ->firstOrFail();

            $tradeItem->delete();

            $this->resetAcceptances($trade);
            $this->emitTradeUpdated($trade, $userId);

            return $trade->fresh();
        });
    }

    public function addGold(int $userId, int $tradeId, int $amount): TradeOffer
    {
        return DB::transaction(function () use ($userId, $tradeId, $amount) {
            $trade = TradeOffer::findOrFail($tradeId);
            $side = $trade->getSide($userId);

            if (!$side) throw new \RuntimeException('Вы не участвуете в этом обмене');
            if ($trade->status !== 'active') throw new \RuntimeException('Обмен не активен');
            if ($amount < 0) throw new \RuntimeException('Сумма должна быть положительной');

            $user = User::findOrFail($userId);
            if ($user->gold < $amount) {
                throw new \RuntimeException('Недостаточно золота');
            }

            $field = $side === 'initiator' ? 'initiator_gold' : 'partner_gold';
            $trade->update([$field => $amount]);

            $this->resetAcceptances($trade);
            $this->emitTradeUpdated($trade, $userId);

            return $trade->fresh();
        });
    }

    public function accept(int $userId, int $tradeId): TradeOffer
    {
        return DB::transaction(function () use ($userId, $tradeId) {
            $trade = TradeOffer::findOrFail($tradeId);
            $side = $trade->getSide($userId);

            if (!$side) throw new \RuntimeException('Вы не участвуете в этом обмене');
            if ($trade->status !== 'active') throw new \RuntimeException('Обмен не активен');

            $field = $side === 'initiator' ? 'initiator_accepted' : 'partner_accepted';
            $trade->update([$field => true]);

            $correlationId = Str::uuid()->toString();
            $payload = [
                'trade_id' => $trade->id,
                'user_id' => $userId,
                'side' => $side,
            ];

            $this->eventStore->record(
                GameEvent::TRADE_ACCEPTED, 'trade', $trade->id,
                $payload, $trade->initiator_id, $correlationId
            );
            $this->eventStore->record(
                GameEvent::TRADE_ACCEPTED, 'trade', $trade->id,
                $payload, $trade->partner_id, $correlationId
            );

            $trade = $trade->fresh();

            if ($trade->initiator_accepted && $trade->partner_accepted) {
                $this->executeTrade($trade);
            } else {
                $this->emitTradeUpdated($trade, $userId);
            }

            return $trade->fresh();
        });
    }

    public function cancel(int $userId, int $tradeId): TradeOffer
    {
        $trade = TradeOffer::findOrFail($tradeId);
        $side = $trade->getSide($userId);

        if (!$side) throw new \RuntimeException('Вы не участвуете в этом обмене');
        if (!in_array($trade->status, ['pending', 'active'])) {
            throw new \RuntimeException('Обмен уже завершён');
        }

        $trade->update(['status' => 'cancelled']);

        $correlationId = Str::uuid()->toString();
        $payload = ['trade_id' => $trade->id, 'cancelled_by' => $userId];

        $this->eventStore->record(
            GameEvent::TRADE_CANCELLED, 'trade', $trade->id,
            $payload, $trade->initiator_id, $correlationId
        );
        $this->eventStore->record(
            GameEvent::TRADE_CANCELLED, 'trade', $trade->id,
            $payload, $trade->partner_id, $correlationId
        );

        return $trade;
    }

    private function executeTrade(TradeOffer $trade): void
    {
        DB::transaction(function () use ($trade) {
            $correlationId = Str::uuid()->toString();

            $initiator = User::findOrFail($trade->initiator_id);
            $partner = User::findOrFail($trade->partner_id);

            $initiatorItems = $trade->initiatorItems;
            $partnerItems = $trade->partnerItems;

            // Проверка наличия предметов по template_id
            foreach ($initiatorItems as $ti) {
                $available = ItemInstance::where('owner_id', $trade->initiator_id)
                    ->where('template_id', $ti->template_id)
                    ->sum('quantity');
                if ($available < $ti->quantity) {
                    throw new \RuntimeException("Предметы инициатора больше недоступны ({$ti->template->name})");
                }
            }
            foreach ($partnerItems as $ti) {
                $available = ItemInstance::where('owner_id', $trade->partner_id)
                    ->where('template_id', $ti->template_id)
                    ->sum('quantity');
                if ($available < $ti->quantity) {
                    throw new \RuntimeException("Предметы партнёра больше недоступны ({$ti->template->name})");
                }
            }

            if ($initiator->gold < $trade->initiator_gold) {
                throw new \RuntimeException('У инициатора недостаточно золота');
            }
            if ($partner->gold < $trade->partner_gold) {
                throw new \RuntimeException('У партнёра недостаточно золота');
            }

            $initiatorDelta = $trade->partner_gold - $trade->initiator_gold;
            $partnerDelta = $trade->initiator_gold - $trade->partner_gold;

            if ($initiatorDelta !== 0) $initiator->increment('gold', $initiatorDelta);
            if ($partnerDelta !== 0) $partner->increment('gold', $partnerDelta);

            // Обмен предметами: инициатор → партнёру
            foreach ($initiatorItems as $ti) {
                $remaining = $ti->quantity;
                $stacks = ItemInstance::where('owner_id', $trade->initiator_id)
                    ->where('template_id', $ti->template_id)
                    ->orderBy('quantity', 'desc')
                    ->get();

                foreach ($stacks as $stack) {
                    if ($remaining <= 0) break;
                    $toRemove = min($remaining, $stack->quantity);
                    $this->inventoryService->removeItem(
                        $trade->initiator_id, $stack->id, $toRemove,
                        $correlationId, 'trade', false
                    );
                    $remaining -= $toRemove;
                }

                $this->inventoryService->addItem(
                    $trade->partner_id, $ti->template_id, $ti->quantity,
                    $correlationId, false
                );
            }

            // Обмен предметами: партнёр → инициатору
            foreach ($partnerItems as $ti) {
                $remaining = $ti->quantity;
                $stacks = ItemInstance::where('owner_id', $trade->partner_id)
                    ->where('template_id', $ti->template_id)
                    ->orderBy('quantity', 'desc')
                    ->get();

                foreach ($stacks as $stack) {
                    if ($remaining <= 0) break;
                    $toRemove = min($remaining, $stack->quantity);
                    $this->inventoryService->removeItem(
                        $trade->partner_id, $stack->id, $toRemove,
                        $correlationId, 'trade', false
                    );
                    $remaining -= $toRemove;
                }

                $this->inventoryService->addItem(
                    $trade->initiator_id, $ti->template_id, $ti->quantity,
                    $correlationId, false
                );
            }

            $trade->update(['status' => 'completed']);

            // Формируем received_items с золотом
            $initiatorReceived = $partnerItems->map(fn($i) => [
                'template_id' => $i->template_id,
                'template_name' => $i->template->name,
                'template_type' => $i->template->type,
                'template_icon' => $i->template->icon,
                'description' => $i->template->description ?? '',
                'quantity' => $i->quantity,
            ])->toArray();
            if ($initiatorDelta > 0) {
                $initiatorReceived[] = ['template_name' => '💰 Золото', 'quantity' => $initiatorDelta];
            }

            $partnerReceived = $initiatorItems->map(fn($i) => [
                'template_id' => $i->template_id,
                'template_name' => $i->template->name,
                'template_type' => $i->template->type,
                'template_icon' => $i->template->icon,
                'description' => $i->template->description ?? '',
                'quantity' => $i->quantity,
            ])->toArray();
            if ($partnerDelta > 0) {
                $partnerReceived[] = ['template_name' => '💰 Золото', 'quantity' => $partnerDelta];
            }

            $initiatorGiven = $initiatorItems->map(fn($i) => [
                'template_id' => $i->template_id,
                'template_name' => $i->template->name,
                'template_type' => $i->template->type,
                'template_icon' => $i->template->icon,
                'description' => $i->template->description ?? '',
                'quantity' => $i->quantity,
            ])->toArray();
            if ($trade->initiator_gold > 0) {
                $initiatorGiven[] = ['template_name' => '💰 Золото', 'quantity' => $trade->initiator_gold];
            }

            $partnerGiven = $partnerItems->map(fn($i) => [
                'template_id' => $i->template_id,
                'template_name' => $i->template->name,
                'template_type' => $i->template->type,
                'template_icon' => $i->template->icon,
                'description' => $i->template->description ?? '',
                'quantity' => $i->quantity,
            ])->toArray();
            if ($trade->partner_gold > 0) {
                $partnerGiven[] = ['template_name' => '💰 Золото', 'quantity' => $trade->partner_gold];
            }

            $this->eventStore->record(
                GameEvent::TRADE_COMPLETED, 'trade', $trade->id,
                [
                    'trade_id' => $trade->id,
                    'side' => 'initiator',
                    'opponent_name' => $partner->name,
                    'received_items' => $initiatorReceived,
                    'given_items' => $initiatorGiven,
                ],
                $trade->initiator_id,
                $correlationId
            );

            $this->eventStore->record(
                GameEvent::TRADE_COMPLETED, 'trade', $trade->id,
                [
                    'trade_id' => $trade->id,
                    'side' => 'partner',
                    'opponent_name' => $initiator->name,
                    'received_items' => $partnerReceived,
                    'given_items' => $partnerGiven,
                ],
                $trade->partner_id,
                $correlationId
            );
        });
    }

    private function resetAcceptances(TradeOffer $trade): void
    {
        if ($trade->initiator_accepted || $trade->partner_accepted) {
            $trade->update([
                'initiator_accepted' => false,
                'partner_accepted' => false,
            ]);
        }
    }

    private function emitTradeUpdated(TradeOffer $trade, int $triggerUserId): void
    {
        $payload = [
            'trade_id' => $trade->id,
            'trigger_user_id' => $triggerUserId,
        ];
        $correlationId = Str::uuid()->toString();

        $this->eventStore->record(
            GameEvent::TRADE_UPDATED, 'trade', $trade->id,
            $payload, $trade->initiator_id, $correlationId
        );
        $this->eventStore->record(
            GameEvent::TRADE_UPDATED, 'trade', $trade->id,
            $payload, $trade->partner_id, $correlationId
        );
    }

    private function formatTrade(TradeOffer $trade, int $userId): array
    {
        $mySide = $trade->getSide($userId);
        $opponentId = $trade->getOpponentId($userId);
        $opponent = $opponentId ? User::find($opponentId) : null;

        return [
            'id' => $trade->id,
            'status' => $trade->status,
            'my_side' => $mySide,
            'opponent_id' => $opponentId,
            'opponent_name' => $opponent?->name ?? '???',
            'initiator' => [
                'id' => $trade->initiator_id,
                'name' => $trade->initiator->name,
                'accepted' => $trade->initiator_accepted,
                'gold' => $trade->initiator_gold,
                'items' => $trade->initiatorItems->map(fn($i) => [
                    'id' => $i->id,
                    'instance_id' => $i->item_instance_id,
                    'template_id' => $i->template_id,
                    'name' => $i->template->name,
                    'type' => $i->template->type,
                    'quantity' => $i->quantity,
                ])->values()->toArray(),
            ],
            'partner' => [
                'id' => $trade->partner_id,
                'name' => $trade->partner->name,
                'accepted' => $trade->partner_accepted,
                'gold' => $trade->partner_gold,
                'items' => $trade->partnerItems->map(fn($i) => [
                    'id' => $i->id,
                    'instance_id' => $i->item_instance_id,
                    'template_id' => $i->template_id,
                    'name' => $i->template->name,
                    'type' => $i->template->type,
                    'quantity' => $i->quantity,
                ])->values()->toArray(),
            ],
        ];
    }
}
