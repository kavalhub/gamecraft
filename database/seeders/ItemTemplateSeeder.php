<?php
declare(strict_types=1);

namespace Database\Seeders;

use App\Models\ItemTemplate;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ItemTemplateSeeder extends Seeder
{
    public function run(): void
    {
        // Дерево
        ItemTemplate::updateOrCreate(
            ['id' => 1],
            [
                'name' => 'Дерево',
                'type' => 'material',
                'icon' => '🪵',
                'is_stackable' => true,
                'max_stack' => 200,
            ]
        );

        // Железная руда
        ItemTemplate::updateOrCreate(
            ['id' => 2],
            [
                'name' => 'Железная руда',
                'type' => 'material',
                'icon' => '⛏️',
                'is_stackable' => true,
                'max_stack' => 100,
            ]
        );

        ItemTemplate::updateOrCreate(
            ['id' => 3],
            [
                'name' => 'Деревянный меч',
                'type' => 'equipment',
                'is_stackable' => false,
                'icon' => 'wood_sword.png',
                'disassemble_data' => json_encode(['1' => 2]),
                'max_stack' => 1,
            ]
        );
    }
}
