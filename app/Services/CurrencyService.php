<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Character;
use Illuminate\Support\Facades\DB;

class CurrencyService
{
    private static bool $mutationAllowed = false;

    public function __construct(
        private InventoryService $inventoryService,
        private EventStore $eventStore,
        private StorageProvisioningService $provisioningService,
    ) {}

    public static function isMutationAllowed(): bool
    {
        return self::$mutationAllowed;
    }

    public function grantStartingGold(Character $character, int $amount = 1000): void
    {
        if ($amount < 1) {
            return;
        }

        if ($this->provisioningService->getInventoryGoldQuantity($character) > 0) {
            return;
        }

        $this->credit($character, $amount, 'registration', [
            'reason' => 'starting_gold',
        ]);
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function credit(Character $character, int $amount, string $source, array $metadata = []): void
    {
        if ($amount < 1) {
            return;
        }

        DB::transaction(function () use ($character, $amount, $source, $metadata) {
            self::$mutationAllowed = true;
            try {
                $this->inventoryService->addResource($character, 'gold', $amount);
            } finally {
                self::$mutationAllowed = false;
            }

            $this->eventStore->record(
                'currency.credited',
                'character',
                $character->uuid,
                [
                    'currency' => 'gold',
                    'amount' => $amount,
                    'source' => $source,
                    'metadata' => $metadata,
                ],
                $character->uuid
            );
        });
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function debit(Character $character, int $amount, string $source, array $metadata = []): void
    {
        if ($amount < 1) {
            return;
        }

        DB::transaction(function () use ($character, $amount, $source, $metadata) {
            self::$mutationAllowed = true;
            try {
                $this->inventoryService->removeResource($character, 'gold', $amount);
            } finally {
                self::$mutationAllowed = false;
            }

            $this->eventStore->record(
                'currency.debited',
                'character',
                $character->uuid,
                [
                    'currency' => 'gold',
                    'amount' => $amount,
                    'source' => $source,
                    'metadata' => $metadata,
                ],
                $character->uuid
            );
        });
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function transfer(
        Character $from,
        Character $to,
        int $amount,
        string $source,
        array $metadata = []
    ): void {
        if ($amount < 1) {
            return;
        }

        DB::transaction(function () use ($from, $to, $amount, $source, $metadata) {
            $this->debit($from, $amount, $source, array_merge($metadata, [
                'counterparty_uuid' => $to->uuid,
                'direction' => 'out',
            ]));
            $this->credit($to, $amount, $source, array_merge($metadata, [
                'counterparty_uuid' => $from->uuid,
                'direction' => 'in',
            ]));
        });
    }
}
