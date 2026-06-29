<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resources', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->uuid('slot_uuid');
            $table->uuid('temporary_slot_uuid')->nullable();
            $table->string('recipe_slug');
            $table->string('template_slug');
            $table->string('slot_type');
            $table->integer('max_stack')->nullable();
            $table->integer('quantity');
            $table->timestamps();

            $table->foreign('slot_uuid')->references('uuid')->on('slots')->onDelete('restrict');
            $table->foreign('temporary_slot_uuid')->references('uuid')->on('temporary_slots')->onDelete('set null');
            $table->foreign('recipe_slug')->references('slug')->on('recipes')->onDelete('restrict');
            $table->foreign('template_slug')->references('slug')->on('item_templates')->onDelete('restrict');
            $table->foreign('slot_type')->references('type')->on('slot_types')->onDelete('restrict');
            $table->index('slot_uuid');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resources');
    }
};
