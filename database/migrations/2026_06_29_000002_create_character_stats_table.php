<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('character_stats', function (Blueprint $table) {
            $table->id();
            $table->string('character_uuid', 36)->unique();
            $table->unsignedSmallInteger('level')->default(1);
            $table->unsignedInteger('experience')->default(0);
            $table->unsignedSmallInteger('strength')->default(10);
            $table->unsignedSmallInteger('agility')->default(10);
            $table->unsignedSmallInteger('intellect')->default(10);
            $table->unsignedSmallInteger('stamina')->default(10);
            $table->unsignedSmallInteger('spirit')->default(10);
            $table->timestamps();

            $table->foreign('character_uuid')->references('uuid')->on('characters')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('character_stats');
    }
};
