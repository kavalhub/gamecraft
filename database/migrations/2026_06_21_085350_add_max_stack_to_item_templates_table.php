<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('item_templates', function (Blueprint $table) {
            if (!Schema::hasColumn('item_templates', 'max_stack')) {
                $table->unsignedInteger('max_stack')->default(999)->after('is_stackable');
            }
        });
    }

    public function down(): void
    {
        Schema::table('item_templates', function (Blueprint $table) {
            if (Schema::hasColumn('item_templates', 'max_stack')) {
                $table->dropColumn('max_stack');
            }
        });
    }
};
