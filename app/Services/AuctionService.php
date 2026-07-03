<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuctionLot;
use App\Models\Character;
use App\Models\Item;
use App\Models\Resources;
use App\Models\Slot;
use App\Models\Storage;
use App\Models\TemporarySlot;
use App\Services\StorageProvisioningService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AuctionService
{
    public function __construct(
        private InventoryService $inventoryService,
        private EventStore $eventStore,
        private WorldStorageService $worldStorageService,
        private MailService $mailService,
        private CurrencyService $currencyService,
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

    public function isLotVisibleToBuyer(Character $buyer, AuctionLot $lot): bool
    {
        if ($lot->template->type !== 'quest_item') {
            return true;
        }

        return !$this->buyerOwnsQuestItem($buyer, $lot->template_slug);
    }

    public function buyerOwnsQuestItem(Character $buyer, string $templateSlug): bool
    {
        return $this->inventoryService->countItemsInStorage($buyer, $templateSlug, 'quest_item') > 0;
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
                ->whereNull('buffer_slot_uuid')
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
            $item->update(['buffer_slot_uuid' => $temporarySlot->uuid]);

            $this->eventStore->record(
                'auction.prepared',
                'item',
                $item->uuid,
                [
                    'price' => $price,
                    'buffer_slot_uuid' => $temporarySlot->uuid,
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
     * Выставить лот одним действием (prepare + confirm)
     */
    public function listLot(
        Character $seller,
        string $itemUuid,
        int $price
    ): AuctionLot {
        return DB::transaction(function () use ($seller, $itemUuid, $price) {
            $this->prepareLot($seller, $itemUuid, $price);

            return $this->confirmLot($seller, $itemUuid, $price);
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
                ->whereNotNull('buffer_slot_uuid')
                ->firstOrFail();

            $sourceSlotUuid = $item->slot_uuid;

            $temporarySlot = TemporarySlot::where('uuid', $item->buffer_slot_uuid)->firstOrFail();
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
                'buffer_slot_uuid' => null,
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
                    'from_slot_uuid' => $sourceSlotUuid,
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
        string $lotUuid,
        int $quantity = 1
    ): array {
        return DB::transaction(function () use ($buyer, $lotUuid, $quantity) {
            $provisioning = app(StorageProvisioningService::class);
            $provisioning->consolidateInventoryResources($buyer);

            $lot = AuctionLot::with('template')
                ->where('uuid', $lotUuid)
                ->where('status', 'active')
                ->firstOrFail();

            if ($lot->seller_uuid === $buyer->uuid) {
                throw new \RuntimeException('Нельзя купить свой собственный лот');
            }

            if ($quantity < 1) {
                throw new \RuntimeException('Количество должно быть больше 0');
            }

            if ($lot->is_infinite) {
                $maxPurchasable = $this->getMaxPurchasableQuantity($buyer, $lot);
                if ($quantity > $maxPurchasable) {
                    throw new \RuntimeException("Можно купить не более {$maxPurchasable} шт.");
                }

                $totalPrice = $lot->price * $quantity;
                $buyerGold = $provisioning->getInventoryGoldQuantity($buyer);
                if ($buyerGold < $totalPrice) {
                    throw new \RuntimeException("Недостаточно золота: есть {$buyerGold}, нужно {$totalPrice}");
                }

                return $this->buyInfiniteLot($buyer, $lot, $quantity);
            }

            if ($quantity !== $lot->quantity) {
                throw new \RuntimeException('Можно купить только весь лот целиком');
            }

            $buyerGold = $provisioning->getInventoryGoldQuantity($buyer);
            if ($buyerGold < $lot->price) {
                throw new \RuntimeException("Недостаточно золота: есть {$buyerGold}, нужно {$lot->price}");
            }

            return $this->buyFiniteLot($buyer, $lot);
        });
    }

    /**
     * Максимальное количество для покупки бесконечного лота
     */
    public function getMaxPurchasableQuantity(Character $buyer, AuctionLot $lot): int
    {
        return $this->getBuyLimits($buyer, $lot)['max_purchasable'];
    }

    /**
     * @return array{max_by_gold: int, max_by_inventory: int, max_purchasable: int, gold_available: int}
     */
    public function getBuyLimits(Character $buyer, AuctionLot $lot): array
    {
        $provisioning = app(StorageProvisioningService::class);
        $provisioning->consolidateInventoryResources($buyer);
        $gold = $provisioning->getInventoryGoldQuantity($buyer);

        if (!$lot->is_infinite) {
            $canBuy = $gold >= $lot->price && $lot->quantity > 0;

            return [
                'max_by_gold' => $canBuy ? $lot->quantity : 0,
                'max_by_inventory' => $lot->quantity,
                'max_purchasable' => $canBuy ? $lot->quantity : 0,
                'gold_available' => $gold,
            ];
        }

        if ($lot->template->type === 'quest_item' && $this->buyerOwnsQuestItem($buyer, $lot->template_slug)) {
            return [
                'max_by_gold' => 0,
                'max_by_inventory' => 0,
                'max_purchasable' => 0,
                'gold_available' => $gold,
            ];
        }

        $maxByGold = $lot->price > 0 ? intdiv($gold, $lot->price) : 0;
        $maxByInventory = $lot->template->type === 'material'
            ? $this->inventoryService->getMaxAddableQuantity($buyer, $lot->template_slug)
            : ($this->inventoryService->getMaxAddableQuantity($buyer, $lot->template_slug) > 0 ? 1 : 0);
        $maxPurchasable = $lot->template->type === 'material'
            ? min($maxByGold, $this->capUiLimit($maxByInventory))
            : min($maxByGold, $maxByInventory > 0 ? 1 : 0, 1);

        return [
            'max_by_gold' => $maxByGold,
            'max_by_inventory' => $this->capUiLimit($maxByInventory),
            'max_purchasable' => $maxPurchasable,
            'gold_available' => $gold,
        ];
    }

    private function capUiLimit(int $value): int
    {
        $cap = 99999;

        return $value > $cap ? $cap : $value;
    }

    /**
     * Покупка бесконечного лота (NPC-торговец)
     */
    private function buyInfiniteLot(Character $buyer, AuctionLot $lot, int $quantity): array
    {
        if ($lot->price < 1) {
            throw new \RuntimeException('Цена лота должна быть не менее 1 золота');
        }

        $template = $lot->template;

        if ($template->type === 'quest_item' && $this->buyerOwnsQuestItem($buyer, $lot->template_slug)) {
            throw new \RuntimeException('У вас уже есть этот квестовый предмет');
        }

        if ($template->type !== 'material' && $quantity !== 1) {
            throw new \RuntimeException('Этот предмет можно купить только по одному');
        }

        $totalPrice = $lot->price * $quantity;

        $this->currencyService->debit($buyer, $totalPrice, 'auction.buy', [
            'lot_uuid' => $lot->uuid,
            'template_slug' => $lot->template_slug,
            'quantity' => $quantity,
        ]);

        $correlationUuid = Str::uuid()->toString();

        if ($template->type === 'material') {
            $result = $this->inventoryService->addResource(
                $buyer,
                $lot->template_slug,
                $quantity
            );
        } elseif ($this->worldStorageService->isInstanceTemplate($template)) {
            $result = null;
            $stage = $this->worldStorageService->stageForTemplate($template);
            for ($i = 0; $i < $quantity; $i++) {
                $claimed = $this->worldStorageService->claimItem($buyer, $lot->template_slug, $stage);
                $result = $claimed ?? $this->inventoryService->addItem(
                    $buyer,
                    $lot->template_slug,
                    $stage,
                    null,
                    $template->recipe_slug ?? ($stage === 'quest_item' ? 'quest_item_stub' : null),
                    null,
                    $stage === 'item' ? $template->base_stats : null,
                );
            }
        } else {
            $result = null;
            for ($i = 0; $i < $quantity; $i++) {
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
        }

        \App\Models\AuctionHistory::create([
            'lot_uuid' => $lot->uuid,
            'seller_uuid' => $lot->seller_uuid,
            'buyer_uuid' => $buyer->uuid,
            'template_slug' => $lot->template_slug,
            'quantity' => $quantity,
            'price' => $totalPrice,
            'commission' => 0,
            'seller_received' => 0,
            'action' => 'sold',
            'occurred_at' => now(),
        ]);

        $purchasePayload = [
            'buyer_uuid' => $buyer->uuid,
            'seller_uuid' => $lot->seller_uuid,
            'template_slug' => $lot->template_slug,
            'quantity' => $quantity,
            'price' => $totalPrice,
            'unit_price' => $lot->price,
            'is_infinite' => true,
            'role' => 'buyer',
        ];

        $this->eventStore->record(
            'auction.purchased',
            'auction_lot',
            $lot->uuid,
            $purchasePayload,
            $buyer->uuid,
            $correlationUuid
        );

        $this->eventStore->record(
            'auction.sold',
            'auction_lot',
            $lot->uuid,
            array_merge($purchasePayload, ['role' => 'seller']),
            $lot->seller_uuid,
            $correlationUuid
        );

        return [
            'lot' => $lot,
            'result' => $result,
            'quantity' => $quantity,
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

        $this->currencyService->debit($buyer, $lot->price, 'auction.buy', [
            'lot_uuid' => $lot->uuid,
            'template_slug' => $lot->template_slug,
        ]);

        // Находим инвентарь покупателя
        $buyerInventory = Storage::where('characters_uuid', $buyer->uuid)
            ->where('storage_type', 'inventory')
            ->firstOrFail();

        // Ищем свободный слот в инвентаре покупателя
        $occupiedSlotUuids = Item::pluck('slot_uuid')->merge(Resources::pluck('slot_uuid'));
        $freeSlot = $buyerInventory->slots()
            ->whereNotIn('uuid', $occupiedSlotUuids)
            ->first();

        $oldSlotUuid = $item->slot_uuid;
        $mailed = false;

        if (!$freeSlot) {
            $this->mailService->sendSystemMail(
                $buyer,
                'Покупка с аукциона',
                'Инвентарь был полон. Предмет отправлен на почту.',
                [$item],
                $lot->seller?->name ?? 'Аукцион',
            );
            $mailed = true;
        } else {
            $item->update(['slot_uuid' => $freeSlot->uuid]);
        }

        // Вычисляем комиссию
        $commission = (int) round($lot->price * $lot->commission_percent / 100);
        $sellerReceived = $lot->price - $commission;

        if ($lot->seller) {
            $this->currencyService->credit($lot->seller, $sellerReceived, 'auction.sale', [
                'lot_uuid' => $lot->uuid,
                'buyer_uuid' => $buyer->uuid,
                'commission' => $commission,
            ]);
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

        $purchasePayload = [
            'buyer_uuid' => $buyer->uuid,
            'seller_uuid' => $lot->seller_uuid,
            'item_uuid' => $item->uuid,
            'template_slug' => $lot->template_slug,
            'price' => $lot->price,
            'commission' => $commission,
            'seller_received' => $sellerReceived,
            'from_slot_uuid' => $oldSlotUuid,
            'to_slot_uuid' => $mailed ? null : $freeSlot->uuid,
            'mailed' => $mailed,
            'quantity' => 1,
            'role' => 'buyer',
        ];

        $this->eventStore->record(
            'auction.purchased',
            'auction_lot',
            $lot->uuid,
            $purchasePayload,
            $buyer->uuid,
            $correlationUuid
        );

        $this->eventStore->record(
            'auction.sold',
            'auction_lot',
            $lot->uuid,
            array_merge($purchasePayload, ['role' => 'seller']),
            $lot->seller_uuid,
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
            $occupiedSlotUuids = Item::pluck('slot_uuid')->merge(Resources::pluck('slot_uuid'));
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
                    'to_slot_uuid' => $mailed ? null : $freeSlot->uuid,
            'mailed' => $mailed,
                ],
                $seller->uuid,
                $correlationUuid
            );

            return $lot->fresh();
        });
    }
}
