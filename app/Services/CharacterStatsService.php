<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Character;
use App\Models\CharacterStat;
use App\Models\Item;
use App\Models\Storage;

class CharacterStatsService
{
    /**
     * @return array<string, int|float>
     */
    public function ensureFor(Character $character): array
    {
        $stats = CharacterStat::firstOrCreate(
            ['character_uuid' => $character->uuid],
            [
                'level' => 1,
                'experience' => 0,
                'strength' => 10,
                'agility' => 10,
                'intellect' => 10,
                'stamina' => 10,
                'spirit' => 10,
            ]
        );

        return $this->buildProfile($character, $stats);
    }

    /**
     * @return array<string, mixed>
     */
    public function buildProfile(Character $character, ?CharacterStat $base = null): array
    {
        $base ??= CharacterStat::where('character_uuid', $character->uuid)->first();

        if (!$base) {
            return $this->ensureFor($character);
        }

        $equipmentBonus = $this->sumEquipmentBonuses($character);
        $baseHealth = 50;

        return [
            'level' => $base->level,
            'experience' => $base->experience,
            'base' => [
                'strength' => $base->strength,
                'agility' => $base->agility,
                'intellect' => $base->intellect,
                'stamina' => $base->stamina,
                'spirit' => $base->spirit,
                'health' => $baseHealth,
            ],
            'equipment_bonus' => $equipmentBonus,
            'total' => [
                'strength' => $base->strength + ($equipmentBonus['strength'] ?? 0),
                'agility' => $base->agility + ($equipmentBonus['agility'] ?? 0),
                'intellect' => $base->intellect + ($equipmentBonus['intellect'] ?? 0),
                'stamina' => $base->stamina + ($equipmentBonus['stamina'] ?? 0),
                'spirit' => $base->spirit + ($equipmentBonus['spirit'] ?? 0),
                'damage' => $equipmentBonus['damage'] ?? 0,
                'defense' => $equipmentBonus['defense'] ?? 0,
                'health' => $baseHealth + ($equipmentBonus['health'] ?? 0),
            ],
        ];
    }

    /**
     * @return array<string, int>
     */
    private function sumEquipmentBonuses(Character $character): array
    {
        $storage = $character->storages()->where('storage_type', 'equipment')->first();
        if (!$storage) {
            return [];
        }

        $slotUuids = $storage->slots()->pluck('uuid');
        $items = Item::whereIn('slot_uuid', $slotUuids)
            ->whereNull('temporary_slot_uuid')
            ->where('stage', 'item')
            ->get();

        $bonus = [];

        foreach ($items as $item) {
            if (!is_array($item->stats)) {
                continue;
            }
            foreach ($item->stats as $key => $value) {
                if (!is_numeric($value)) {
                    continue;
                }
                $bonus[$key] = ($bonus[$key] ?? 0) + (int) $value;
            }
        }

        return $bonus;
    }
}
