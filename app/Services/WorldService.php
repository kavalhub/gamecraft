<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Character;
use App\Models\CharacterWorldState;
use Illuminate\Support\Facades\DB;

class WorldService
{
    public const DEFAULT_ZONE = 'craft_city';

    /** @var array<string, array{float, float}> */
    private const STEP_VECTORS = [
        'north' => [0.0, 1.0],
        'south' => [0.0, -1.0],
        'east' => [1.0, 0.0],
        'west' => [-1.0, 0.0],
    ];

    public function __construct(
        private ZoneCatalog $zoneCatalog,
        private ZoneTileCatalog $zoneTileCatalog,
        private EventStore $eventStore,
    ) {}

    public function ensureSpawn(Character $character): CharacterWorldState
    {
        $existing = CharacterWorldState::where('character_uuid', $character->uuid)->first();
        if ($existing) {
            return $existing;
        }

        return $this->spawnInZone($character, self::DEFAULT_ZONE, 'default', recordEvent: true);
    }

    /**
     * @return array<string, mixed>
     */
    public function getState(Character $character): array
    {
        $state = $this->ensureSpawn($character);

        return $this->formatStateResponse($character, $state);
    }

    /**
     * @return array<string, mixed>
     */
    public function getContext(Character $character): array
    {
        $state = $this->ensureSpawn($character);
        $zone = $this->zoneCatalog->get($state->zone_slug);
        $interactRadius = (float) config('game.world_interact_radius', 5.0);
        $portalRadius = (float) config('game.world_portal_radius', 4.0);
        $nearbyRadius = (float) config('game.world_nearby_radius', 30.0);

        return [
            'state' => $this->formatStateResponse($character, $state),
            'nearby_players' => $this->nearby($character, $nearbyRadius),
            'nearby_interactables' => $this->filterNearbyPoints(
                $state,
                $zone['interactables'] ?? [],
                $interactRadius,
            ),
            'nearby_portals' => $this->filterNearbyPoints(
                $state,
                $zone['portals'] ?? [],
                $portalRadius,
            ),
        ];
    }

    /**
     * @return array{version: string, cells: array<string, array<string, mixed>>}
     */
    public function getZoneTiles(string $zoneSlug): array
    {
        if (!$this->zoneCatalog->exists($zoneSlug)) {
            throw new \RuntimeException("Зона не найдена: {$zoneSlug}");
        }

        return $this->zoneTileCatalog->get($zoneSlug);
    }

    /**
     * @return array<string, mixed>
     */
    public function getZoneMeta(string $zoneSlug): array
    {
        $zone = $this->zoneCatalog->get($zoneSlug);

        return [
            'slug' => $zone['slug'],
            'name' => $zone['name'] ?? $zoneSlug,
            'description' => $zone['description'] ?? null,
            'bounds' => $zone['bounds'] ?? null,
            'portals' => $zone['portals'] ?? [],
            'interactables' => $zone['interactables'] ?? [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function enterZone(Character $character, string $zoneSlug, ?string $spawnId = 'default'): array
    {
        if (!$this->zoneCatalog->exists($zoneSlug)) {
            throw new \RuntimeException("Зона не найдена: {$zoneSlug}");
        }

        return DB::transaction(function () use ($character, $zoneSlug, $spawnId) {
            $state = CharacterWorldState::where('character_uuid', $character->uuid)->first();
            $previousZone = $state?->zone_slug;

            $spawned = $this->spawnInZone($character, $zoneSlug, $spawnId ?? 'default', recordEvent: false);

            if ($previousZone !== $zoneSlug) {
                $this->recordEnteredZone($character, $spawned, $zoneSlug, $previousZone);
            }

            return $this->wrapState($character, $spawned);
        });
    }

    /**
     * @return array{state: array<string, mixed>, portal_used: array<string, mixed>|null}
     */
    public function move(Character $character, float $x, float $y, float $z, ?float $rotationY = null): array
    {
        return DB::transaction(function () use ($character, $x, $y, $z, $rotationY) {
            $state = $this->ensureSpawn($character);
            $zone = $this->zoneCatalog->get($state->zone_slug);
            $bounds = $zone['bounds'] ?? [];

            if (!$this->isInsideBounds($x, $z, $bounds)) {
                throw new \RuntimeException('Позиция вне границ зоны');
            }

            if (!$this->zoneTileCatalog->isWalkable($state->zone_slug, $x, $z)) {
                throw new \RuntimeException('Сюда нельзя пройти');
            }

            $this->assertSpeedAllowed($state, $x, $y, $z);

            $state->x = $x;
            $state->y = $y;
            $state->z = $z;
            if ($rotationY !== null) {
                $state->rotation_y = $rotationY;
            }
            $state->moved_at = now();
            $state->save();

            $this->recordMoved($character, $state);

            $portalUsed = $this->tryPortalTransition($character, $state);
            if ($portalUsed) {
                $state = CharacterWorldState::where('character_uuid', $character->uuid)->firstOrFail();
            }

            return $this->wrapState($character, $state, $portalUsed);
        });
    }

    /**
     * @return array{state: array<string, mixed>, portal_used: array<string, mixed>|null}
     */
    public function step(Character $character, string $direction): array
    {
        $vector = self::STEP_VECTORS[$direction] ?? null;
        if (!$vector) {
            throw new \RuntimeException('Неизвестное направление: ' . $direction);
        }

        $state = $this->ensureSpawn($character);
        $step = (float) config('game.world_step_size', 3.0);

        return $this->move(
            $character,
            $state->x + ($vector[0] * $step),
            $state->y,
            $state->z + ($vector[1] * $step),
            $state->rotation_y,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function interact(Character $character, string $targetId): array
    {
        return DB::transaction(function () use ($character, $targetId) {
            $state = $this->ensureSpawn($character);
            $interactable = $this->zoneCatalog->findInteractable($state->zone_slug, $targetId);

            if (!$interactable) {
                throw new \RuntimeException('Объект не найден');
            }

            $radius = (float) config('game.world_interact_radius', 5.0);
            $distance = $this->distance2d(
                $state->x,
                $state->z,
                (float) ($interactable['x'] ?? 0),
                (float) ($interactable['z'] ?? 0),
            );

            if ($distance > $radius) {
                throw new \RuntimeException('Слишком далеко для взаимодействия');
            }

            $this->eventStore->record(
                'world.interacted',
                'world',
                $state->zone_slug,
                [
                    'character_uuid' => $character->uuid,
                    'character_name' => $character->name,
                    'zone_slug' => $state->zone_slug,
                    'target_id' => $targetId,
                    'target_name' => $interactable['name'] ?? $targetId,
                    'action' => $interactable['action'] ?? null,
                    'window' => $interactable['window'] ?? null,
                ],
                $character->uuid,
            );

            return [
                'success' => true,
                'target_id' => $targetId,
                'target_name' => $interactable['name'] ?? $targetId,
                'action' => $interactable['action'] ?? null,
                'window' => $interactable['window'] ?? null,
                'state' => $this->formatStateResponse($character, $state),
            ];
        });
    }

    /**
     * @return array{state: array<string, mixed>, portal_used: array<string, mixed>|null}
     */
    public function usePortal(Character $character, string $portalId): array
    {
        return DB::transaction(function () use ($character, $portalId) {
            $state = $this->ensureSpawn($character);
            $portal = $this->zoneCatalog->findPortal($state->zone_slug, $portalId);

            if (!$portal) {
                throw new \RuntimeException('Портал не найден');
            }

            $radius = (float) config('game.world_portal_radius', 4.0);
            $distance = $this->distance2d(
                $state->x,
                $state->z,
                (float) ($portal['x'] ?? 0),
                (float) ($portal['z'] ?? 0),
            );

            if ($distance > $radius) {
                throw new \RuntimeException('Слишком далеко от портала');
            }

            $fromZone = $state->zone_slug;
            $spawned = $this->spawnInZone(
                $character,
                (string) $portal['target_zone'],
                (string) ($portal['target_spawn'] ?? 'default'),
                recordEvent: false,
            );

            if ($fromZone !== $spawned->zone_slug) {
                $this->recordEnteredZone($character, $spawned, $spawned->zone_slug, $fromZone);
            }

            $portalUsed = [
                'id' => $portalId,
                'from_zone' => $fromZone,
                'to_zone' => $spawned->zone_slug,
            ];

            return $this->wrapState($character, $spawned, $portalUsed);
        });
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function nearby(Character $character, float $radius = 30.0): array
    {
        $state = $this->ensureSpawn($character);
        $radiusSq = $radius * $radius;

        $others = CharacterWorldState::query()
            ->where('zone_slug', $state->zone_slug)
            ->where('character_uuid', '!=', $character->uuid)
            ->with('character')
            ->get();

        $result = [];
        foreach ($others as $other) {
            $dx = $other->x - $state->x;
            $dz = $other->z - $state->z;
            if (($dx * $dx + $dz * $dz) > $radiusSq) {
                continue;
            }

            $otherCharacter = $other->character;
            if (!$otherCharacter) {
                continue;
            }

            $result[] = [
                'character_uuid' => $other->character_uuid,
                'character_name' => $otherCharacter->name,
                'avatar_icon' => $otherCharacter->avatarIcon(),
                'x' => $other->x,
                'y' => $other->y,
                'z' => $other->z,
                'rotation_y' => $other->rotation_y,
                'distance' => round(sqrt($dx * $dx + $dz * $dz), 2),
            ];
        }

        usort($result, fn ($a, $b) => $a['distance'] <=> $b['distance']);

        return $result;
    }

    public function getZoneSlugForCharacter(string $characterUuid): ?string
    {
        return CharacterWorldState::where('character_uuid', $characterUuid)->value('zone_slug');
    }

    private function spawnInZone(
        Character $character,
        string $zoneSlug,
        string $spawnId,
        bool $recordEvent,
    ): CharacterWorldState {
        $spawn = $this->zoneCatalog->getSpawn($zoneSlug, $spawnId);

        $state = CharacterWorldState::updateOrCreate(
            ['character_uuid' => $character->uuid],
            [
                'zone_slug' => $zoneSlug,
                'x' => $spawn['x'],
                'y' => $spawn['y'],
                'z' => $spawn['z'],
                'rotation_y' => $spawn['rotation_y'],
                'moved_at' => now(),
            ],
        );

        if ($recordEvent) {
            $this->recordEnteredZone($character, $state, $zoneSlug, null);
        }

        return $state->fresh();
    }

    /**
     * @param list<array<string, mixed>> $points
     * @return list<array<string, mixed>>
     */
    private function filterNearbyPoints(CharacterWorldState $state, array $points, float $radius): array
    {
        $result = [];
        foreach ($points as $point) {
            $distance = $this->distance2d(
                $state->x,
                $state->z,
                (float) ($point['x'] ?? 0),
                (float) ($point['z'] ?? 0),
            );
            if ($distance > $radius) {
                continue;
            }
            $result[] = array_merge($point, [
                'distance' => round($distance, 2),
            ]);
        }

        usort($result, fn ($a, $b) => ($a['distance'] ?? 0) <=> ($b['distance'] ?? 0));

        return $result;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function tryPortalTransition(Character $character, CharacterWorldState $state): ?array
    {
        $zone = $this->zoneCatalog->get($state->zone_slug);
        $radius = (float) config('game.world_portal_radius', 4.0);

        foreach ($zone['portals'] ?? [] as $portal) {
            $distance = $this->distance2d(
                $state->x,
                $state->z,
                (float) ($portal['x'] ?? 0),
                (float) ($portal['z'] ?? 0),
            );
            if ($distance > $radius) {
                continue;
            }

            $fromZone = $state->zone_slug;
            $spawned = $this->spawnInZone(
                $character,
                (string) $portal['target_zone'],
                (string) ($portal['target_spawn'] ?? 'default'),
                recordEvent: false,
            );

            if ($fromZone !== $spawned->zone_slug) {
                $this->recordEnteredZone($character, $spawned, $spawned->zone_slug, $fromZone);
            }

            return [
                'id' => $portal['id'] ?? null,
                'from_zone' => $fromZone,
                'to_zone' => $spawned->zone_slug,
            ];
        }

        return null;
    }

    private function recordMoved(Character $character, CharacterWorldState $state): void
    {
        $this->eventStore->record(
            'world.moved',
            'world',
            $state->zone_slug,
            [
                'character_uuid' => $character->uuid,
                'character_name' => $character->name,
                'zone_slug' => $state->zone_slug,
                'x' => $state->x,
                'y' => $state->y,
                'z' => $state->z,
                'rotation_y' => $state->rotation_y,
            ],
            $character->uuid,
        );
    }

    private function recordEnteredZone(
        Character $character,
        CharacterWorldState $state,
        string $zoneSlug,
        ?string $previousZone,
    ): void {
        $this->eventStore->record(
            'world.entered_zone',
            'world',
            $zoneSlug,
            [
                'character_uuid' => $character->uuid,
                'character_name' => $character->name,
                'zone_slug' => $zoneSlug,
                'previous_zone' => $previousZone,
                'x' => $state->x,
                'y' => $state->y,
                'z' => $state->z,
                'rotation_y' => $state->rotation_y,
            ],
            $character->uuid,
        );
    }

    /**
     * @param array<string, float|int> $bounds
     */
    private function isInsideBounds(float $x, float $z, array $bounds): bool
    {
        if ($bounds === []) {
            return true;
        }

        $minX = (float) ($bounds['min_x'] ?? -PHP_FLOAT_MAX);
        $maxX = (float) ($bounds['max_x'] ?? PHP_FLOAT_MAX);
        $minZ = (float) ($bounds['min_z'] ?? -PHP_FLOAT_MAX);
        $maxZ = (float) ($bounds['max_z'] ?? PHP_FLOAT_MAX);

        return $x >= $minX && $x <= $maxX && $z >= $minZ && $z <= $maxZ;
    }

    private function assertSpeedAllowed(CharacterWorldState $state, float $x, float $y, float $z): void
    {
        $maxSpeed = (float) config('game.world_max_speed', 15.0);
        $maxStep = (float) config('game.world_max_step', 12.0);

        $dx = $x - $state->x;
        $dy = $y - $state->y;
        $dz = $z - $state->z;
        $distance = sqrt($dx * $dx + $dy * $dy + $dz * $dz);

        if ($distance <= $maxStep) {
            return;
        }

        $seconds = max(0.05, ($state->moved_at ?? now())->diffInMilliseconds(now()) / 1000);
        $allowed = min($maxSpeed * $seconds + 0.5, $maxStep);

        if ($distance > $allowed) {
            throw new \RuntimeException('Слишком быстрое перемещение');
        }
    }

    private function distance2d(float $x1, float $z1, float $x2, float $z2): float
    {
        $dx = $x2 - $x1;
        $dz = $z2 - $z1;

        return sqrt($dx * $dx + $dz * $dz);
    }

    /**
     * @return array{state: array<string, mixed>, portal_used: array<string, mixed>|null}
     */
    private function wrapState(
        Character $character,
        CharacterWorldState $state,
        ?array $portalUsed = null,
    ): array {
        return [
            'state' => $this->formatStateResponse($character, $state),
            'portal_used' => $portalUsed,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatStateResponse(Character $character, CharacterWorldState $state): array
    {
        $zone = $this->zoneCatalog->get($state->zone_slug);

        return [
            'character_uuid' => $character->uuid,
            'zone_slug' => $state->zone_slug,
            'zone_name' => $zone['name'] ?? $state->zone_slug,
            'x' => $state->x,
            'y' => $state->y,
            'z' => $state->z,
            'rotation_y' => $state->rotation_y,
            'moved_at' => $state->moved_at?->toIso8601String(),
        ];
    }
}
