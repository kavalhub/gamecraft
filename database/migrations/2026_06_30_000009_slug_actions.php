<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('slug_actions', function (Blueprint $table) {
            $table->string('slug')->primary();
            $table->string('label');
            $table->timestamps();
        });

        $now = now();
        $actions = [
            ['slug' => 'create', 'label' => 'Создать'],
            ['slug' => 'disassemble', 'label' => 'Разобрать'],
            ['slug' => 'saw', 'label' => 'Распилить'],
            ['slug' => 'smelt', 'label' => 'Переплавить'],
            ['slug' => 'brew', 'label' => 'Сварить'],
        ];

        foreach ($actions as $action) {
            DB::table('slug_actions')->insert([
                ...$action,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        Schema::table('formulas', function (Blueprint $table) {
            $table->string('action_slug')->nullable()->after('type');
            $table->foreign('action_slug')->references('slug')->on('slug_actions')->nullOnDelete();
        });

        DB::table('formulas')->where('type', 'craft')->whereIn('recipe_slug', [
            'craft_wooden_plank',
        ])->update(['action_slug' => 'saw']);

        DB::table('formulas')->where('type', 'craft')->whereIn('recipe_slug', [
            'craft_iron_ingot',
        ])->update(['action_slug' => 'smelt']);

        DB::table('formulas')->where('type', 'craft')->whereNotIn('recipe_slug', [
            'craft_wooden_plank',
            'craft_iron_ingot',
            'gold',
            'wood',
            'iron_ore',
            'wooden_plank',
            'iron_ingot',
            'experience',
        ])->update(['action_slug' => 'create']);

        DB::table('formulas')->where('type', 'disassemble')->update(['action_slug' => 'disassemble']);
    }

    public function down(): void
    {
        Schema::table('formulas', function (Blueprint $table) {
            $table->dropForeign(['action_slug']);
            $table->dropColumn('action_slug');
        });

        Schema::dropIfExists('slug_actions');
    }
};
