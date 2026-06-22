<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('item_templates', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 100)->unique();
            $table->string('name', 100);
            $table->enum('type', ['material', 'equipment', 'consumable', 'recipe']);
            $table->string('icon', 50)->default('default.png');
            $table->boolean('is_stackable')->default(true);
            $table->unsignedInteger('max_stack')->default(999);
            $table->text('description')->nullable();
            $table->json('disassemble_data')->nullable();
            $table->json('stats')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_templates');
    }
};
