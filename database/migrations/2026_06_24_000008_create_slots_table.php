<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('slots', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->uuid('storage_uuid');
            $table->string('slot_type')->nullable();
            $table->timestamps();

            $table->foreign('storage_uuid')->references('uuid')->on('storages')->onDelete('cascade');
            $table->foreign('slot_type')->references('type')->on('slot_types')->onDelete('set null');
            $table->index('storage_uuid');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('slots');
    }
};
