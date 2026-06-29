<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('storages', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->uuid('characters_uuid');
            $table->string('storage_type');
            $table->string('name');
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->foreign('characters_uuid')->references('uuid')->on('characters')->onDelete('cascade');
            $table->foreign('storage_type')->references('type')->on('storages_type')->onDelete('restrict');
            $table->index(['characters_uuid', 'storage_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('storages');
    }
};
