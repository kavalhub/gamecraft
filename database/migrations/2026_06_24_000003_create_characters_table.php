<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('characters', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->uuid('user_uuid')->nullable();
            $table->string('character_type');
            $table->string('name');
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->foreign('user_uuid')->references('uuid')->on('users')->onDelete('set null');
            $table->foreign('character_type')->references('type')->on('characters_type')->onDelete('restrict');
            $table->index(['user_uuid', 'active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('characters');
    }
};
