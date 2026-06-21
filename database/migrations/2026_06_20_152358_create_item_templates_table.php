<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('item_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->enum('type', ['material', 'equipment', 'consumable']);
            $table->boolean('is_stackable')->default(true);
            $table->string('icon', 50)->default('default.png');
            $table->json('disassemble_data')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_templates');
    }
};
