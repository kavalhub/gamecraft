<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\WorldAssetCatalog;
use App\Services\WorldService;
use App\Services\ZoneCatalog;
use App\Services\ZoneTileCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ZoneEditorController extends Controller
{
    public function __construct(
        private ZoneCatalog $zoneCatalog,
        private ZoneTileCatalog $tileCatalog,
        private WorldAssetCatalog $assetCatalog,
    ) {}

    public function sprites(): JsonResponse
    {
        $this->assertEnabled();

        return response()->json($this->assetCatalog->listSprites());
    }

    public function updateZone(Request $request, string $zoneSlug): JsonResponse
    {
        $this->assertEnabled();
        $this->assertZoneExists($zoneSlug);

        $request->validate([
            'bounds.min_x' => 'required|numeric',
            'bounds.max_x' => 'required|numeric',
            'bounds.min_z' => 'required|numeric',
            'bounds.max_z' => 'required|numeric',
        ]);

        try {
            $this->zoneCatalog->updateBounds($zoneSlug, $request->input('bounds'));
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'zone' => [
                'slug' => $zoneSlug,
                'bounds' => $this->zoneCatalog->get($zoneSlug)['bounds'] ?? null,
            ],
        ]);
    }

    public function saveTiles(Request $request, string $zoneSlug): JsonResponse
    {
        $this->assertEnabled();
        $this->assertZoneExists($zoneSlug);

        $request->validate([
            'cells' => 'array',
            'cells.*.ground' => 'nullable|string|max:255',
            'cells.*.overlay' => 'nullable|string|max:255',
            'cells.*.sprite' => 'nullable|string|max:255',
            'cells.*.walkable' => 'nullable|boolean',
        ]);

        $this->tileCatalog->save($zoneSlug, [
            'cells' => $request->input('cells', []),
        ]);

        return response()->json([
            'success' => true,
            'tiles' => $this->tileCatalog->get($zoneSlug),
        ]);
    }

    private function assertEnabled(): void
    {
        if (!config('game.zone_editor_enabled', true)) {
            abort(403, 'Редактор зон отключён');
        }
    }

    private function assertZoneExists(string $zoneSlug): void
    {
        if (!$this->zoneCatalog->exists($zoneSlug)) {
            abort(404, 'Зона не найдена');
        }
    }
}
