<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\CombatSimulator;
use Tests\TestCase;

class CombatSimulatorTest extends TestCase
{
    private CombatSimulator $simulator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->simulator = app(CombatSimulator::class);
    }

    public function test_personalize_for_right_inverts_outcome(): void
    {
        $simulation = [
            'winner' => 'left',
            'left_hp_remaining' => 30,
            'right_hp_remaining' => 0,
            'combat_log' => [
                [
                    'actor' => 'left',
                    'message' => 'А наносит 5 урона. У Б осталось 0 HP.',
                    'left_hp' => 30,
                    'right_hp' => 0,
                ],
            ],
            'combat_ui' => [
                'left' => ['name' => 'А', 'hp_max' => 50],
                'right' => ['name' => 'Б', 'hp_max' => 20],
            ],
        ];

        $rightView = $this->simulator->personalizeForRight('А', 'Б', $simulation);

        $this->assertSame('lost', $rightView['outcome']);
        $this->assertSame('Б', $rightView['combat_ui']['player']['name']);
        $this->assertSame('А', $rightView['combat_ui']['enemy']['name']);
    }

    public function test_resolve_vs_npc_returns_player_perspective(): void
    {
        $result = $this->simulator->resolveVsNpc('Герой', [
            'total' => [
                'health' => 100,
                'damage' => 20,
                'defense' => 2,
                'strength' => 10,
            ],
        ], [
            'name' => 'Крыса',
            'stats' => ['health' => 5, 'damage' => 1, 'defense' => 0],
        ]);

        $this->assertContains($result['outcome'], ['won', 'lost']);
        $this->assertNotEmpty($result['combat_log']);
        $this->assertSame('Герой', $result['combat_ui']['player']['name']);
        $this->assertSame('Крыса', $result['combat_ui']['enemy']['name']);
    }
}
