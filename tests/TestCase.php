<?php

declare(strict_types=1);

namespace Tests;

use Database\Seeders\DatabaseSeeder;
use Database\Seeders\TestPlayerSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function seedGameDatabase(): void
    {
        $this->seedGameDatabase();
        $this->seed(TestPlayerSeeder::class);
    }
}
