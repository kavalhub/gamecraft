<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('item_templates', function (Blueprint $table) {
            $table->string('recipe_slug')->nullable()->after('slot_type');
            $table->foreign('recipe_slug')->references('slug')->on('recipes')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('item_templates', function (Blueprint $table) {
            $table->dropForeign(['recipe_slug']);
            $table->dropColumn('recipe_slug');
        });
    }
};
