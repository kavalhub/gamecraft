<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Character;
use App\Services\WorldService;
use App\Services\ZoneCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorldController extends Controller
{
    public function __construct(
        private WorldService $worldService,
        private ZoneCatalog $zoneCatalog,
    ) {}

    public function show(string $characterUuid): JsonResponse
    {
        $character = Character::where('uuid', $characterUuid)->firstOrFail();

        return response()->json([
            'state' => $this->worldService->getState($character),
        ]);
    }

    public function context(string $characterUuid): JsonResponse
    {
        $character = Character::where('uuid', $characterUuid)->firstOrFail();

        return response()->json($this->worldService->getContext($character));
    }

    public function zone(string $zoneSlug): JsonResponse
    {
        try {
            return response()->json([
                'zone' => $this->worldService->getZoneMeta($zoneSlug),
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 404);
        }
    }

    public function zoneTiles(string $zoneSlug): JsonResponse
    {
        try {
            return response()->json([
                'zone_slug' => $zoneSlug,
                'tiles' => $this->worldService->getZoneTiles($zoneSlug),
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 404);
        }
    }

    public function zones(): JsonResponse
    {
        return response()->json([
            'zones' => array_map(
                fn (array $zone) => $this->worldService->getZoneMeta((string) $zone['slug']),
                $this->zoneCatalog->all(),
            ),
        ]);
    }

    public function move(Request $request, string $characterUuid): JsonResponse
    {
        $request->validate([
            'x' => 'required|numeric',
            'y' => 'required|numeric',
            'z' => 'required|numeric',
            'rotation_y' => 'nullable|numeric',
        ]);

        $character = Character::where('uuid', $characterUuid)->firstOrFail();

        try {
            $result = $this->worldService->move(
                $character,
                (float) $request->input('x'),
                (float) $request->input('y'),
                (float) $request->input('z'),
                $request->has('rotation_y') ? (float) $request->input('rotation_y') : null,
            );

            return response()->json([
                'success' => true,
                'state' => $result['state'],
                'portal_used' => $result['portal_used'],
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 422);
        }
    }

    public function step(Request $request, string $characterUuid): JsonResponse
    {
        $request->validate([
            'direction' => 'required|string|in:north,south,east,west',
        ]);

        $character = Character::where('uuid', $characterUuid)->firstOrFail();

        try {
            $result = $this->worldService->step(
                $character,
                $request->string('direction')->toString(),
            );

            return response()->json([
                'success' => true,
                'state' => $result['state'],
                'portal_used' => $result['portal_used'],
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 422);
        }
    }

    public function interact(Request $request, string $characterUuid): JsonResponse
    {
        $request->validate([
            'target_id' => 'required|string',
        ]);

        $character = Character::where('uuid', $characterUuid)->firstOrFail();

        try {
            return response()->json(
                $this->worldService->interact($character, $request->string('target_id')->toString()),
            );
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 422);
        }
    }

    public function usePortal(Request $request, string $characterUuid): JsonResponse
    {
        $request->validate([
            'portal_id' => 'required|string',
        ]);

        $character = Character::where('uuid', $characterUuid)->firstOrFail();

        try {
            $result = $this->worldService->usePortal(
                $character,
                $request->string('portal_id')->toString(),
            );

            return response()->json([
                'success' => true,
                'state' => $result['state'],
                'portal_used' => $result['portal_used'],
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 422);
        }
    }

    public function enterZone(Request $request, string $characterUuid): JsonResponse
    {
        $request->validate([
            'zone_slug' => 'required|string',
            'spawn_id' => 'nullable|string',
        ]);

        $character = Character::where('uuid', $characterUuid)->firstOrFail();

        try {
            $result = $this->worldService->enterZone(
                $character,
                $request->string('zone_slug')->toString(),
                $request->input('spawn_id', 'default'),
            );

            return response()->json([
                'success' => true,
                'state' => $result['state'],
                'portal_used' => $result['portal_used'],
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 422);
        }
    }

    public function nearby(Request $request, string $characterUuid): JsonResponse
    {
        $request->validate([
            'radius' => 'nullable|numeric|min:1|max:200',
        ]);

        $character = Character::where('uuid', $characterUuid)->firstOrFail();
        $radius = (float) $request->input('radius', 30);

        return response()->json([
            'nearby' => $this->worldService->nearby($character, $radius),
            'state' => $this->worldService->getState($character),
        ]);
    }
}
