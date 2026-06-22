<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('auction_history', function (Blueprint $table) {
            // Проверяем, есть ли уже колонка
            if (!Schema::hasColumn('auction_history', 'lot_id')) {
                $table->foreignId('lot_id')
                    ->after('id')
                    ->constrained('auction_lots')
                    ->onDelete('cascade');
            }
        });
    }

    public function down(): void
    {
        Schema::table('auction_history', function (Blueprint $table) {
            if (Schema::hasColumn('auction_history', 'lot_id')) {
                $table->dropForeign(['lot_id']);
                $table->dropColumn('lot_id');
            }
        });
    }
};
