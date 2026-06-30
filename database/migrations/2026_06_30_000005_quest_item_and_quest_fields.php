<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('item_templates', function (Blueprint $table) {
            $table->string('quest_slug')->nullable()->after('recipe_slug');
            $table->foreign('quest_slug')->references('slug')->on('quests')->nullOnDelete();
        });

        Schema::table('quests', function (Blueprint $table) {
            $table->json('accept_grants')->nullable()->after('description');
            $table->string('starter_item_template_slug')->nullable()->after('accept_grants');
        });

        Schema::table('character_quests', function (Blueprint $table) {
            $table->timestamp('storage_prepared_at')->nullable()->after('turned_in_at');
        });

        $now = now();

        DB::table('slot_types')->insertOrIgnore([
            [
                'type' => 'quest_item',
                'parent_type' => null,
                'name' => 'Квестовый предмет',
                'description' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'type' => 'experience',
                'parent_type' => null,
                'name' => 'Опыт',
                'description' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        $stubRecipes = [
            [
                'slug' => 'quest_item_stub',
                'type' => 'blueprint',
                'name' => 'Квестовый предмет (заглушка)',
                'description' => 'Служебный рецепт для квестовых предметов',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'slug' => 'experience',
                'type' => 'resource',
                'name' => 'Опыт',
                'description' => 'Служебный рецепт для ресурса опыта',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        foreach ($stubRecipes as $recipe) {
            if (!DB::table('recipes')->where('slug', $recipe['slug'])->exists()) {
                DB::table('recipes')->insert($recipe);
            }
        }

        if (!DB::table('item_templates')->where('slug', 'experience')->exists()) {
            DB::table('item_templates')->insert([
                'slug' => 'experience',
                'name' => 'Опыт',
                'type' => 'material',
                'icon' => '⭐',
                'is_stackable' => true,
                'max_stack' => null,
                'description' => 'Очки опыта персонажа',
                'slot_type' => 'experience',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('character_quests', function (Blueprint $table) {
            $table->dropColumn('storage_prepared_at');
        });

        Schema::table('quests', function (Blueprint $table) {
            $table->dropColumn(['accept_grants', 'starter_item_template_slug']);
        });

        Schema::table('item_templates', function (Blueprint $table) {
            $table->dropForeign(['quest_slug']);
            $table->dropColumn('quest_slug');
        });

        DB::table('item_templates')->where('slug', 'experience')->delete();
        DB::table('recipes')->whereIn('slug', ['quest_item_stub', 'experience'])->delete();
        DB::table('slot_types')->whereIn('type', ['quest_item', 'experience'])->delete();
    }
};
