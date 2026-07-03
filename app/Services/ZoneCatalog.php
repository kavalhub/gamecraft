<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\File;

class ZoneCatalog
{
    /** @var array<string, array<string, mixed>>|null */
    private static ?array $bySlug = null;

    /**
     * @return list<array<string, mixed>>
     */
    public function all(): array
    {
        return array_values($this->loadBySlug());
    }

    /**
     * @return array<string, mixed>
     */
    public function get(string $slug): array
    {
        $zone = $this->loadBySlug()[$slug] ?? null;
        if (!$zone) {
            throw new \RuntimeException("Зона не найдена: {$slug}");
        }

        return $zone;
    }

    public function exists(string $slug): bool
    {
        return isset($this->loadBySlug()[$slug]);
    }

    /**
     * @return array{x: float, y: float, z: float, rotation_y: float}
     */
    public function getSpawn(string $zoneSlug, string $spawnId = 'default'): array
    {
        $zone = $this->get($zoneSlug);
        $spawns = $zone['spawns'] ?? [];
        $spawn = $spawns[$spawnId] ?? $spawns['default'] ?? null;

        if (!$spawn) {
            throw new \RuntimeException("Точка спавна не найдена: {$zoneSlug}/{$spawnId}");
        }

        return [
            'x' => (float) ($spawn['x'] ?? 0),
            'y' => (float) ($spawn['y'] ?? 0),
            'z' => (float) ($spawn['z'] ?? 0),
            'rotation_y' => (float) ($spawn['rotation_y'] ?? 0),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findInteractable(string $zoneSlug, string $id): ?array
    {
        $zone = $this->get($zoneSlug);
        foreach ($zone['interactables'] ?? [] as $item) {
            if (($item['id'] ?? null) === $id) {
                return $item;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findPortal(string $zoneSlug, string $id): ?array
    {
        $zone = $this->get($zoneSlug);
        foreach ($zone['portals'] ?? [] as $portal) {
            if (($portal['id'] ?? null) === $id) {
                return $portal;
            }
        }

        return null;
    }

    /**
     * @param array{min_x: float|int, max_x: float|int, min_z: float|int, max_z: float|int} $bounds
     */
    public function updateBounds(string $slug, array $bounds): void
    {
        $minX = (float) $bounds['min_x'];
        $maxX = (float) $bounds['max_x'];
        $minZ = (float) $bounds['min_z'];
        $maxZ = (float) $bounds['max_z'];

        if ($minX >= $maxX || $minZ >= $maxZ) {
            throw new \RuntimeException('Границы зоны некорректны: min должен быть меньше max');
        }

        if (abs($maxX - $minX) > 500 || abs($maxZ - $minZ) > 500) {
            throw new \RuntimeException('Слишком большая зона (максимум 500×500 клеток)');
        }

        $path = base_path('content/zones.json');
        if (!File::exists($path)) {
            throw new \RuntimeException('Файл zones.json не найден');
        }

        $data = json_decode(File::get($path), true);
        $found = false;
        foreach ($data['zones'] ?? [] as &$zone) {
            if (($zone['slug'] ?? '') !== $slug) {
                continue;
            }
            $zone['bounds'] = [
                'min_x' => $minX,
                'max_x' => $maxX,
                'min_z' => $minZ,
                'max_z' => $maxZ,
            ];
            $found = true;
            break;
        }
        unset($zone);

        if (!$found) {
            throw new \RuntimeException("Зона не найдена: {$slug}");
        }

        File::put(
            $path,
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n",
        );

        self::$bySlug = null;
    }

    public function reload(): void
    {
        self::$bySlug = null;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function loadBySlug(): array
    {
        if (self::$bySlug !== null) {
            return self::$bySlug;
        }

        $path = base_path('content/zones.json');
        if (!File::exists($path)) {
            self::$bySlug = [];

            return self::$bySlug;
        }

        $data = json_decode(File::get($path), true);
        $map = [];
        foreach ($data['zones'] ?? [] as $zone) {
            if (!isset($zone['slug'])) {
                continue;
            }
            $map[(string) $zone['slug']] = $zone;
        }

        self::$bySlug = $map;

        return self::$bySlug;
    }
}
