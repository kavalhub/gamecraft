<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Создаём таблицу БЕЗ FK
        Schema::create('slot_types', function (Blueprint $table) {
            $table->id();
            $table->string('type')->unique();
            $table->string('parent_type')->nullable();
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // Вставляем базовые типы (родители)
        $parentTypes = [
            ['type' => 'material', 'parent_type' => null, 'name' => 'Материал'],
            ['type' => 'equipment', 'parent_type' => null, 'name' => 'Экипировка'],
            ['type' => 'blueprint', 'parent_type' => null, 'name' => 'Чертёж'],
            ['type' => 'bag', 'parent_type' => null, 'name' => 'Сумка'],
        ];

        foreach ($parentTypes as $type) {
            DB::table('slot_types')->insert(array_merge($type, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }

        // Вставляем дочерние типы
        $childTypes = [
            ['type' => 'ore', 'parent_type' => 'material', 'name' => 'Руда'],
            ['type' => 'wood', 'parent_type' => 'material', 'name' => 'Дерево'],
            ['type' => 'ingot', 'parent_type' => 'material', 'name' => 'Слиток'],
            ['type' => 'plank', 'parent_type' => 'material', 'name' => 'Брусок'],
            ['type' => 'gold', 'parent_type' => 'material', 'name' => 'Золото'],
            ['type' => 'equipment_head', 'parent_type' => 'equipment', 'name' => 'Головной убор'],
            ['type' => 'equipment_chest', 'parent_type' => 'equipment', 'name' => 'Нагрудник'],
            ['type' => 'equipment_legs', 'parent_type' => 'equipment', 'name' => 'Поножи'],
            ['type' => 'equipment_weapon', 'parent_type' => 'equipment', 'name' => 'Оружие'],
            ['type' => 'equipment_offhand', 'parent_type' => 'equipment', 'name' => 'Левая рука'],
            ['type' => 'equipment_ring', 'parent_type' => 'equipment', 'name' => 'Кольцо'],
            ['type' => 'equipment_amulet', 'parent_type' => 'equipment', 'name' => 'Амулет'],
        ];

        foreach ($childTypes as $type) {
            DB::table('slot_types')->insert(array_merge($type, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }

        // Теперь добавляем FK после того, как все записи вставлены
        Schema::table('slot_types', function (Blueprint $table) {
            $table->foreign('parent_type')->references('type')->on('slot_types')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('slot_types');
    }
};
