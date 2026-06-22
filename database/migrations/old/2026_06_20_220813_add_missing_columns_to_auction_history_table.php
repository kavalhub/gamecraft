<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('auction_history', function (Blueprint $table) {
            // lot_id
            if (!Schema::hasColumn('auction_history', 'lot_id')) {
                $table->unsignedBigInteger('lot_id')->after('id');
            }

            // seller_id
            if (!Schema::hasColumn('auction_history', 'seller_id')) {
                $table->unsignedBigInteger('seller_id')->nullable()->after('lot_id');
            }

            // buyer_id
            if (!Schema::hasColumn('auction_history', 'buyer_id')) {
                $table->unsignedBigInteger('buyer_id')->nullable()->after('seller_id');
            }

            // template_id
            if (!Schema::hasColumn('auction_history', 'template_id')) {
                $table->unsignedBigInteger('template_id')->nullable()->after('buyer_id');
            }

            // quantity
            if (!Schema::hasColumn('auction_history', 'quantity')) {
                $table->integer('quantity')->default(1)->after('template_id');
            }

            // price
            if (!Schema::hasColumn('auction_history', 'price')) {
                $table->unsignedBigInteger('price')->default(0)->after('quantity');
            }

            // commission
            if (!Schema::hasColumn('auction_history', 'commission')) {
                $table->unsignedBigInteger('commission')->default(0)->after('price');
            }

            // seller_received
            if (!Schema::hasColumn('auction_history', 'seller_received')) {
                $table->unsignedBigInteger('seller_received')->default(0)->after('commission');
            }

            // action
            if (!Schema::hasColumn('auction_history', 'action')) {
                $table->string('action', 20)->nullable()->after('seller_received');
            }

            // occurred_at
            if (!Schema::hasColumn('auction_history', 'occurred_at')) {
                $table->timestamp('occurred_at')->nullable()->after('action');
            }
        });

        // Добавляем foreign keys отдельно (после того как все колонки созданы)
        Schema::table('auction_history', function (Blueprint $table) {
            if (Schema::hasColumn('auction_history', 'lot_id') && !collect(DB::select("SHOW KEYS FROM auction_history WHERE Key_name = 'auction_history_lot_id_foreign'"))->isNotEmpty()) {
                $table->foreign('lot_id')->references('id')->on('auction_lots')->onDelete('cascade');
            }
            if (Schema::hasColumn('auction_history', 'seller_id') && !collect(DB::select("SHOW KEYS FROM auction_history WHERE Key_name = 'auction_history_seller_id_foreign'"))->isNotEmpty()) {
                $table->foreign('seller_id')->references('id')->on('users')->onDelete('cascade');
            }
            if (Schema::hasColumn('auction_history', 'buyer_id') && !collect(DB::select("SHOW KEYS FROM auction_history WHERE Key_name = 'auction_history_buyer_id_foreign'"))->isNotEmpty()) {
                $table->foreign('buyer_id')->references('id')->on('users')->onDelete('set null');
            }
            if (Schema::hasColumn('auction_history', 'template_id') && !collect(DB::select("SHOW KEYS FROM auction_history WHERE Key_name = 'auction_history_template_id_foreign'"))->isNotEmpty()) {
                $table->foreign('template_id')->references('id')->on('item_templates')->onDelete('cascade');
            }
        });

        // Индексы
        Schema::table('auction_history', function (Blueprint $table) {
            if (Schema::hasColumn('auction_history', 'template_id')) {
                $table->index('template_id');
            }
            if (Schema::hasColumn('auction_history', 'occurred_at')) {
                $table->index('occurred_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('auction_history', function (Blueprint $table) {
            $table->dropForeign(['lot_id']);
            $table->dropForeign(['seller_id']);
            $table->dropForeign(['buyer_id']);
            $table->dropForeign(['template_id']);

            $table->dropColumn([
                'lot_id', 'seller_id', 'buyer_id', 'template_id',
                'quantity', 'price', 'commission', 'seller_received',
                'action', 'occurred_at'
            ]);
        });
    }
};
