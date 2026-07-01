<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Character;

final class ItemMaterialsUsed
{
    /**
     * @return array{resources: array<string, int>, crafter: array<string, string>|null, modifiers: array, material_stats: array}
     */
    public static function normalize(?array $data): array
    {
        if ($data === null || $data === []) {
            return [
                'resources' => [],
                'crafter' => null,
                'modifiers' => [],
                'material_stats' => [],
            ];
        }

        if (isset($data['resources']) && is_array($data['resources'])) {
            return [
                'resources' => $data['resources'],
                'crafter' => $data['crafter'] ?? null,
                'modifiers' => $data['modifiers'] ?? [],
                'material_stats' => $data['material_stats'] ?? [],
            ];
        }

        return [
            'resources' => $data,
            'crafter' => null,
            'modifiers' => [],
            'material_stats' => [],
        ];
    }

    /**
     * @return array<string, int>
     */
    public static function resources(?array $data): array
    {
        return self::normalize($data)['resources'];
    }

    /**
     * @param  array<string, int>  $resources
     * @return array{resources: array<string, int>, crafter: array<string, string>, modifiers: array, material_stats: array}
     */
    public static function build(Character $character, array $resources): array
    {
        return [
            'resources' => $resources,
            'crafter' => [
                'character_uuid' => $character->uuid,
                'character_name' => $character->name,
            ],
            'modifiers' => [],
            'material_stats' => [],
        ];
    }
}
