<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Сначала удаляем FK
        $fks = DB::select("
            SELECT CONSTRAINT_NAME 
            FROM information_schema.KEY_COLUMN_USAGE 
            WHERE TABLE_SCHEMA = DATABASE() 
            AND TABLE_NAME = 'items' 
            AND COLUMN_NAME = 'recipe_slug' 
            AND REFERENCED_TABLE_NAME IS NOT NULL
        ");

        Schema::table('items', function (Blueprint $table) use ($fks) {
            foreach ($fks as $fk) {
                $table->dropForeign($fk->CONSTRAINT_NAME);
            }
        });

        // Делаем nullable
        Schema::table('items', function (Blueprint $table) {
            $table->string('recipe_slug')->nullable()->change();
        });

        // Возвращаем FK
        Schema::table('items', function (Blueprint $table) {
            $table->foreign('recipe_slug')->references('slug')->on('recipes')->onDelete('set null');
        });
    }

    public function down(): void
    {
        $fks = DB::select("
            SELECT CONSTRAINT_NAME 
            FROM information_schema.KEY_COLUMN_USAGE 
            WHERE TABLE_SCHEMA = DATABASE() 
            AND TABLE_NAME = 'items' 
            AND COLUMN_NAME = 'recipe_slug' 
            AND REFERENCED_TABLE_NAME IS NOT NULL
        ");

        Schema::table('items', function (Blueprint $table) use ($fks) {
            foreach ($fks as $fk) {
                $table->dropForeign($fk->CONSTRAINT_NAME);
            }
        });

        Schema::table('items', function (Blueprint $table) {
            $table->string('recipe_slug')->nullable(false)->change();
        });

        Schema::table('items', function (Blueprint $table) {
            $table->foreign('recipe_slug')->references('slug')->on('recipes')->onDelete('restrict');
        });
    }
};
