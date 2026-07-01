<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('temporary_slots', function (Blueprint $table) {
            $table->string('quest_slug')->nullable()->after('slot_index');
            $table->string('slot_role', 32)->nullable()->after('quest_slug');
            $table->index(['storage_uuid', 'quest_slug', 'slot_role']);
        });

        $now = now();

        DB::table('slot_types')->insertOrIgnore([
            [
                'type' => 'quest_requirement',
                'parent_type' => 'quest',
                'name' => 'Сдача квеста',
                'description' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        $systemUuid = DB::table('characters')
            ->where('character_type', 'system')
            ->value('uuid');

        if ($systemUuid) {
            $exists = DB::table('storages')
                ->where('characters_uuid', $systemUuid)
                ->where('storage_type', 'world')
                ->exists();

            if (!$exists) {
                DB::table('storages')->insert([
                    'uuid' => (string) Str::uuid(),
                    'characters_uuid' => $systemUuid,
                    'storage_type' => 'world',
                    'name' => 'Мир',
                    'active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('temporary_slots', function (Blueprint $table) {
            $table->dropIndex(['storage_uuid', 'quest_slug', 'slot_role']);
            $table->dropColumn(['quest_slug', 'slot_role']);
        });

        DB::table('slot_types')->where('type', 'quest_requirement')->delete();
    }
};
