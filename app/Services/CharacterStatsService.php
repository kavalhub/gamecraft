<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Character;
use App\Models\CharacterStat;

class CharacterStatsService
{
  /** @var int[] Пороги опыта для повышения уровня: ур.2 — 10, ур.3 — 50, ур.4 — 150 … */
    private const XP_LEVEL_THRESHOLDS = [0, 10, 50, 150, 450, 1350, 4050, 12150, 36450];

    public function __construct(
        private SpecialSlotService $specialSlotService,
    ) {}

    /**
     * @return array<string, int|float>
     */
    public function ensureFor(Character $character): array
    {
        $stats = CharacterStat::firstOrCreate(
            ['character_uuid' => $character->uuid],
            [
                'level' => 1,
                'base_damage' => random_int(3, 5),
                'strength' => 10,
                'agility' => 10,
                'intellect' => 10,
                'stamina' => 10,
                'spirit' => 10,
            ]
        );

        return $this->buildProfile($character, $stats);
    }

    public function levelFromExperience(int $experience): int
    {
        $level = 1;
        foreach (array_slice(self::XP_LEVEL_THRESHOLDS, 1) as $threshold) {
            if ($experience >= $threshold) {
                $level++;
            } else {
                break;
            }
        }

        return max(1, $level);
    }

    /**
     * @return array{current: int, level: int, level_min: int, level_max: ?int}
     */
    public function experienceProgress(int $experience): array
    {
        $level = $this->levelFromExperience($experience);
        $levelMin = self::XP_LEVEL_THRESHOLDS[$level - 1] ?? 0;
        $levelMax = self::XP_LEVEL_THRESHOLDS[$level] ?? null;

        return [
            'current' => $experience,
            'level' => $level,
            'level_min' => $levelMin,
            'level_max' => $levelMax,
        ];
    }

    public function syncLevelFromExperience(Character $character): void
    {
        $xp = $this->specialSlotService->getExperienceQuantity($character);
        $level = $this->levelFromExperience($xp);

        CharacterStat::where('character_uuid', $character->uuid)->update(['level' => $level]);
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

        $experience = $this->specialSlotService->getExperienceQuantity($character);
        $level = $this->levelFromExperience($experience);

        if ($base->level !== $level) {
            $base->update(['level' => $level]);
        }

        $equipmentBonus = $this->sumEquipmentBonuses($character);
        $baseHealth = 50;
        $totalStrength = $base->strength + ($equipmentBonus['strength'] ?? 0);
        $equipmentDamage = (int) ($equipmentBonus['damage'] ?? 0);

        return [
            'level' => $level,
            'experience' => $experience,
            'experience_progress' => $this->experienceProgress($experience),
            'base' => [
                'strength' => $base->strength,
                'agility' => $base->agility,
                'intellect' => $base->intellect,
                'stamina' => $base->stamina,
                'spirit' => $base->spirit,
                'health' => $baseHealth,
                'base_damage' => (int) $base->base_damage,
            ],
            'equipment_bonus' => $equipmentBonus,
            'total' => [
                'strength' => $totalStrength,
                'agility' => $base->agility + ($equipmentBonus['agility'] ?? 0),
                'intellect' => $base->intellect + ($equipmentBonus['intellect'] ?? 0),
                'stamina' => $base->stamina + ($equipmentBonus['stamina'] ?? 0),
                'spirit' => $base->spirit + ($equipmentBonus['spirit'] ?? 0),
                'damage' => $this->computeMeleeDamage($equipmentDamage, (int) $base->base_damage, $level),
                'defense' => $equipmentBonus['defense'] ?? 0,
                'health' => $baseHealth + ($equipmentBonus['health'] ?? 0),
            ],
        ];
    }

    /**
     * Без оружия: base_damage (3–5) × уровень. С оружием — урон экипировки.
     */
    private function computeMeleeDamage(int $equipmentDamage, int $baseDamage, int $level): int
    {
        if ($equipmentDamage >= 1) {
            return $equipmentDamage;
        }

        $baseDamage = max(3, min(5, $baseDamage));

        return max(1, $baseDamage * max(1, $level));
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
        $items = \App\Models\Item::whereIn('slot_uuid', $slotUuids)
            ->whereNull('buffer_slot_uuid')
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
