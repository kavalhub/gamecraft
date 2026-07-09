<?php

declare(strict_types=1);

namespace Tests;

use Database\Seeders\DatabaseSeeder;
use Database\Seeders\TestPlayerSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Testing\TestResponse;

abstract class TestCase extends BaseTestCase
{
    protected function seedGameDatabase(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(TestPlayerSeeder::class);
    }

    protected function apiUrl(string $path): string
    {
        $base = rtrim((string) config('game.base_path', '/gamecraft'), '/');

        return $base . (str_starts_with($path, '/') ? $path : '/' . $path);
    }

    protected function prefixApiUri(mixed $uri): mixed
    {
        if (is_string($uri) && str_starts_with($uri, '/api')) {
            return $this->apiUrl($uri);
        }

        return $uri;
    }

    public function getJson($uri, array $headers = [], $options = 0): TestResponse
    {
        return parent::getJson($this->prefixApiUri($uri), $headers, $options);
    }

    public function postJson($uri, array $data = [], array $headers = [], $options = 0): TestResponse
    {
        return parent::postJson($this->prefixApiUri($uri), $data, $headers, $options);
    }

    public function putJson($uri, array $data = [], array $headers = [], $options = 0): TestResponse
    {
        return parent::putJson($this->prefixApiUri($uri), $data, $headers, $options);
    }

    public function patchJson($uri, array $data = [], array $headers = [], $options = 0): TestResponse
    {
        return parent::patchJson($this->prefixApiUri($uri), $data, $headers, $options);
    }

    public function deleteJson($uri, array $data = [], array $headers = [], $options = 0): TestResponse
    {
        return parent::deleteJson($this->prefixApiUri($uri), $data, $headers, $options);
    }
}
