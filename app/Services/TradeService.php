<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\GameEvent;
use App\Models\ItemInstance;
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

    /**
     * Получить активные обмены игрока
     */
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

    /**
     * Получить детали обмена
     */
    public function getTrade(int $tradeId, int $userId): array
    {
        $trade = TradeOffer::with(['initiator', 'partner', 'items.template', 'items.instance'])
            ->findOrFail($tradeId);

        if (!$trade->isParticipant($userId)) {
            throw new \RuntimeException('Вы не участвуете в этом обмене');
        }

        return $this->formatTrade($trade, $userId);
    }

    /**
     * Создать обмен
     */
    public function createTrade(int $initiatorId, int $partnerId): TradeOffer
    {
        if ($initiatorId === $partnerId) {
            throw new \RuntimeException('Нельзя торговать с самим собой');
        }

        if (!User::find($partnerId)) {
            throw new \RuntimeException('Партнёр не найден');
        }

        // Проверяем, нет ли уже активного обмена
        $existing = TradeOffer::where(function ($q) use ($initiatorId, $partnerId) {
            $q->where(function ($sub) use ($initiatorId, $partnerId) {
                $sub->where('initiator_id', $initiatorId)
                    ->where('partner_id', $partnerId);
            })->orWhere(function ($sub) use ($initiatorId, $partnerId) {
                $sub->where('initiator_id', $partnerId)
                    ->where('partner_id', $initiatorId);
            });
        })->whereIn('status', ['pending', 'active'])->first();

        // ЛОГИРОВАНИЕ
        if ($existing) {
            \Log::warning('Найден существующий обмен', [
                'initiator_id' => $initiatorId,
                'partner_id' => $partnerId,
                'existing_trade' => [
                    'id' => $existing->id,
                    'initiator_id' => $existing->initiator_id,
                    'partner_id' => $existing->partner_id,
                    'status' => $existing->status,
                    'created_at' => $existing->created_at,
                    'updated_at' => $existing->updated_at,
                ],
                'sql' => TradeOffer::where(function ($q) use ($initiatorId, $partnerId) {
                    $q->where('initiator_id', $initiatorId)->where('partner_id', $partnerId);
                })->orWhere(function ($q) use ($initiatorId, $partnerId) {
                    $q->where('initiator_id', $partnerId)->where('partner_id', $initiatorId);
                })->whereIn('status', ['pending', 'active'])->toSql(),
            ]);
            throw new \RuntimeException('У вас уже есть активный обмен с этим игроком');
        }

        $trade = TradeOffer::create([
            'initiator_id' => $initiatorId,
            'partner_id' => $partnerId,
            'status' => 'active',
        ]);

        // ... остальной код без изменений
    }

    /**
     * Добавить предмет в обмен
     */
    public function addItem(int $userId, int $tradeId, int $instanceId, int $quantity = 1): TradeOffer
    {
        return DB::transaction(function () use ($userId, $tradeId, $instanceId, $quantity) {
            $trade = TradeOffer::findOrFail($tradeId);
            $side = $trade->getSide($userId);

            if (!$side) {
                throw new \RuntimeException('Вы не участвуете в этом обмене');
            }
            if ($trade->status !== 'active') {
                throw new \RuntimeException('Обмен не активен');
            }

            $item = ItemInstance::with('template')
                ->where('id', $instanceId)
                ->where('owner_id', $userId)
                ->firstOrFail();

            if ($item->template->type === 'recipe') {
                throw new \RuntimeException('Чертежи нельзя обменивать');
            }

            // Проверяем, что такой предмет уже не в обмене
            $existing = TradeItem::where('trade_id', $tradeId)
                ->where('side', $side)
                ->where('item_instance_id', $instanceId)
                ->first();

            if ($existing) {
                throw new \RuntimeException('Этот предмет уже в обмене');
            }

            // Для стакуемых — можно указать количество
            $addQuantity = $item->template->is_stackable ? $quantity : 1;

            if ($addQuantity > $item->quantity) {
                throw new \RuntimeException('Недостаточно количества');
            }

            TradeItem::create([
                'trade_id' => $tradeId,
                'side' => $side,
                'template_id' => $item->template_id,
                'item_instance_id' => $instanceId,
                'quantity' => $addQuantity,
            ]);

            // Сбрасываем подтверждения (защита от обмана)
            $this->resetAcceptances($trade);

            $this->emitTradeUpdated($trade, $userId);

            return $trade->fresh();
        });
    }

    /**
     * Убрать предмет из обмена
     */
    public function removeItem(int $userId, int $tradeId, int $tradeItemId): TradeOffer
    {
        return DB::transaction(function () use ($userId, $tradeId, $tradeItemId) {
            $trade = TradeOffer::findOrFail($tradeId);
            $side = $trade->getSide($userId);

            if (!$side) {
                throw new \RuntimeException('Вы не участвуете в этом обмене');
            }

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

    /**
     * Добавить золото в обмен
     */
    public function addGold(int $userId, int $tradeId, int $amount): TradeOffer
    {
        return DB::transaction(function () use ($userId, $tradeId, $amount) {
            $trade = TradeOffer::findOrFail($tradeId);
            $side = $trade->getSide($userId);

            if (!$side) {
                throw new \RuntimeException('Вы не участвуете в этом обмене');
            }
            if ($trade->status !== 'active') {
                throw new \RuntimeException('Обмен не активен');
            }
            if ($amount < 0) {
                throw new \RuntimeException('Сумма должна быть положительной');
            }

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

    /**
     * Подтвердить обмен
     */
    public function accept(int $userId, int $tradeId): TradeOffer
    {
        return DB::transaction(function () use ($userId, $tradeId) {
            $trade = TradeOffer::findOrFail($tradeId);
            $side = $trade->getSide($userId);

            if (!$side) {
                throw new \RuntimeException('Вы не участвуете в этом обмене');
            }
            if ($trade->status !== 'active') {
                throw new \RuntimeException('Обмен не активен');
            }

            $field = $side === 'initiator' ? 'initiator_accepted' : 'partner_accepted';
            $trade->update([$field => true]);

            $correlationId = Str::uuid()->toString();
            $this->eventStore->record(
                GameEvent::TRADE_ACCEPTED,
                'trade',
                $trade->id,
                [
                    'trade_id' => $trade->id,
                    'user_id' => $userId,
                    'side' => $side,
                ],
                $userId,
                $correlationId
            );

            $trade = $trade->fresh();

            // Если обе стороны подтвердили — выполняем обмен
            if ($trade->initiator_accepted && $trade->partner_accepted) {
                $this->executeTrade($trade);
            } else {
                $this->emitTradeUpdated($trade, $userId);
            }

            return $trade->fresh();
        });
    }

    /**
     * Отменить обмен
     */
    public function cancel(int $userId, int $tradeId): TradeOffer
    {
        $trade = TradeOffer::findOrFail($tradeId);
        $side = $trade->getSide($userId);

        if (!$side) {
            throw new \RuntimeException('Вы не участвуете в этом обмене');
        }
        if (!in_array($trade->status, ['pending', 'active'])) {
            throw new \RuntimeException('Обмен уже завершён');
        }

        $trade->update(['status' => 'cancelled']);

        $correlationId = Str::uuid()->toString();
        $this->eventStore->record(
            GameEvent::TRADE_CANCELLED,
            'trade',
            $trade->id,
            [
                'trade_id' => $trade->id,
                'cancelled_by' => $userId,
            ],
            $userId,
            $correlationId
        );

        return $trade;
    }

    /**
     * Выполнить обмен (атомарно)
     */
    private function executeTrade(TradeOffer $trade): void
    {
        DB::transaction(function () use ($trade) {
            $correlationId = Str::uuid()->toString();

            $initiator = User::findOrFail($trade->initiator_id);
            $partner = User::findOrFail($trade->partner_id);

            // 1. Проверяем, что все предметы всё ещё в инвентарях
            $initiatorItems = $trade->initiatorItems;
            $partnerItems = $trade->partnerItems;

            foreach ($initiatorItems as $ti) {
                $instance = ItemInstance::where('id', $ti->item_instance_id)
                    ->where('owner_id', $trade->initiator_id)
                    ->first();
                if (!$instance || $instance->quantity < $ti->quantity) {
                    throw new \RuntimeException('Предметы инициатора больше недоступны');
                }
            }

            foreach ($partnerItems as $ti) {
                $instance = ItemInstance::where('id', $ti->item_instance_id)
                    ->where('owner_id', $trade->partner_id)
                    ->first();
                if (!$instance || $instance->quantity < $ti->quantity) {
                    throw new \RuntimeException('Предметы партнёра больше недоступны');
                }
            }

            // 2. Проверяем золото
            if ($initiator->gold < $trade->initiator_gold) {
                throw new \RuntimeException('У инициатора недостаточно золота');
            }
            if ($partner->gold < $trade->partner_gold) {
                throw new \RuntimeException('У партнёра недостаточно золота');
            }

            // 3. Обмен золотом (разница)
            $initiatorDelta = $trade->partner_gold - $trade->initiator_gold;
            $partnerDelta = $trade->initiator_gold - $trade->partner_gold;

            if ($initiatorDelta !== 0) {
                $initiator->increment('gold', $initiatorDelta);
            }
            if ($partnerDelta !== 0) {
                $partner->increment('gold', $partnerDelta);
            }

            // 4. Обмен предметами: инициатор → партнёру
            foreach ($initiatorItems as $ti) {
                // Снимаем у инициатора (без события)
                $this->inventoryService->removeItem(
                    $trade->initiator_id,
                    $ti->item_instance_id,
                    $ti->quantity,
                    $correlationId,
                    'trade',
                    false
                );
                // Добавляем партнёру (без события)
                $this->inventoryService->addItem(
                    $trade->partner_id,
                    $ti->template_id,
                    $ti->quantity,
                    $correlationId,
                    false
                );
            }

            // 5. Обмен предметами: партнёр → инициатору
            foreach ($partnerItems as $ti) {
                $this->inventoryService->removeItem(
                    $trade->partner_id,
                    $ti->item_instance_id,
                    $ti->quantity,
                    $correlationId,
                    'trade',
                    false
                );
                $this->inventoryService->addItem(
                    $trade->initiator_id,
                    $ti->template_id,
                    $ti->quantity,
                    $correlationId,
                    false
                );
            }

            // 6. Обновляем статус
            $trade->update(['status' => 'completed']);

            // 7. События для обоих игроков
            $this->eventStore->record(
                GameEvent::TRADE_COMPLETED,
                'trade',
                $trade->id,
                [
                    'trade_id' => $trade->id,
                    'side' => 'initiator',
                    'opponent_name' => $partner->name,
                    'received_items' => $partnerItems->map(fn($i) => [
                        'name' => $i->template->name,
                        'quantity' => $i->quantity,
                    ])->toArray(),
                    'given_items' => $initiatorItems->map(fn($i) => [
                        'name' => $i->template->name,
                        'quantity' => $i->quantity,
                    ])->toArray(),
                    'gold_delta' => $initiatorDelta,
                ],
                $trade->initiator_id,
                $correlationId
            );

            $this->eventStore->record(
                GameEvent::TRADE_COMPLETED,
                'trade',
                $trade->id,
                [
                    'trade_id' => $trade->id,
                    'side' => 'partner',
                    'opponent_name' => $initiator->name,
                    'received_items' => $initiatorItems->map(fn($i) => [
                        'name' => $i->template->name,
                        'quantity' => $i->quantity,
                    ])->toArray(),
                    'given_items' => $partnerItems->map(fn($i) => [
                        'name' => $i->template->name,
                        'quantity' => $i->quantity,
                    ])->toArray(),
                    'gold_delta' => $partnerDelta,
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

    private function emitTradeUpdated(TradeOffer $trade, int $userId): void
    {
        $this->eventStore->record(
            GameEvent::TRADE_UPDATED,
            'trade',
            $trade->id,
            ['trade_id' => $trade->id],
            $userId,
            Str::uuid()->toString()
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
                    'template_id' => $i->template_id,
                    'name' => $i->template->name,
                    'type' => $i->template->type,
                    'quantity' => $i->quantity,
                ])->values()->toArray(),
            ],
        ];
    }
}
