<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('item_templates', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('type'); // material|equipment|blueprint
            $table->string('icon')->nullable();
            $table->boolean('is_stackable')->default(false);
            $table->integer('max_stack')->nullable();
            $table->text('description')->nullable();
            $table->json('base_stats')->nullable();
            $table->string('slot_type')->nullable();
            $table->timestamps();

            $table->foreign('slot_type')->references('type')->on('slot_types')->onDelete('set null');
        });

        // Базовые шаблоны для регистрации
        $templates = [
            [
                'slug' => 'gold',
                'name' => 'Золото',
                'type' => 'material',
                'icon' => '💰',
                'is_stackable' => true,
                'max_stack' => null,
                'description' => 'Золотые монеты — универсальная валюта',
                'slot_type' => 'gold',
            ],
        ];

        foreach ($templates as $template) {
            DB::table('item_templates')->insert(array_merge($template, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('item_templates');
    }
};
