<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Character;
use App\Models\CharacterSetting;
use App\Models\Slot;

class PlayPanelService
{
    public const SLOT_COUNT = 12;

    /** @var string[] */
    public const VALID_ACTIONS = [
        'journal',
        'inventory',
        'character',
        'auction',
        'players',
        'settings',
    ];

    /** @var string[] */
    public const DEFAULT_ACTION_ORDER = [
        'journal',
        'inventory',
        'character',
        'auction',
        'players',
        'settings',
    ];

    public function __construct(
        private StorageProvisioningService $provisioningService
    ) {}

    public function ensurePlayPanel(Character $character): void
    {
        $this->provisioningService->grantStorage($character, 'play_panel');
    }

    /**
     * @return array{storage_uuid: string, cols: int, slots: array<int, array{uuid: string, action: string|null}>, layout: array<string, string>}
     */
    public function getPanelData(Character $character): array
    {
        $this->ensurePlayPanel($character);

        $storage = $character->storages()->where('storage_type', 'play_panel')->firstOrFail();
        $slots = $storage->slots()->orderBy('id')->get();

        $savedLayout = CharacterSetting::get($character->uuid, 'play_panel_layout', []);
        if (!is_array($savedLayout)) {
            $savedLayout = [];
        }

        $layout = $this->normalizeLayout($slots, $savedLayout);

        $slotPayload = [];
        foreach ($slots as $slot) {
            $slotPayload[] = [
                'uuid' => $slot->uuid,
                'action' => $layout[$slot->uuid] ?? null,
            ];
        }

        return [
            'storage_uuid' => $storage->uuid,
            'cols' => $this->provisioningService->getGridCols('play_panel'),
            'slots' => $slotPayload,
            'layout' => $layout,
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Slot>  $slots
     * @param  array<string, string>  $savedLayout
     * @return array<string, string>
     */
    private function normalizeLayout($slots, array $savedLayout): array
    {
        $validActions = array_flip(self::VALID_ACTIONS);
        $layout = [];

        foreach ($savedLayout as $slotUuid => $action) {
            if (!is_string($slotUuid) || !is_string($action)) {
                continue;
            }
            // migrate legacy action ids
            if ($action === 'trade') {
                $action = 'players';
            }
            if ($action === 'workbench' || !isset($validActions[$action])) {
                continue;
            }
            $layout[$slotUuid] = $action;
        }

        if ($layout !== []) {
            return $this->ensureRequiredActions($slots, $this->dedupeLayout($slots, $layout));
        }

        return $this->buildDefaultLayout($slots);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Slot>  $slots
     * @param  array<string, string>  $layout
     * @return array<string, string>
     */
    private function ensureRequiredActions($slots, array $layout): array
    {
        foreach (['character', 'players'] as $action) {
            if (in_array($action, $layout, true)) {
                continue;
            }
            $emptySlot = $slots->first(fn (Slot $slot) => !isset($layout[$slot->uuid]));
            if ($emptySlot) {
                $layout[$emptySlot->uuid] = $action;
            }
        }

        return $layout;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Slot>  $slots
     * @return array<string, string>
     */
    private function buildDefaultLayout($slots): array
    {
        $layout = [];
        $slotList = $slots->values();

        foreach (self::DEFAULT_ACTION_ORDER as $index => $action) {
            $slot = $slotList->get($index);
            if ($slot) {
                $layout[$slot->uuid] = $action;
            }
        }

        return $layout;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Slot>  $slots
     * @param  array<string, string>  $layout
     * @return array<string, string>
     */
    private function dedupeLayout($slots, array $layout): array
    {
        $slotUuids = $slots->pluck('uuid')->flip();
        $usedActions = [];
        $result = [];

        foreach ($layout as $slotUuid => $action) {
            if (!$slotUuids->has($slotUuid) || isset($usedActions[$action])) {
                continue;
            }
            $result[$slotUuid] = $action;
            $usedActions[$action] = true;
        }

        return $result;
    }
}
