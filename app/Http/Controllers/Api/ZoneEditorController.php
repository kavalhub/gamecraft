<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\WorldAssetCatalog;
use App\Services\WorldService;
use App\Services\ZoneCatalog;
use App\Services\ZoneStampCatalog;
use App\Services\ZoneTileCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ZoneEditorController extends Controller
{
    public function __construct(
        private ZoneCatalog $zoneCatalog,
        private ZoneTileCatalog $tileCatalog,
        private ZoneStampCatalog $stampCatalog,
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

    public function listStamps(): JsonResponse
    {
        $this->assertEnabled();

        return response()->json([
            'stamps' => $this->stampCatalog->list(),
        ]);
    }

    public function getStamp(string $stampId): JsonResponse
    {
        $this->assertEnabled();
        $this->assertStampId($stampId);

        try {
            $stamp = $this->stampCatalog->get($stampId);
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 404);
        }

        return response()->json([
            'stamp' => array_merge(['id' => $stampId], $stamp),
        ]);
    }

    public function createStamp(Request $request): JsonResponse
    {
        $this->assertEnabled();

        $request->validate([
            'id' => 'required|string|max:64|regex:/^[a-z0-9_-]+$/',
            'name' => 'required|string|max:120',
            'cells' => 'required|array',
            'cells.*.ground' => 'nullable|string|max:255',
            'cells.*.overlay' => 'nullable|string|max:255',
            'cells.*.sprite' => 'nullable|string|max:255',
            'cells.*.walkable' => 'nullable|boolean',
        ]);

        $stampId = (string) $request->input('id');
        if ($this->stampCatalog->exists($stampId)) {
            return response()->json(['success' => false, 'error' => 'Штамп с таким id уже существует'], 422);
        }

        $cells = $request->input('cells', []);
        if ($cells === []) {
            return response()->json(['success' => false, 'error' => 'Выделение пустое'], 422);
        }

        $this->stampCatalog->save($stampId, [
            'name' => $request->input('name'),
            'cells' => $cells,
        ]);

        return response()->json([
            'success' => true,
            'stamp' => array_merge(['id' => $stampId], $this->stampCatalog->get($stampId)),
        ], 201);
    }

    public function updateStamp(Request $request, string $stampId): JsonResponse
    {
        $this->assertEnabled();
        $this->assertStampId($stampId);

        if (!$this->stampCatalog->exists($stampId)) {
            return response()->json(['success' => false, 'error' => 'Штамп не найден'], 404);
        }

        $request->validate([
            'name' => 'sometimes|string|max:120',
            'cells' => 'sometimes|array',
            'cells.*.ground' => 'nullable|string|max:255',
            'cells.*.overlay' => 'nullable|string|max:255',
            'cells.*.sprite' => 'nullable|string|max:255',
            'cells.*.walkable' => 'nullable|boolean',
        ]);

        $existing = $this->stampCatalog->get($stampId);
        $cells = $request->has('cells') ? $request->input('cells', []) : $existing['cells'];
        if ($cells === []) {
            return response()->json(['success' => false, 'error' => 'Штамп не может быть пустым'], 422);
        }

        $this->stampCatalog->save($stampId, [
            'name' => $request->input('name', $existing['name']),
            'cells' => $cells,
        ]);

        return response()->json([
            'success' => true,
            'stamp' => array_merge(['id' => $stampId], $this->stampCatalog->get($stampId)),
        ]);
    }

    public function deleteStamp(string $stampId): JsonResponse
    {
        $this->assertEnabled();
        $this->assertStampId($stampId);

        if (!$this->stampCatalog->exists($stampId)) {
            return response()->json(['success' => false, 'error' => 'Штамп не найден'], 404);
        }

        $this->stampCatalog->delete($stampId);

        return response()->json(['success' => true]);
    }

    private function assertStampId(string $stampId): void
    {
        if (!preg_match('/^[a-z0-9_-]+$/', $stampId)) {
            abort(404, 'Штамп не найден');
        }
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
