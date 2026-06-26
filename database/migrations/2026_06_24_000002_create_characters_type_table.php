<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('characters_type', function (Blueprint $table) {
            $table->id();
            $table->string('type')->unique();
            $table->string('name');
            $table->string('parent_type')->nullable();
            $table->timestamps();
        });

        // Базовые типы сущностей
        $types = [
            ['type' => 'player', 'name' => 'Персонаж', 'parent_type' => null],
            ['type' => 'npc', 'name' => 'Неигровой персонаж', 'parent_type' => null],
            ['type' => 'npc_merchant', 'name' => 'Торговец', 'parent_type' => 'npc'],
            ['type' => 'npc_quest_giver', 'name' => 'Квестодатель', 'parent_type' => 'npc'],
            ['type' => 'auction', 'name' => 'Аукцион', 'parent_type' => null],
            ['type' => 'guild', 'name' => 'Гильдия', 'parent_type' => null],
            ['type' => 'alliance', 'name' => 'Альянс', 'parent_type' => null],
            ['type' => 'location', 'name' => 'Локация', 'parent_type' => null],
            ['type' => 'chest', 'name' => 'Сундук', 'parent_type' => null],
            ['type' => 'post', 'name' => 'Почта', 'parent_type' => null],
            ['type' => 'system', 'name' => 'Система', 'parent_type' => null],
        ];

        foreach ($types as $type) {
            DB::table('characters_type')->insert(array_merge($type, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('characters_type');
    }
};
