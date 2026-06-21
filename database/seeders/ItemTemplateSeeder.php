<?php
declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ItemTemplateSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('item_templates')->insert([
            [
                'name' => 'Дерево',
                'type' => 'material',
                'is_stackable' => true,
                'icon' => 'wood.png',
                'disassemble_data' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Железная руда',
                'type' => 'material',
                'is_stackable' => true,
                'icon' => 'iron.png',
                'disassemble_data' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Деревянный меч',
                'type' => 'equipment',
                'is_stackable' => false,
                'icon' => 'wood_sword.png',
                'disassemble_data' => json_encode(['1' => 2]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
