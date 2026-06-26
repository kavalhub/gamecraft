<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuctionLot;
use App\Models\Character;
use App\Models\Item;
use App\Models\Resource;
use App\Models\Slot;
use App\Models\Storage;
use App\Models\TemporarySlot;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AuctionService
{
    public function __construct(
        private InventoryService $inventoryService,
        private EventStore $eventStore
    ) {}

    /**
     * Получить список активных лотов
     */
    public function getActiveLots(?string $templateSlug = null): Collection
    {
        $query = AuctionLot::with(['seller', 'template'])
            ->where('status', 'active');

        if ($templateSlug) {
            $query->where('template_slug', $templateSlug);
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    /**
     * Получить лоты продавца
     */
    public function getMyLots(Character $seller): Collection
    {
        return AuctionLot::with('template')
            ->where('seller_uuid', $seller->uuid)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Шаг 1: Поместить предмет во временный слот аукциона (предпросмотр)
     */
    public function prepareLot(
        Character $seller,
        string $itemUuid,
        int $price
    ): TemporarySlot {
        return DB::transaction(function () use ($seller, $itemUuid, $price) {
            if ($price < 1) {
                throw new \RuntimeException('Цена должна быть больше 0');
            }

            $item = Item::where('uuid', $itemUuid)
                ->whereIn('stage', ['item', 'blueprint'])
                ->whereNull('temporary_slot_uuid')
                ->firstOrFail();

            // Проверяем, что предмет принадлежит продавцу
            $slot = Slot::where('uuid', $item->slot_uuid)->firstOrFail();
            $storage = Storage::where('uuid', $slot->storage_uuid)->firstOrFail();
            if ($storage->characters_uuid !== $seller->uuid) {
                throw new \RuntimeException('Предмет не принадлежит продавцу');
            }

            // Находим или создаём хранилище аукциона
            $auctionCharacter = Character::where('character_type', 'auction')->firstOrFail();
            $auctionStorage = Storage::firstOrCreate(
                [
                    'characters_uuid' => $auctionCharacter->uuid,
                    'storage_type' => 'auction',
                ],
                ['name' => 'Аукцион', 'active' => true]
            );

            // Создаём временный слот
            $temporarySlot = TemporarySlot::create([
                'uuid' => Str::uuid()->toString(),
                'storage_uuid' => $auctionStorage->uuid,
                'character_uuid' => $seller->uuid,
                'active' => true,
                'timestamps_end' => now()->addMinutes(5), // 5 минут на подтверждение
            ]);

            // Привязываем предмет к временному слоту (но slot_uuid остаётся у продавца!)
            $item->update(['temporary_slot_uuid' => $temporarySlot->uuid]);

            $this->eventStore->record(
                'auction.prepared',
                'item',
                $item->uuid,
                [
                    'price' => $price,
                    'temporary_slot_uuid' => $temporarySlot->uuid,
                    'expires_at' => $temporarySlot->timestamps_end->toDateTimeString(),
                ],
                $seller->uuid
            );

            // Сохраняем цену во временных метаданных (можно расширить TemporarySlot)
            // В данном случае цена запоминается в сессии/кэше или передаётся дальше

            return $temporarySlot;
        });
    }

    /**
     * Шаг 2: Подтвердить выставление (предмет перемещается в аукцион)
     */
    public function confirmLot(
        Character $seller,
        string $itemUuid,
        int $price
    ): AuctionLot {
        return DB::transaction(function () use ($seller, $itemUuid, $price) {
            $item = Item::where('uuid', $itemUuid)
                ->whereNotNull('temporary_slot_uuid')
                ->firstOrFail();

            $temporarySlot = TemporarySlot::where('uuid', $item->temporary_slot_uuid)->firstOrFail();
            if ($temporarySlot->character_uuid !== $seller->uuid) {
                throw new \RuntimeException('Временный слот не принадлежит продавцу');
            }

            if (!$temporarySlot->active || ($temporarySlot->timestamps_end && $temporarySlot->timestamps_end->isPast())) {
                throw new \RuntimeException('Временный слот истёк или неактивен');
            }

            // Находим или создаём аукционный слот для предмета
            $auctionCharacter = Character::where('character_type', 'auction')->firstOrFail();
            $auctionStorage = Storage::firstOrCreate(
                [
                    'characters_uuid' => $auctionCharacter->uuid,
                    'storage_type' => 'auction',
                ],
                ['name' => 'Аукцион', 'active' => true]
            );

            // Создаём постоянный слот на аукционе
            $auctionSlot = Slot::create([
                'uuid' => Str::uuid()->toString(),
                'storage_uuid' => $auctionStorage->uuid,
                'slot_type' => null,
            ]);

            // Перемещаем предмет в аукционный слот
            $item->update([
                'slot_uuid' => $auctionSlot->uuid,
                'temporary_slot_uuid' => null,
            ]);

            // Создаём лот
            $lot = AuctionLot::create([
                'uuid' => Str::uuid()->toString(),
                'storage_uuid' => $auctionStorage->uuid,
                'seller_uuid' => $seller->uuid,
                'template_slug' => $item->template_slug,
                'quantity' => 1,
                'price' => $price,
                'commission_percent' => 5,
                'status' => 'active',
                'is_infinite' => false,
            ]);

            // Деактивируем временный слот
            $temporarySlot->update(['active' => false]);

            $correlationUuid = Str::uuid()->toString();

            $this->eventStore->record(
                'auction.listed',
                'auction_lot',
                $lot->uuid,
                [
                    'item_uuid' => $item->uuid,
                    'seller_uuid' => $seller->uuid,
                    'template_slug' => $item->template_slug,
                    'price' => $price,
                    'from_slot_uuid' => $slot->uuid ?? null,
                    'to_slot_uuid' => $auctionSlot->uuid,
                ],
                $seller->uuid,
                $correlationUuid
            );

            return $lot;
        });
    }

    /**
     * Шаг 3: Купить предмет с аукциона
     */
    public function buyLot(
        Character $buyer,
        string $lotUuid
    ): array {
        return DB::transaction(function () use ($buyer, $lotUuid) {
            $lot = AuctionLot::where('uuid', $lotUuid)
                ->where('status', 'active')
                ->firstOrFail();

            if ($lot->seller_uuid === $buyer->uuid) {
                throw new \RuntimeException('Нельзя купить свой собственный лот');
            }

            // Проверяем золото покупателя
            $buyerGold = $this->inventoryService->getResourceQuantity($buyer, 'gold');
            if ($buyerGold < $lot->price) {
                throw new \RuntimeException("Недостаточно золота: есть {$buyerGold}, нужно {$lot->price}");
            }

            if ($lot->is_infinite) {
                return $this->buyInfiniteLot($buyer, $lot);
            } else {
                return $this->buyFiniteLot($buyer, $lot);
            }
        });
    }

    /**
     * Покупка бесконечного лота (NPC-торговец)
     */
    private function buyInfiniteLot(Character $buyer, AuctionLot $lot): array
    {
        // Снимаем золото с покупателя
        $this->inventoryService->removeResource($buyer, 'gold', $lot->price);

        // Создаём новый предмет/ресурс для покупателя (копию)
        $template = $lot->template;
        $correlationUuid = Str::uuid()->toString();

        if ($template->type === 'material') {
            $result = $this->inventoryService->addResource(
                $buyer,
                $lot->template_slug,
                $lot->quantity
            );
        } elseif ($template->type === 'blueprint') {
            // Для blueprint-шаблонов используем recipe_slug из template
            $result = $this->inventoryService->addItem(
                $buyer,
                $lot->template_slug,
                'blueprint',
                null,
                $template->recipe_slug,
                null,
                null
            );
        } else {
            $result = $this->inventoryService->addItem(
                $buyer,
                $lot->template_slug,
                'item',
                null,
                null,
                null,
                $template->base_stats
            );
        }

        // Логируем в аукционную историю
        \App\Models\AuctionHistory::create([
            'lot_uuid' => $lot->uuid,
            'seller_uuid' => $lot->seller_uuid,
            'buyer_uuid' => $buyer->uuid,
            'template_slug' => $lot->template_slug,
            'quantity' => $lot->quantity,
            'price' => $lot->price,
            'commission' => 0,
            'seller_received' => 0, // NPC не получает золото
            'action' => 'sold',
            'occurred_at' => now(),
        ]);

        $this->eventStore->record(
            'auction.purchased',
            'auction_lot',
            $lot->uuid,
            [
                'buyer_uuid' => $buyer->uuid,
                'seller_uuid' => $lot->seller_uuid,
                'template_slug' => $lot->template_slug,
                'quantity' => $lot->quantity,
                'price' => $lot->price,
                'is_infinite' => true,
            ],
            $buyer->uuid,
            $correlationUuid
        );

        return [
            'lot' => $lot,
            'result' => $result,
            'is_infinite' => true,
        ];
    }

    /**
     * Покупка обычного лота (от игрока)
     */
    private function buyFiniteLot(Character $buyer, AuctionLot $lot): array
    {
        $item = Item::whereIn('slot_uuid', function ($q) use ($lot) {
            $q->select('uuid')->from('slots')->where('storage_uuid', $lot->storage_uuid);
        })->first();

        if (!$item) {
            throw new \RuntimeException('Предмет лота не найден');
        }

        // Снимаем золото с покупателя
        $this->inventoryService->removeResource($buyer, 'gold', $lot->price);

        // Находим инвентарь покупателя
        $buyerInventory = Storage::where('characters_uuid', $buyer->uuid)
            ->where('storage_type', 'inventory')
            ->firstOrFail();

        // Ищем свободный слот в инвентаре покупателя
        $occupiedSlotUuids = Item::pluck('slot_uuid')->merge(Resource::pluck('slot_uuid'));
        $freeSlot = $buyerInventory->slots()
            ->whereNotIn('uuid', $occupiedSlotUuids)
            ->first();

        if (!$freeSlot) {
            // Возвращаем золото обратно
            $this->inventoryService->addResource($buyer, 'gold', $lot->price);
            throw new \RuntimeException('У покупателя нет свободных слотов в инвентаре');
        }

        // Перемещаем предмет покупателю
        $oldSlotUuid = $item->slot_uuid;
        $item->update(['slot_uuid' => $freeSlot->uuid]);

        // Вычисляем комиссию
        $commission = (int) round($lot->price * $lot->commission_percent / 100);
        $sellerReceived = $lot->price - $commission;

        // Начисляем золото продавцу (если есть продавец)
        if ($lot->seller) {
            $this->inventoryService->addResource($lot->seller, 'gold', $sellerReceived);
        }

        // Обновляем лот
        $lot->update([
            'status' => 'sold',
            'buyer_uuid' => $buyer->uuid,
            'sold_at' => now(),
        ]);

        // Удаляем аукционный слот (он больше не нужен)
        Slot::where('uuid', $oldSlotUuid)->delete();

        $correlationUuid = Str::uuid()->toString();

        // Логируем в аукционную историю
        \App\Models\AuctionHistory::create([
            'lot_uuid' => $lot->uuid,
            'seller_uuid' => $lot->seller_uuid,
            'buyer_uuid' => $buyer->uuid,
            'template_slug' => $lot->template_slug,
            'quantity' => 1,
            'price' => $lot->price,
            'commission' => $commission,
            'seller_received' => $sellerReceived,
            'action' => 'sold',
            'occurred_at' => now(),
        ]);

        $this->eventStore->record(
            'auction.purchased',
            'auction_lot',
            $lot->uuid,
            [
                'buyer_uuid' => $buyer->uuid,
                'seller_uuid' => $lot->seller_uuid,
                'item_uuid' => $item->uuid,
                'template_slug' => $lot->template_slug,
                'price' => $lot->price,
                'commission' => $commission,
                'seller_received' => $sellerReceived,
                'from_slot_uuid' => $oldSlotUuid,
                'to_slot_uuid' => $freeSlot->uuid,
            ],
            $buyer->uuid,
            $correlationUuid
        );

        return [
            'lot' => $lot->fresh(),
            'item' => $item->fresh(),
            'seller_received' => $sellerReceived,
            'commission' => $commission,
            'is_infinite' => false,
        ];
    }

    /**
     * Отмена лота
     */
    public function cancelLot(
        Character $seller,
        string $lotUuid
    ): AuctionLot {
        return DB::transaction(function () use ($seller, $lotUuid) {
            $lot = AuctionLot::where('uuid', $lotUuid)
                ->where('seller_uuid', $seller->uuid)
                ->where('status', 'active')
                ->firstOrFail();

            if ($lot->is_infinite) {
                throw new \RuntimeException('Нельзя отменить бесконечный лот');
            }

            $item = Item::whereIn('slot_uuid', function ($q) use ($lot) {
                $q->select('uuid')->from('slots')->where('storage_uuid', $lot->storage_uuid);
            })->first();

            if (!$item) {
                throw new \RuntimeException('Предмет лота не найден');
            }

            // Находим инвентарь продавца
            $sellerInventory = Storage::where('characters_uuid', $seller->uuid)
                ->where('storage_type', 'inventory')
                ->firstOrFail();

            // Ищем свободный слот в инвентаре продавца
            $occupiedSlotUuids = Item::pluck('slot_uuid')->merge(Resource::pluck('slot_uuid'));
            $freeSlot = $sellerInventory->slots()
                ->whereNotIn('uuid', $occupiedSlotUuids)
                ->first();

            if (!$freeSlot) {
                throw new \RuntimeException('У продавца нет свободных слотов для возврата предмета');
            }

            $oldSlotUuid = $item->slot_uuid;
            $item->update(['slot_uuid' => $freeSlot->uuid]);

            $lot->update(['status' => 'cancelled']);

            Slot::where('uuid', $oldSlotUuid)->delete();

            $correlationUuid = Str::uuid()->toString();

            \App\Models\AuctionHistory::create([
                'lot_uuid' => $lot->uuid,
                'seller_uuid' => $seller->uuid,
                'buyer_uuid' => null,
                'template_slug' => $lot->template_slug,
                'quantity' => 1,
                'price' => $lot->price,
                'commission' => 0,
                'seller_received' => 0,
                'action' => 'cancelled',
                'occurred_at' => now(),
            ]);

            $this->eventStore->record(
                'auction.cancelled',
                'auction_lot',
                $lot->uuid,
                [
                    'seller_uuid' => $seller->uuid,
                    'item_uuid' => $item->uuid,
                    'template_slug' => $lot->template_slug,
                    'from_slot_uuid' => $oldSlotUuid,
                    'to_slot_uuid' => $freeSlot->uuid,
                ],
                $seller->uuid,
                $correlationUuid
            );

            return $lot->fresh();
        });
    }
}
