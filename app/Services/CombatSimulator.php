<?php

declare(strict_types=1);

namespace App\Services;

class CombatSimulator
{
    /**
     * @param  array<string, mixed>  $leftStats  profile total block
     * @param  array<string, mixed>  $rightStats
     * @return array{
     *   winner: string,
     *   left_hp_remaining: int,
     *   right_hp_remaining: int,
     *   combat_log: list<array<string, mixed>>,
     *   combat_ui: array{left: array{name: string, hp_max: int}, right: array{name: string, hp_max: int}}
     * }
     */
    public function resolve(
        string $leftName,
        array $leftStats,
        string $rightName,
        array $rightStats,
    ): array {
        $leftHp = (int) ($leftStats['health'] ?? 50);
        $rightHp = (int) ($rightStats['health'] ?? 10);
        $leftHpMax = $leftHp;
        $rightHpMax = $rightHp;
        $leftDefense = (int) ($leftStats['defense'] ?? 0);
        $rightDefense = (int) ($rightStats['defense'] ?? 0);

        $leftAttack = $this->attackPower($leftStats, $rightDefense);
        $rightAttack = max(1, $this->attackPower($rightStats, $leftDefense));

        $log = [];
        $round = 0;
        $maxRounds = 100;

        while ($leftHp > 0 && $rightHp > 0 && $round < $maxRounds) {
            $round++;
            $damage = $leftAttack;
            $rightHp = max(0, $rightHp - $damage);
            $log[] = [
                'actor' => 'left',
                'message' => "{$leftName} наносит {$damage} урона. У {$rightName} осталось {$rightHp} HP.",
                'left_hp' => $leftHp,
                'right_hp' => $rightHp,
            ];

            if ($rightHp <= 0) {
                $log[] = [
                    'actor' => 'system',
                    'message' => 'Победа!',
                    'left_hp' => $leftHp,
                    'right_hp' => 0,
                ];
                break;
            }

            $damage = $rightAttack;
            $leftHp = max(0, $leftHp - $damage);
            $log[] = [
                'actor' => 'right',
                'message' => "{$rightName} наносит {$damage} урона. У {$leftName} осталось {$leftHp} HP.",
                'left_hp' => $leftHp,
                'right_hp' => $rightHp,
            ];
        }

        if ($rightHp > 0 && $leftHp <= 0) {
            $log[] = [
                'actor' => 'system',
                'message' => 'Поражение.',
                'left_hp' => 0,
                'right_hp' => $rightHp,
            ];
        }

        $winner = $rightHp <= 0 ? 'left' : ($leftHp <= 0 ? 'right' : 'draw');

        return [
            'winner' => $winner,
            'left_hp_remaining' => max(0, $leftHp),
            'right_hp_remaining' => max(0, $rightHp),
            'combat_log' => $log,
            'combat_ui' => [
                'left' => ['name' => $leftName, 'hp_max' => $leftHpMax],
                'right' => ['name' => $rightName, 'hp_max' => $rightHpMax],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $simulation
     * @return array<string, mixed>
     */
    public function personalizeForLeft(string $leftName, string $rightName, array $simulation): array
    {
        return $this->personalize($leftName, $rightName, $simulation, true);
    }

    /**
     * @param  array<string, mixed>  $simulation
     * @return array<string, mixed>
     */
    public function personalizeForRight(string $leftName, string $rightName, array $simulation): array
    {
        return $this->personalize($leftName, $rightName, $simulation, false);
    }

    /**
     * @param  array<string, mixed>  $encounter
     * @param  array<string, mixed>  $playerStats
     * @return array{outcome: string, combat_log: list<array<string, mixed>>, player_hp_remaining: int, combat_ui: array<string, mixed>}
     */
    public function resolveVsNpc(string $playerName, array $playerStats, array $encounter): array
    {
        $enemyStats = $encounter['stats'] ?? [];
        $enemyName = (string) ($encounter['name'] ?? 'Противник');

        $npcStats = [
            'health' => (int) ($enemyStats['health'] ?? 10),
            'damage' => (int) ($enemyStats['damage'] ?? 1),
            'defense' => (int) ($enemyStats['defense'] ?? 0),
            'strength' => 0,
        ];

        $simulation = $this->resolve(
            $playerName,
            $playerStats['total'] ?? $playerStats,
            $enemyName,
            $npcStats,
        );

        $personalized = $this->personalizeForLeft($playerName, $enemyName, $simulation);

        return [
            'outcome' => $personalized['outcome'],
            'combat_log' => $personalized['combat_log'],
            'player_hp_remaining' => $personalized['player_hp_remaining'],
            'combat_ui' => $personalized['combat_ui'],
        ];
    }

    /**
     * @param  array<string, mixed>  $simulation
     * @return array<string, mixed>
     */
    private function personalize(string $leftName, string $rightName, array $simulation, bool $viewerIsLeft): array
    {
        $winner = (string) ($simulation['winner'] ?? 'draw');
        $outcome = 'lost';
        if ($winner === 'draw') {
            $outcome = 'lost';
        } elseif (($viewerIsLeft && $winner === 'left') || (!$viewerIsLeft && $winner === 'right')) {
            $outcome = 'won';
        }

        $ui = $simulation['combat_ui'];
        $combatUi = $viewerIsLeft
            ? [
                'player' => $ui['left'],
                'enemy' => $ui['right'],
            ]
            : [
                'player' => $ui['right'],
                'enemy' => $ui['left'],
            ];

        $combatLog = [];
        foreach ($simulation['combat_log'] as $line) {
            $actor = (string) ($line['actor'] ?? 'system');
            if ($viewerIsLeft) {
                $uiActor = match ($actor) {
                    'left' => 'player',
                    'right' => 'enemy',
                    default => 'system',
                };
                $playerHp = $line['left_hp'] ?? null;
                $enemyHp = $line['right_hp'] ?? null;
                $message = str_replace([$leftName, $rightName], ['Вы', $rightName], (string) ($line['message'] ?? ''));
            } else {
                $uiActor = match ($actor) {
                    'right' => 'player',
                    'left' => 'enemy',
                    default => 'system',
                };
                $playerHp = $line['right_hp'] ?? null;
                $enemyHp = $line['left_hp'] ?? null;
                $message = str_replace([$rightName, $leftName], ['Вы', $leftName], (string) ($line['message'] ?? ''));
            }

            $combatLog[] = [
                'actor' => $uiActor,
                'message' => $message,
                'player_hp' => $playerHp,
                'enemy_hp' => $enemyHp,
            ];
        }

        return [
            'outcome' => $outcome,
            'combat_log' => $combatLog,
            'player_hp_remaining' => $viewerIsLeft
                ? (int) ($simulation['left_hp_remaining'] ?? 0)
                : (int) ($simulation['right_hp_remaining'] ?? 0),
            'combat_ui' => $combatUi,
            'winner_side' => $winner,
        ];
    }

    /**
     * @param  array<string, mixed>  $stats
     */
    private function attackPower(array $stats, int $targetDefense): int
    {
        $rawDamage = (int) ($stats['damage'] ?? 0);
        if ($rawDamage < 1) {
            $baseDamage = max(3, min(5, (int) ($stats['base_damage'] ?? 4)));
            $level = max(1, (int) ($stats['level'] ?? 1));
            $rawDamage = max(1, $baseDamage * $level);
        }

        return max(1, $rawDamage - $targetDefense);
    }
}
