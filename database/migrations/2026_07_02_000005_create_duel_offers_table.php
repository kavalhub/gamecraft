<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('duel_offers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->uuid('challenger_uuid');
            $table->uuid('opponent_uuid');
            $table->string('status')->default('pending');
            $table->string('correlation_uuid')->nullable();
            $table->timestamps();

            $table->foreign('challenger_uuid')->references('uuid')->on('characters')->cascadeOnDelete();
            $table->foreign('opponent_uuid')->references('uuid')->on('characters')->cascadeOnDelete();
            $table->index(['challenger_uuid', 'status']);
            $table->index(['opponent_uuid', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('duel_offers');
    }
};
