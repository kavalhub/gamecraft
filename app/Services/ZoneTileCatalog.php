<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\File;

class ZoneTileCatalog
{
    /** @var array<string, array<string, mixed>> */
    private static array $cache = [];

    /**
     * @return array{version: string, cells: array<string, array<string, mixed>>}
     */
    public function get(string $zoneSlug): array
    {
        if (isset(self::$cache[$zoneSlug])) {
            return self::$cache[$zoneSlug];
        }

        $path = $this->pathFor($zoneSlug);
        if (!File::exists($path)) {
            $empty = ['version' => '1.0.0', 'cells' => []];
            self::$cache[$zoneSlug] = $empty;

            return $empty;
        }

        $data = json_decode(File::get($path), true);
        $normalized = [
            'version' => (string) ($data['version'] ?? '1.0.0'),
            'cells' => is_array($data['cells'] ?? null) ? $data['cells'] : [],
        ];
        self::$cache[$zoneSlug] = $normalized;

        return $normalized;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getCell(string $zoneSlug, int $x, int $z): ?array
    {
        $cells = $this->get($zoneSlug)['cells'];

        return $cells[$this->cellKey($x, $z)] ?? null;
    }

    public function isWalkable(string $zoneSlug, float $x, float $z): bool
    {
        foreach ($this->footprintSamples($x, $z) as [$sx, $sz]) {
            if (!$this->isSampleWalkable($zoneSlug, $sx, $sz)) {
                return false;
            }
        }

        return true;
    }

    private function isSampleWalkable(string $zoneSlug, float $x, float $z): bool
    {
        $cell = $this->worldToCell($x, $z);
        $data = $this->getCell($zoneSlug, $cell['x'], $cell['z']);

        if ($data === null) {
            return true;
        }

        return ($data['walkable'] ?? true) !== false;
    }

    /**
     * @return array{x: int, z: int}
     */
    public function worldToCell(float $px, float $pz): array
    {
        $u = $px - $pz;
        $v = $px + $pz;
        $cx = (int) round(($v + $u) / 2);
        $cz = (int) round(($v - $u) / 2);

        if ($this->pointInCell($px, $pz, $cx, $cz)) {
            return ['x' => $cx, 'z' => $cz];
        }

        foreach ([[1, 0], [-1, 0], [0, 1], [0, -1], [1, 1], [-1, -1], [1, -1], [-1, 1]] as [$dx, $dz]) {
            $tx = $cx + $dx;
            $tz = $cz + $dz;
            if ($this->pointInCell($px, $pz, $tx, $tz)) {
                return ['x' => $tx, 'z' => $tz];
            }
        }

        return ['x' => $cx, 'z' => $cz];
    }

    private function pointInCell(float $px, float $pz, int $cx, int $cz, float $margin = 1.0): bool
    {
        $u = $px - $pz;
        $v = $px + $pz;
        $u0 = $cx - $cz;
        $v0 = $cx + $cz;

        return abs($u - $u0) <= $margin && abs($v - $v0) <= $margin;
    }

    /**
     * @return list<array{0: float, 1: float}>
     */
    private function footprintSamples(float $px, float $pz, float $radius = 0.42): array
    {
        $d = $radius * 0.7;

        return [
            [$px, $pz],
            [$px + $radius, $pz],
            [$px - $radius, $pz],
            [$px, $pz + $radius],
            [$px, $pz - $radius],
            [$px + $d, $pz + $d],
            [$px - $d, $pz + $d],
            [$px + $d, $pz - $d],
            [$px - $d, $pz - $d],
        ];
    }

    /**
     * @param array{version?: string, cells: array<string, array<string, mixed>>} $data
     */
    public function save(string $zoneSlug, array $data): void
    {
        $cells = [];
        foreach ($data['cells'] ?? [] as $key => $cell) {
            if (!is_string($key) || !is_array($cell)) {
                continue;
            }
            if (!preg_match('/^-?\d+,-?\d+$/', $key)) {
                continue;
            }

            $entry = [];
            $ground = $cell['ground'] ?? $cell['sprite'] ?? null;
            if (is_string($ground) && $ground !== '') {
                $entry['ground'] = $this->normalizeSpritePath($ground);
            }
            if (!empty($cell['overlay']) && is_string($cell['overlay'])) {
                $entry['overlay'] = $this->normalizeSpritePath($cell['overlay']);
            }
            if (array_key_exists('walkable', $cell)) {
                $entry['walkable'] = (bool) $cell['walkable'];
            }

            if ($entry === [] || (count($entry) === 1 && array_key_exists('walkable', $entry) && $entry['walkable'] === true)) {
                continue;
            }

            $cells[$key] = $entry;
        }

        $payload = [
            'version' => '1.0.0',
            'cells' => $cells,
        ];

        $dir = base_path('content/zone_tiles');
        if (!File::isDirectory($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        File::put(
            $this->pathFor($zoneSlug),
            json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n",
        );

        self::$cache[$zoneSlug] = $payload;
    }

    public function forget(string $zoneSlug): void
    {
        unset(self::$cache[$zoneSlug]);
    }

    public function cellKey(int $x, int $z): string
    {
        return $x . ',' . $z;
    }

    private function pathFor(string $zoneSlug): string
    {
        $safe = preg_replace('/[^a-z0-9_-]/', '', $zoneSlug) ?? $zoneSlug;

        return base_path('content/zone_tiles/' . $safe . '.json');
    }

    private function normalizeSpritePath(string $path): string
    {
        $path = ltrim(str_replace('\\', '/', $path), '/');
        if (str_starts_with($path, 'assets/')) {
            $path = substr($path, 7);
        }

        return $path;
    }
}
