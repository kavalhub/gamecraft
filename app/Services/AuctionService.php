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

    public function getActiveLots(?string $type = null, ?int $templateId = null): array
    {
        $query = AuctionLot::with(['template', 'seller'])
            ->where('status', 'active');

        if ($type) {
            $query->whereHas('template', function ($q) use ($type) {
                $q->where('type', $type);
            });
        }

        if ($templateId) {
            $query->where('template_id', $templateId);
        }

        return $query->orderBy('created_at', 'desc')
            ->get()
            ->map(fn(AuctionLot $lot) => [
                'id' => $lot->id,
                'seller_id' => $lot->seller_id,
                'seller_name' => $lot->seller?->name ?? 'Неизвестный',
                'template_id' => $lot->template_id,
                'template_name' => $lot->template->name,
                'template_type' => $lot->template->type,
                'template_icon' => $lot->template->icon,
                'description' => $lot->template->description ?? '',
                'quantity' => $lot->quantity,
                'price' => $lot->price,
                'item_stats' => $lot->item_stats ?? [],
                'is_infinite' => $lot->is_infinite,
                'created_at' => $lot->created_at->toDateTimeString(),
            ])
            ->toArray();
    }

    public function getMyLots(int $userId): array
    {
        return AuctionLot::with(['template', 'buyer'])
            ->where('seller_id', $userId)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn(AuctionLot $lot) => [
                'id' => $lot->id,
                'template_id' => $lot->template_id,
                'template_name' => $lot->template->name,
                'template_type' => $lot->template->type,
                'template_icon' => $lot->template->icon,
                'description' => $lot->template->description ?? '',
                'quantity' => $lot->quantity,
                'price' => $lot->price,
                'status' => $lot->status,
                'buyer_name' => $lot->buyer?->name,
                'is_infinite' => $lot->is_infinite,
            ])
            ->toArray();
    }

    public function listLot(int $userId, int $templateId, int $quantity, int $price): AuctionLot
    {
        return DB::transaction(function () use ($userId, $templateId, $quantity, $price) {
            if ($price <= 0) {
                throw new \RuntimeException('Цена должна быть больше нуля');
            }

            if ($quantity <= 0) {
                throw new \RuntimeException('Количество должно быть больше нуля');
            }

            $available = ItemInstance::where('owner_id', $userId)
                ->where('template_id', $templateId)
                ->sum('quantity');

            if ($available < $quantity) {
                throw new \RuntimeException("В инвентаре только {$available} предметов, нужно {$quantity}");
            }

            $remaining = $quantity;
            $stacks = ItemInstance::where('owner_id', $userId)
                ->where('template_id', $templateId)
                ->orderBy('quantity', 'asc')
                ->get();

            foreach ($stacks as $stack) {
                if ($remaining <= 0) break;
                $toRemove = min($remaining, $stack->quantity);
                $this->inventoryService->removeItem(
                    $userId,
                    $stack->id,
                    $toRemove,
                    null,
                    'auction_list',
                    false
                );
                $remaining -= $toRemove;
            }

            $lot = AuctionLot::create([
                'seller_id' => $userId,
                'template_id' => $templateId,
                'quantity' => $quantity,
                'price' => $price,
                'status' => 'active',
                'is_infinite' => false,
            ]);

            $this->eventStore->record(
                GameEvent::AUCTION_LISTED,
                'auction',
                $lot->id,
                [
                    'seller_id' => $userId,
                    'template_id' => $templateId,
                    'template_name' => $lot->template->name ?? '',
                    'template_type' => $lot->template->type ?? '',
                    'template_icon' => $lot->template->icon ?? '',
                    'quantity' => $quantity,
                    'price' => $price,
                ],
                $userId
            );

            return $lot;
        });
    }

    public function buyLot(int $buyerId, int $lotId): array
    {
        return DB::transaction(function () use ($buyerId, $lotId) {
            $lot = AuctionLot::with(['template', 'seller'])->findOrFail($lotId);

            if ($lot->status !== 'active') {
                throw new \RuntimeException('Лот не активен');
            }

            if ($lot->seller_id === $buyerId) {
                throw new \RuntimeException('Нельзя купить свой собственный лот');
            }

            $buyer = User::findOrFail($buyerId);
            $totalPrice = $lot->price * $lot->quantity;

            if ($buyer->gold < $totalPrice) {
                throw new \RuntimeException('Недостаточно золота');
            }

            $correlationId = Str::uuid()->toString();

            // Снимаем золото с покупателя
            $buyer->decrement('gold', $totalPrice);

            if ($lot->is_infinite) {
                // Бесконечный лот - создаём предмет заново, лот остаётся активным
                $this->inventoryService->addItem($buyerId, $lot->template_id, $lot->quantity, $correlationId);
                
                $newBalance = $buyer->fresh()->gold;

                // Золото сжигается (не идёт продавцу)
                $this->eventStore->record(
                    GameEvent::USER_GOLD_CHANGED,
                    'user',
                    $buyerId,
                    [
                        'delta' => -$totalPrice,
                        'new_balance' => $newBalance,
                        'reason' => 'shop_purchase',
                    ],
                    $buyerId,
                    $correlationId
                );

                // Записываем в историю, но лот остаётся active
                AuctionHistory::create([
                    'lot_id' => $lot->id,
                    'seller_id' => $lot->seller_id,
                    'buyer_id' => $buyerId,
                    'template_id' => $lot->template_id,
                    'quantity' => $lot->quantity,
                    'price' => $lot->price,
                    'commission' => 0,
                    'seller_received' => 0,
                    'action' => 'sold',
                    'occurred_at' => now(),
                ]);

                $this->eventStore->record(
                    GameEvent::AUCTION_PURCHASE,
                    'auction',
                    $lot->id,
                    [
                        'buyer_id' => $buyerId,
                        'seller_id' => $lot->seller_id,
                        'seller_name' => $lot->seller?->name ?? 'Неизвестный',
                        'template_id' => $lot->template_id,
                        'template_name' => $lot->template->name,
                        'template_type' => $lot->template->type,
                        'template_icon' => $lot->template->icon,
                        'description' => $lot->template->description ?? '',
                        'quantity' => $lot->quantity,
                        'payment_amount' => $totalPrice,
                        'new_gold_balance' => $newBalance,
                        'is_infinite' => true,
                    ],
                    $buyerId,
                    $correlationId
                );

                return [
                    'lot' => $lot,
                    'buyer' => $buyer->fresh(),
                ];
            } else {
                // Обычный лот - создаём предметы для покупателя
                $this->inventoryService->addItem($buyerId, $lot->template_id, $lot->quantity, $correlationId);

                $seller = User::findOrFail($lot->seller_id);

                // Выплачиваем продавцу с комиссией
                $commission = (int)($totalPrice * $lot->commission_percent / 100);
                $sellerReceived = $totalPrice - $commission;
                $seller->increment('gold', $sellerReceived);

                $this->eventStore->record(
                    GameEvent::USER_GOLD_CHANGED,
                    'user',
                    $seller->id,
                    [
                        'delta' => $sellerReceived,
                        'new_balance' => $seller->fresh()->gold,
                        'reason' => 'auction_sale',
                    ],
                    $seller->id,
                    $correlationId
                );

                // Обновляем статус лота
                $lot->update([
                    'status' => 'sold',
                    'buyer_id' => $buyerId,
                    'sold_at' => now(),
                ]);

                // Записываем в историю
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

                $this->eventStore->record(
                    GameEvent::AUCTION_PURCHASE,
                    'auction',
                    $lot->id,
                    [
                        'buyer_id' => $buyerId,
                        'buyer_name' => $buyer->name,
                        'seller_id' => $lot->seller_id,
                        'seller_name' => $lot->seller?->name ?? 'Неизвестный',
                        'template_id' => $lot->template_id,
                        'template_name' => $lot->template->name,
                        'template_type' => $lot->template->type,
                        'template_icon' => $lot->template->icon,
                        'description' => $lot->template->description ?? '',
                        'quantity' => $lot->quantity,
                        'payment_amount' => $totalPrice,
                        'commission' => $commission,
                        'new_gold_balance' => $buyer->fresh()->gold,
                        'is_infinite' => false,
                    ],
                    $buyerId,
                    $correlationId
                );

                return [
                    'lot' => $lot,
                    'buyer' => $buyer->fresh(),
                ];
            }
        });
    }

    public function cancelLot(int $userId, int $lotId): AuctionLot
    {
        return DB::transaction(function () use ($userId, $lotId) {
            $lot = AuctionLot::findOrFail($lotId);

            if ($lot->seller_id !== $userId) {
                throw new \RuntimeException('Вы не можете отменить чужой лот');
            }

            if ($lot->status !== 'active') {
                throw new \RuntimeException('Можно отменить только активный лот');
            }

            if ($lot->is_infinite) {
                throw new \RuntimeException('Нельзя отменить магазинный лот');
            }

            // Возвращаем предметы продавцу
            $this->inventoryService->addItem($userId, $lot->template_id, $lot->quantity, null);

            $lot->update(['status' => 'cancelled']);

            AuctionHistory::create([
                'lot_id' => $lot->id,
                'seller_id' => $userId,
                'buyer_id' => null,
                'template_id' => $lot->template_id,
                'quantity' => $lot->quantity,
                'price' => $lot->price,
                'commission' => 0,
                'seller_received' => 0,
                'action' => 'cancelled',
                'occurred_at' => now(),
            ]);

            $this->eventStore->record(
                GameEvent::AUCTION_CANCELLED,
                'auction',
                $lot->id,
                [
                    'seller_id' => $userId,
                    'template_id' => $lot->template_id,
                    'template_name' => $lot->template->name ?? '',
                    'template_type' => $lot->template->type ?? '',
                    'template_icon' => $lot->template->icon ?? '',
                    'quantity' => $lot->quantity,
                ],
                $userId
            );

            return $lot;
        });
    }
}
