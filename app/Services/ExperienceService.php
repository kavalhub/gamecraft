<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Character;
use Illuminate\Support\Facades\DB;

class ExperienceService
{
    private static bool $mutationAllowed = false;

    public function __construct(
        private InventoryService $inventoryService,
        private EventStore $eventStore,
    ) {}

    public static function isMutationAllowed(): bool
    {
        return self::$mutationAllowed;
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
                $this->inventoryService->addResource($character, 'experience', $amount);
            } finally {
                self::$mutationAllowed = false;
            }

            $this->eventStore->record(
                'experience.credited',
                'character',
                $character->uuid,
                [
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
                $this->inventoryService->removeResource($character, 'experience', $amount);
            } finally {
                self::$mutationAllowed = false;
            }

            $this->eventStore->record(
                'experience.debited',
                'character',
                $character->uuid,
                [
                    'amount' => $amount,
                    'source' => $source,
                    'metadata' => $metadata,
                ],
                $character->uuid
            );
        });
    }
}
