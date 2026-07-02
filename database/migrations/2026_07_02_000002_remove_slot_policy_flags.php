<?php

use App\Models\StorageType;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        foreach (StorageType::all() as $type) {
            $allowed = $type->allowed_types;
            if (!is_array($allowed) || !isset($allowed['slots'])) {
                continue;
            }

            $changed = false;
            foreach ($allowed['slots'] as $index => $slotDef) {
                if (!is_array($slotDef)) {
                    continue;
                }

                if (array_key_exists('priority_fill', $slotDef) || array_key_exists('auto_reclaim', $slotDef)) {
                    unset($allowed['slots'][$index]['priority_fill'], $allowed['slots'][$index]['auto_reclaim']);
                    $changed = true;
                }
            }

            if ($changed) {
                $type->update(['allowed_types' => $allowed]);
            }
        }
    }

    public function down(): void
    {
        // Flags removed intentionally.
    }
};
