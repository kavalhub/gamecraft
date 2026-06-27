<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class NpcSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'wulfric@game.npc'],
            [
                'name' => 'Wulfric Goldsmyth',
                'password' => bcrypt('npc_password'),
                'gold' => 999999,
            ]
        );

        $this->command->info('NPC Wulfric Goldsmyth created');
    }
}
