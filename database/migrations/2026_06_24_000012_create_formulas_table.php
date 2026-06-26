<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('formulas', function (Blueprint $table) {
            $table->id();
            $table->string('recipe_slug');
            $table->string('type'); // craft|disassemble
            $table->integer('priority')->default(100);
            $table->integer('chance')->default(100);
            $table->json('conditions')->nullable();
            $table->json('formula');
            $table->boolean('is_active')->default(true);
            $table->string('description')->nullable();
            $table->timestamps();

            $table->foreign('recipe_slug')->references('slug')->on('recipes')->onDelete('cascade');
            $table->index(['recipe_slug', 'type', 'is_active', 'priority']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('formulas');
    }
};
