<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('temporary_slots', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->uuid('storage_uuid');
            $table->uuid('character_uuid');
            $table->boolean('active')->default(true);
            $table->timestamp('timestamps_end')->nullable();
            $table->timestamps();

            $table->foreign('storage_uuid')->references('uuid')->on('storages')->onDelete('cascade');
            $table->foreign('character_uuid')->references('uuid')->on('characters')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('temporary_slots');
    }
};
