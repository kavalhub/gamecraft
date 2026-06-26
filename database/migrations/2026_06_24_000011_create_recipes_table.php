<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recipes', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('type'); // blueprint|resource
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // Базовые рецепты для ресурсов
        $recipes = [
            ['slug' => 'gold', 'type' => 'resource', 'name' => 'Золото', 'description' => 'Универсальная валюта'],
            ['slug' => 'wood', 'type' => 'resource', 'name' => 'Дерево', 'description' => 'Базовый материал'],
            ['slug' => 'iron_ore', 'type' => 'resource', 'name' => 'Железная руда', 'description' => 'Базовый материал'],
            ['slug' => 'wooden_plank', 'type' => 'resource', 'name' => 'Деревянный брусок', 'description' => 'Базовый материал'],
            ['slug' => 'iron_ingot', 'type' => 'resource', 'name' => 'Железный слиток', 'description' => 'Базовый материал'],
        ];

        foreach ($recipes as $recipe) {
            DB::table('recipes')->insert(array_merge($recipe, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('recipes');
    }
};
