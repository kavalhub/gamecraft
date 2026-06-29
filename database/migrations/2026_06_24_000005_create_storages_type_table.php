<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('storages_type', function (Blueprint $table) {
            $table->id();
            $table->string('type')->unique();
            $table->string('name');
            $table->json('allowed_types')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        $types = [
            ['type' => 'inventory', 'name' => 'Инвентарь', 'allowed_types' => json_encode(['slots' => [['slot_type' => null, 'count' => 50]]])],
            ['type' => 'equipment', 'name' => 'Экипировка', 'allowed_types' => json_encode(['slots' => [
                ['slot_type' => 'equipment_head', 'count' => 1],
                ['slot_type' => 'equipment_chest', 'count' => 1],
                ['slot_type' => 'equipment_legs', 'count' => 1],
                ['slot_type' => 'equipment_weapon', 'count' => 1],
                ['slot_type' => 'equipment_offhand', 'count' => 1],
                ['slot_type' => 'equipment_ring', 'count' => 2],
                ['slot_type' => 'equipment_amulet', 'count' => 1],
            ]])],
            ['type' => 'bank', 'name' => 'Банк', 'allowed_types' => json_encode(['slots' => [['slot_type' => null, 'count' => 100]]])],
            ['type' => 'auction', 'name' => 'Аукцион', 'allowed_types' => null],
            ['type' => 'trade', 'name' => 'Обмен', 'allowed_types' => null],
            ['type' => 'special', 'name' => 'Специальное', 'allowed_types' => null],
            ['type' => 'world', 'name' => 'Мир', 'allowed_types' => null],
            ['type' => 'mail', 'name' => 'Почта', 'allowed_types' => json_encode(['slots' => [['slot_type' => null, 'count' => 100]]])],
        ];

        foreach ($types as $type) {
            DB::table('storages_type')->insert(array_merge($type, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('storages_type');
    }
};
