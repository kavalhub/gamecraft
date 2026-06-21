<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trade_offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('initiator_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('partner_id')->constrained('users')->onDelete('cascade');

            $table->enum('status', ['pending', 'active', 'completed', 'cancelled'])->default('pending');

            // Подтверждения сторон
            $table->boolean('initiator_accepted')->default(false);
            $table->boolean('partner_accepted')->default(false);

            // Золото, предлагаемое сторонами
            $table->unsignedBigInteger('initiator_gold')->default(0);
            $table->unsignedBigInteger('partner_gold')->default(0);

            $table->timestamps();

            $table->index(['initiator_id', 'status']);
            $table->index(['partner_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trade_offers');
    }
};
