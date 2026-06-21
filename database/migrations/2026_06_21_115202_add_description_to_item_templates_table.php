<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('item_templates', function (Blueprint $table) {
            if (!Schema::hasColumn('item_templates', 'description')) {
                $table->text('description')->nullable()->after('max_stack');
            }
        });
    }

    public function down(): void
    {
        Schema::table('item_templates', function (Blueprint $table) {
            if (Schema::hasColumn('item_templates', 'description')) {
                $table->dropColumn('description');
            }
        });
    }
};
