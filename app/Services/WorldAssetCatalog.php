<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\File;

class WorldAssetCatalog
{
    /** @var list<string> */
    private const IMAGE_EXTENSIONS = ['png', 'jpg', 'jpeg', 'gif', 'webp'];

    /** @var list<string> */
    private const SKIP_FILENAMES = ['preview.png', 'sample.png', 'information.png'];

    /**
     * @return array{sprites: list<array{path: string, url: string, name: string, folder: string}>, folders: list<string>}
     */
    public function listSprites(): array
    {
        $root = public_path('assets');
        if (!File::isDirectory($root)) {
            return ['sprites' => [], 'folders' => []];
        }

        $sprites = [];
        $this->scanDirectory($root, '', $sprites);

        usort($sprites, fn ($a, $b) => strcmp($a['path'], $b['path']));

        $folders = [];
        foreach ($sprites as $sprite) {
            $folder = $sprite['folder'];
            if ($folder !== '' && !in_array($folder, $folders, true)) {
                $folders[] = $folder;
            }
        }
        sort($folders);

        return ['sprites' => $sprites, 'folders' => $folders];
    }

    /**
     * @param list<array{path: string, url: string, name: string, folder: string}> $sprites
     */
    private function scanDirectory(string $absoluteDir, string $relativePrefix, array &$sprites): void
    {
        if (!is_readable($absoluteDir)) {
            return;
        }

        $entries = @scandir($absoluteDir);
        if ($entries === false) {
            return;
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $absolutePath = $absoluteDir . DIRECTORY_SEPARATOR . $entry;
            $relativePath = $relativePrefix === '' ? $entry : $relativePrefix . '/' . $entry;

            if (is_dir($absolutePath)) {
                $this->scanDirectory($absolutePath, $relativePath, $sprites);
                continue;
            }

            if (!is_file($absolutePath) || !is_readable($absolutePath)) {
                continue;
            }

            $ext = strtolower(pathinfo($entry, PATHINFO_EXTENSION));
            if (!in_array($ext, self::IMAGE_EXTENSIONS, true)) {
                continue;
            }

            if (in_array(strtolower($entry), self::SKIP_FILENAMES, true)) {
                continue;
            }

            $folder = str_contains($relativePath, '/')
                ? substr($relativePath, 0, (int) strrpos($relativePath, '/'))
                : '';

            $sprites[] = [
                'path' => $relativePath,
                'url' => '/assets/' . $relativePath,
                'name' => $entry,
                'folder' => $folder,
            ];
        }
    }
}
