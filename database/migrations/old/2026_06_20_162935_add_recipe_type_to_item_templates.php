<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL не позволяет напрямую изменить ENUM, поэтому пересоздаем колонку
        DB::statement("ALTER TABLE item_templates MODIFY COLUMN type ENUM('material','equipment','consumable','recipe') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE item_templates MODIFY COLUMN type ENUM('material','equipment','consumable') NOT NULL");
    }
};
