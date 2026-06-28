<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Удаляем дубликаты - оставляем только последний pending обмен для каждой пары
        $duplicates = DB::select("
            SELECT t1.uuid
            FROM trade_offers t1
            INNER JOIN trade_offers t2 
                ON t1.status = 'pending' 
                AND t2.status = 'pending'
                AND t1.id < t2.id
                AND (
                    (t1.initiator_uuid = t2.initiator_uuid AND t1.partner_uuid = t2.partner_uuid)
                    OR (t1.initiator_uuid = t2.partner_uuid AND t1.partner_uuid = t2.initiator_uuid)
                )
        ");
        
        foreach ($duplicates as $dup) {
            DB::table('trade_offers')->where('uuid', $dup->uuid)->delete();
        }
    }

    public function down(): void
    {
        // Откат невозможен
    }
};
