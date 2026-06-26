<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trade_offers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->uuid('initiator_uuid');
            $table->uuid('partner_uuid');
            $table->string('status')->default('pending');
            $table->boolean('initiator_accepted')->default(false);
            $table->boolean('partner_accepted')->default(false);
            $table->timestamps();

            $table->foreign('initiator_uuid')->references('uuid')->on('characters')->onDelete('cascade');
            $table->foreign('partner_uuid')->references('uuid')->on('characters')->onDelete('cascade');
        });

        Schema::create('trade_items', function (Blueprint $table) {
            $table->id();
            $table->uuid('trade_uuid');
            $table->uuid('character_uuid');
            $table->uuid('item_uuid')->nullable();
            $table->uuid('resource_uuid')->nullable();
            $table->integer('quantity');
            $table->timestamps();

            $table->foreign('trade_uuid')->references('uuid')->on('trade_offers')->onDelete('cascade');
            $table->foreign('character_uuid')->references('uuid')->on('characters')->onDelete('cascade');
            $table->index('trade_uuid');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trade_items');
        Schema::dropIfExists('trade_offers');
    }
};
