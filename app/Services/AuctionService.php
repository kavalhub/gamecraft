<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuctionHistory;
use App\Models\AuctionLot;
use App\Models\GameEvent;
use App\Models\ItemInstance;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AuctionService
{
    public function __construct(
        private InventoryService $inventoryService,
        private EventStore $eventStore
    ) {}

    /**
     * Получить список активных лотов (с фильтрами)
     */
    public function getActiveLots(?string $type = null, ?int $templateId = null, int $limit = 50): array
    {
        $query = AuctionLot::with(['seller', 'template'])
            ->where('status', 'active');

        if ($type) {
            $query->whereHas('template', fn($q) => $q->where('type', $type));
        }

        if ($templateId) {
            $query->where('template_id', $templateId);
        }

        return $query->orderBy('price')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(fn(AuctionLot $lot) => [
                'id' => $lot->id,
                'seller_id' => $lot->seller_id,
                'seller_name' => $lot->seller->name,
                'template_id' => $lot->template_id,
                'item_name' => $lot->template->name,
                'item_type' => $lot->template->type,
                'item_icon' => $lot->template->icon,
                'quantity' => $lot->quantity,
                'price' => $lot->price,
                'commission' => $lot->commission,
                'seller_received' => $lot->seller_received,
                'created_at' => $lot->created_at->toIso8601String(),
            ])
            ->toArray();
    }

    /**
     * Получить мои лоты (активные + проданные)
     */
    public function getMyLots(int $userId): array
    {
        return AuctionLot::with(['buyer', 'template'])
            ->where('seller_id', $userId)
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get()
            ->map(fn(AuctionLot $lot) => [
                'id' => $lot->id,
                'status' => $lot->status,
                'item_name' => $lot->template->name,
                'item_type' => $lot->template->type,
                'quantity' => $lot->quantity,
                'price' => $lot->price,
                'buyer_name' => $lot->buyer?->name,
                'sold_at' => $lot->sold_at?->toIso8601String(),
                'created_at' => $lot->created_at->toIso8601String(),
            ])
            ->toArray();
    }

    /**
     * Выставить предмет на аукцион
     */
    public function listLot(int $sellerId, int $instanceId, int $price, int $quantity = 1): AuctionLot
    {
        if ($price <= 0) {
            throw new \RuntimeException('Цена должна быть больше нуля');
        }
        if ($quantity <= 0) {
            throw new \RuntimeException('Количество должно быть больше нуля');
        }

        return DB::transaction(function () use ($sellerId, $instanceId, $price, $quantity) {
            $item = ItemInstance::with('template')
                ->where('id', $instanceId)
                ->where('owner_id', $sellerId)
                ->firstOrFail();

            // Для не-стакуемых предметов количество всегда 1
            if (!$item->template->is_stackable) {
                $quantity = 1;
            } else {
                // Проверяем, что количество не больше, чем в инвентаре
                if ($quantity > $item->quantity) {
                    throw new \RuntimeException(
                        "В инвентаре только {$item->quantity} шт., нельзя выставить {$quantity}"
                    );
                }
            }

            // Создаём лот
            $lot = AuctionLot::create([
                'seller_id' => $sellerId,
                'template_id' => $item->template_id,
                'quantity' => $quantity,
                'item_instance_id' => $item->template->is_stackable ? null : $item->id,
                'item_stats' => $item->stats,
                'price' => $price,
                'commission_percent' => 5,
                'status' => 'active',
            ]);

            // Удаляем предмет из инвентаря продавца (только указанное количество)
            $this->inventoryService->removeItem(
                $sellerId,
                $instanceId,
                $quantity,
                null,
                'auction_list'
            );

            // Событие
            $correlationId = Str::uuid()->toString();
            $this->eventStore->record(
                GameEvent::AUCTION_LISTED,
                'auction',
                $lot->id,
                [
                    'seller_id' => $sellerId,
                    'template_id' => $item->template_id,
                    'template_name' => $item->template->name,
                    'quantity' => $quantity,
                    'price' => $price,
                ],
                $sellerId,
                $correlationId
            );

            AuctionHistory::create([
                'lot_id' => $lot->id,
                'seller_id' => $sellerId,
                'template_id' => $item->template_id,
                'quantity' => $quantity,
                'price' => $price,
                'commission' => 0,
                'seller_received' => 0,
                'action' => 'listed',
                'occurred_at' => now(),
            ]);

            return $lot;
        });
    }

    /**
     * Купить лот
     */
    public function buyLot(int $buyerId, int $lotId): array
    {
        return DB::transaction(function () use ($buyerId, $lotId) {
            $lot = AuctionLot::with('template')->findOrFail($lotId);

            if (!$lot->isActive()) {
                throw new \RuntimeException('Лот уже не активен');
            }

            if ($lot->seller_id === $buyerId) {
                throw new \RuntimeException('Нельзя купить свой собственный лот');
            }

            $buyer = User::findOrFail($buyerId);

            if ($buyer->gold < $lot->price) {
                throw new \RuntimeException('Недостаточно золота');
            }

            $correlationId = Str::uuid()->toString();

            // 1. Снимаем золото с покупателя (БЕЗ события)
            $buyer->decrement('gold', $lot->price);
            $buyer->refresh();

            // 2. Начисляем золото продавцу (БЕЗ события)
            $seller = User::findOrFail($lot->seller_id);
            $commission = $lot->commission;
            $sellerReceived = $lot->seller_received;

            $seller->increment('gold', $sellerReceived);
            $seller->refresh();

            // 3. Передаём предмет покупателю (БЕЗ события)
            $this->inventoryService->addItem(
                $buyerId,
                $lot->template_id,
                $lot->quantity,
                $correlationId,
                false // НЕ записываем событие
            );

            // 4. Обновляем статус лота
            $lot->update([
                'status' => 'sold',
                'buyer_id' => $buyerId,
                'sold_at' => now(),
            ]);

            // 5. Событие ПОКУПКИ (для покупателя)
            $this->eventStore->record(
                GameEvent::AUCTION_PURCHASE,
                'user',
                $buyerId,
                [
                    'lot_id' => $lot->id,
                    'seller_id' => $lot->seller_id,
                    'seller_name' => $seller->name,
                    'item_name' => $lot->template->name,
                    'item_type' => $lot->template->type,
                    'quantity' => $lot->quantity,
                    'payment_type' => 'gold',
                    'payment_amount' => $lot->price,
                    'new_gold_balance' => $buyer->gold,
                ],
                $buyerId,
                $correlationId
            );

            // 6. Событие ПРОДАЖИ (для продавца)
            $this->eventStore->record(
                GameEvent::AUCTION_SALE,
                'user',
                $lot->seller_id,
                [
                    'lot_id' => $lot->id,
                    'buyer_id' => $buyerId,
                    'buyer_name' => $buyer->name,
                    'item_name' => $lot->template->name,
                    'item_type' => $lot->template->type,
                    'quantity' => $lot->quantity,
                    'payment_type' => 'gold',
                    'payment_amount' => $sellerReceived,
                    'commission' => $commission,
                    'new_gold_balance' => $seller->gold,
                ],
                $lot->seller_id,
                $correlationId
            );

            // 7. Запись в историю
            AuctionHistory::create([
                'lot_id' => $lot->id,
                'seller_id' => $lot->seller_id,
                'buyer_id' => $buyerId,
                'template_id' => $lot->template_id,
                'quantity' => $lot->quantity,
                'price' => $lot->price,
                'commission' => $commission,
                'seller_received' => $sellerReceived,
                'action' => 'sold',
                'occurred_at' => now(),
            ]);

            return [
                'message' => 'Лот куплен!',
                'lot' => [
                    'item_name' => $lot->template->name,
                    'quantity' => $lot->quantity,
                    'price' => $lot->price,
                ],
            ];
        });
    }

    /**
     * Отменить свой лот
     */
    public function cancelLot(int $sellerId, int $lotId): array
    {
        return DB::transaction(function () use ($sellerId, $lotId) {
            $lot = AuctionLot::with('template')->findOrFail($lotId);

            if ($lot->seller_id !== $sellerId) {
                throw new \RuntimeException('Это не ваш лот');
            }

            if (!$lot->isActive()) {
                throw new \RuntimeException('Лот уже не активен');
            }

            $correlationId = Str::uuid()->toString();

            // Возвращаем предмет продавцу
            $this->inventoryService->addItem(
                $sellerId,
                $lot->template_id,
                $lot->quantity,
                $correlationId
            );

            // Обновляем статус
            $lot->update(['status' => 'cancelled']);

            // Событие
            $this->eventStore->record(
                GameEvent::AUCTION_CANCELLED,
                'auction',
                $lot->id,
                [
                    'lot_id' => $lot->id,
                    'seller_id' => $sellerId,
                    'template_id' => $lot->template_id,
                    'template_name' => $lot->template->name,
                    'quantity' => $lot->quantity,
                    'price' => $lot->price,
                ],
                $sellerId,
                $correlationId
            );

            // Запись в историю
            AuctionHistory::create([
                'lot_id' => $lot->id,
                'seller_id' => $sellerId,
                'template_id' => $lot->template_id,
                'quantity' => $lot->quantity,
                'price' => $lot->price,
                'commission' => 0,
                'seller_received' => 0,
                'action' => 'cancelled',
                'occurred_at' => now(),
            ]);

            return [
                'message' => 'Лот отменён, предмет возвращён',
            ];
        });
    }
}
