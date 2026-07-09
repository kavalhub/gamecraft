<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\File;

class ZoneStampCatalog
{
    /** @var array<string, array<string, mixed>> */
    private static array $cache = [];

    /**
     * @return list<array{id: string, name: string, cell_count: int, width: int, height: int}>
     */
    public function list(): array
    {
        $dir = $this->directory();
        if (!File::isDirectory($dir)) {
            return [];
        }

        $stamps = [];
        foreach (File::files($dir) as $file) {
            if ($file->getExtension() !== 'json') {
                continue;
            }
            $id = $file->getFilenameWithoutExtension();
            $data = $this->get($id);
            $meta = $this->metaFromCells($data['cells']);
            $stamps[] = [
                'id' => $id,
                'name' => $data['name'],
                'cell_count' => $meta['cell_count'],
                'width' => $meta['width'],
                'height' => $meta['height'],
            ];
        }

        usort($stamps, fn ($a, $b) => strcmp($a['name'], $b['name']));

        return $stamps;
    }

    /**
     * @return array{version: string, name: string, cells: array<string, array<string, mixed>>}
     */
    public function get(string $stampId): array
    {
        if (isset(self::$cache[$stampId])) {
            return self::$cache[$stampId];
        }

        $path = $this->pathFor($stampId);
        if (!File::exists($path)) {
            throw new \RuntimeException('Штамп не найден');
        }

        $data = json_decode(File::get($path), true);
        $normalized = [
            'version' => (string) ($data['version'] ?? '1.0.0'),
            'name' => (string) ($data['name'] ?? $stampId),
            'cells' => is_array($data['cells'] ?? null) ? $data['cells'] : [],
        ];
        self::$cache[$stampId] = $normalized;

        return $normalized;
    }

    /**
     * @param array{name: string, cells: array<string, array<string, mixed>>} $data
     */
    public function save(string $stampId, array $data): void
    {
        $cells = $this->normalizeCells($data['cells'] ?? []);
        $payload = [
            'version' => '1.0.0',
            'name' => trim((string) ($data['name'] ?? $stampId)),
            'cells' => $cells,
        ];

        $dir = $this->directory();
        if (!File::isDirectory($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        File::put(
            $this->pathFor($stampId),
            json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n",
        );

        self::$cache[$stampId] = $payload;
    }

    public function delete(string $stampId): void
    {
        $path = $this->pathFor($stampId);
        if (File::exists($path)) {
            File::delete($path);
        }
        unset(self::$cache[$stampId]);
    }

    public function exists(string $stampId): bool
    {
        return File::exists($this->pathFor($stampId));
    }

    /**
     * @param array<string, array<string, mixed>> $cells
     * @return array{cell_count: int, width: int, height: int}
     */
    public function metaFromCells(array $cells): array
    {
        if ($cells === []) {
            return ['cell_count' => 0, 'width' => 0, 'height' => 0];
        }

        $minX = $maxX = $minZ = $maxZ = 0;
        $first = true;
        foreach (array_keys($cells) as $key) {
            if (!preg_match('/^-?\d+,-?\d+$/', $key)) {
                continue;
            }
            [$x, $z] = array_map('intval', explode(',', $key, 2));
            if ($first) {
                $minX = $maxX = $x;
                $minZ = $maxZ = $z;
                $first = false;
                continue;
            }
            $minX = min($minX, $x);
            $maxX = max($maxX, $x);
            $minZ = min($minZ, $z);
            $maxZ = max($maxZ, $z);
        }

        return [
            'cell_count' => count($cells),
            'width' => $maxX - $minX + 1,
            'height' => $maxZ - $minZ + 1,
        ];
    }

    /**
     * @param array<string, array<string, mixed>> $cells
     * @return array<string, array<string, mixed>>
     */
    private function normalizeCells(array $cells): array
    {
        $normalized = [];
        foreach ($cells as $key => $cell) {
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

            $normalized[$key] = $entry;
        }

        return $normalized;
    }

    private function directory(): string
    {
        return base_path('content/zone_stamps');
    }

    private function pathFor(string $stampId): string
    {
        $safe = preg_replace('/[^a-z0-9_-]/', '', $stampId) ?? $stampId;

        return $this->directory() . '/' . $safe . '.json';
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
