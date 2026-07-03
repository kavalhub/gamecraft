<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Character;
use App\Models\CharacterSetting;

class CharacterSettingsDefaultsService
{
    public function applyForCharacter(Character $character): void
    {
        $uuid = $character->uuid;

        if (CharacterSetting::get($uuid, 'window_positions') === null) {
            CharacterSetting::set($uuid, 'window_positions', config('game.default_window_positions', []));
        }
    }

    /**
     * @param array<string, mixed> $saved
     * @return array<string, mixed>
     */
    public function mergeSettings(array $saved): array
    {
        $defaults = config('game.default_window_positions', []);
        $savedPositions = $saved['window_positions'] ?? [];

        if (is_array($savedPositions) && $savedPositions !== []) {
            $saved['window_positions'] = array_merge($defaults, $savedPositions);
        } elseif ($defaults !== []) {
            $saved['window_positions'] = $defaults;
        }

        return $saved;
    }
}
