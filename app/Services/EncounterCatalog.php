<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\File;

class EncounterCatalog
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
        $encounter = $this->loadBySlug()[$slug] ?? null;
        if (!$encounter) {
            throw new \RuntimeException("Столкновение не найдено: {$slug}");
        }

        return $encounter;
    }

    public function exists(string $slug): bool
    {
        return isset($this->loadBySlug()[$slug]);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function loadBySlug(): array
    {
        if (self::$bySlug !== null) {
            return self::$bySlug;
        }

        $path = base_path('content/encounters.json');
        if (!File::exists($path)) {
            self::$bySlug = [];

            return self::$bySlug;
        }

        $data = json_decode(File::get($path), true);
        $map = [];
        foreach ($data['encounters'] ?? [] as $encounter) {
            if (!isset($encounter['slug'])) {
                continue;
            }
            $map[(string) $encounter['slug']] = $encounter;
        }

        self::$bySlug = $map;

        return self::$bySlug;
    }
}
