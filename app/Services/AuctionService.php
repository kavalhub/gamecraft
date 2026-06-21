<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuctionHistory;
use App\Models\AuctionLot;
use App\Models\GameEvent;
use App\Models\ItemInstance;
use App\Models\ItemTemplate;
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
     * Получить все активные лоты
     */
    public function getActiveLots(?string $type = null, ?int $templateId = null): array
    {
        $query = AuctionLot::with(['template', 'seller'])
            ->where('status', 'active')
            ->orderBy('created_at', 'desc');

        if ($type) {
            $query->whereHas('template', function ($q) use ($type) {
                $q->where('type', $type);
            });
        }

        if ($templateId) {
            $query->where('template_id', $templateId);
        }

        return $query->get()
            ->map(fn(AuctionLot $lot) => [
                'id' => $lot->id,
                'seller_id' => $lot->seller_id,
                'seller_name' => $lot->seller->name ?? '???',
                'template_id' => $lot->template_id,
                'template_name' => $lot->template->name ?? '???',
                'template_type' => $lot->template->type ?? 'material',
                'template_icon' => $lot->template->icon ?? '📦',
                'description' => $lot->template->description ?? '',
                'quantity' => $lot->quantity,
                'price' => $lot->price,
                'item_stats' => $lot->item_stats ?? [],
                'created_at' => $lot->created_at?->format('Y-m-d H:i:s'),
            ])
            ->toArray();
    }

    /**
     * Получить мои лоты
     */
    public function getMyLots(int $userId): array
    {
        return AuctionLot::with(['template'])
            ->where('seller_id', $userId)
            ->whereIn('status', ['active', 'sold', 'cancelled'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn(AuctionLot $lot) => [
                'id' => $lot->id,
                'status' => $lot->status,
                'template_id' => $lot->template_id,
                'template_name' => $lot->template->name ?? '???',
                'template_type' => $lot->template->type ?? 'material',
                'template_icon' => $lot->template->icon ?? '📦',
                'description' => $lot->template->description ?? '',
                'quantity' => $lot->quantity,
                'price' => $lot->price,
                'buyer_id' => $lot->buyer_id,
                'item_stats' => $lot->item_stats ?? [],
                'created_at' => $lot->created_at?->format('Y-m-d H:i:s'),
            ])
            ->toArray();
    }

    /**
     * Выставить лот
     */
    public function listLot(int $sellerId, int $templateId, int $price, int $quantity = 1): AuctionLot
    {
        if ($price <= 0) throw new \RuntimeException('Цена должна быть больше нуля');
        if ($quantity <= 0) throw new \RuntimeException('Количество должно быть больше нуля');

        return DB::transaction(function () use ($sellerId, $templateId, $price, $quantity) {
            $template = ItemTemplate::findOrFail($templateId);

            $items = ItemInstance::where('owner_id', $sellerId)
                ->where('template_id', $templateId)
                ->orderBy('quantity', 'desc')
                ->get();

            $totalAvailable = $items->sum('quantity');
            if ($totalAvailable < $quantity) {
                throw new \RuntimeException("В инвентаре только {$totalAvailable} шт., нельзя выставить {$quantity}");
            }

            if (!$template->is_stackable) {
                $quantity = 1;
            }

            $firstInstance = $items->first();

            $lot = AuctionLot::create([
                'seller_id' => $sellerId,
                'template_id' => $templateId,
                'quantity' => $quantity,
                'item_instance_id' => $template->is_stackable ? null : $firstInstance->id,
                'item_stats' => $firstInstance->stats,
                'price' => $price,
                'commission_percent' => 5,
                'status' => 'active',
            ]);

            $remaining = $quantity;
            foreach ($items as $item) {
                if ($remaining <= 0) break;
                $toRemove = min($remaining, $item->quantity);
                $this->inventoryService->removeItem(
                    $sellerId, $item->id, $toRemove, null, 'auction_list', false
                );
                $remaining -= $toRemove;
            }

            $this->eventStore->recordItemEvent(
                GameEvent::ITEM_REMOVED,
                $sellerId,
                $templateId,
                $firstInstance->id,
                [
                    'quantity' => $quantity,
                    'reason' => 'auction_list',
                ],
                Str::uuid()->toString()
            );

            $this->eventStore->record(
                GameEvent::AUCTION_LISTED,
                'auction',
                $lot->id,
                [
                    'seller_id' => $sellerId,
                    'template_id' => $templateId,
                    'template_name' => $template->name,
                    'template_type' => $template->type,
                    'template_icon' => $template->icon,
                    'description' => $template->description ?? '',
                    'quantity' => $quantity,
                    'price' => $price,
                ],
                $sellerId,
                Str::uuid()->toString()
            );

            AuctionHistory::create([
                'lot_id' => $lot->id,
                'seller_id' => $sellerId,
                'template_id' => $templateId,
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
    public function buyLot(int $buyerId, int $lotId): void
    {
        DB::transaction(function () use ($buyerId, $lotId) {
            $lot = AuctionLot::with('template')->findOrFail($lotId);

            if ($lot->status !== 'active') {
                throw new \RuntimeException('Лот не активен');
            }

            if ($lot->seller_id === $buyerId) {
                throw new \RuntimeException('Нельзя купить свой лот');
            }

            $buyer = User::findOrFail($buyerId);
            $seller = User::findOrFail($lot->seller_id);

            if ($buyer->gold < $lot->price) {
                throw new \RuntimeException('Недостаточно золота');
            }

            $correlationId = Str::uuid()->toString();

            $buyer->decrement('gold', $lot->price);

            $commission = (int)($lot->price * $lot->commission_percent / 100);
            $sellerReceived = $lot->price - $commission;

            $seller->increment('gold', $sellerReceived);

            if ($lot->template->is_stackable) {
                $this->inventoryService->addItem(
                    $buyerId,
                    $lot->template_id,
                    $lot->quantity,
                    $correlationId,
                    false
                );
            } else {
                ItemInstance::create([
                    'template_id' => $lot->template_id,
                    'owner_id' => $buyerId,
                    'quantity' => 1,
                    'durability' => 100,
                    'stats' => $lot->item_stats ?? [],
                ]);
            }

            $lot->update([
                'status' => 'sold',
                'buyer_id' => $buyerId,
            ]);

            $this->eventStore->recordItemEvent(
                GameEvent::ITEM_RECEIVED,
                $buyerId,
                $lot->template_id,
                null,
                [
                    'quantity' => $lot->quantity,
                    'reason' => 'auction_purchase',
                    'payment_amount' => $lot->price,
                    'new_gold_balance' => $buyer->fresh()->gold,
                    'seller_name' => $seller->name,
                ],
                $correlationId
            );

            $this->eventStore->record(
                GameEvent::AUCTION_SALE,
                'auction',
                $lot->id,
                [
                    'lot_id' => $lot->id,
                    'seller_id' => $lot->seller_id,
                    'buyer_id' => $buyerId,
                    'buyer_name' => $buyer->name,
                    'template_id' => $lot->template_id,
                    'template_name' => $lot->template->name,
                    'template_type' => $lot->template->type,
                    'template_icon' => $lot->template->icon,
                    'description' => $lot->template->description ?? '',
                    'quantity' => $lot->quantity,
                    'payment_amount' => $lot->price,
                    'commission' => $commission,
                    'seller_received' => $sellerReceived,
                    'new_gold_balance' => $seller->fresh()->gold,
                ],
                $lot->seller_id,
                $correlationId
            );

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
        });
    }

    /**
     * Отменить лот
     */
    public function cancelLot(int $sellerId, int $lotId): void
    {
        DB::transaction(function () use ($sellerId, $lotId) {
            $lot = AuctionLot::with('template')->findOrFail($lotId);

            if ($lot->seller_id !== $sellerId) {
                throw new \RuntimeException('Вы не владелец этого лота');
            }

            if ($lot->status !== 'active') {
                throw new \RuntimeException('Лот не активен');
            }

            $correlationId = Str::uuid()->toString();

            if ($lot->template->is_stackable) {
                $this->inventoryService->addItem(
                    $sellerId,
                    $lot->template_id,
                    $lot->quantity,
                    $correlationId,
                    false
                );
            } else {
                ItemInstance::create([
                    'template_id' => $lot->template_id,
                    'owner_id' => $sellerId,
                    'quantity' => 1,
                    'durability' => 100,
                    'stats' => $lot->item_stats ?? [],
                ]);
            }

            $lot->update(['status' => 'cancelled']);

            $this->eventStore->recordItemEvent(
                GameEvent::ITEM_RECEIVED,
                $sellerId,
                $lot->template_id,
                null,
                [
                    'quantity' => $lot->quantity,
                    'reason' => 'auction_cancelled',
                ],
                $correlationId
            );

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
        });
    }
}
