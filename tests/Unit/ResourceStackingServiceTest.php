<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\ResourceStackingService;
use PHPUnit\Framework\TestCase;

class ResourceStackingServiceTest extends TestCase
{
    private ResourceStackingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ResourceStackingService();
    }

    public function test_split_unlimited_stack_returns_single_chunk(): void
    {
        $this->assertEquals([600], $this->service->split(600, null));
    }

    public function test_split_even_division(): void
    {
        $this->assertEquals([200, 200, 200], $this->service->split(600, 200));
    }

    public function test_split_with_remainder(): void
    {
        $this->assertEquals([200, 50], $this->service->split(250, 200));
    }

    public function test_split_single_unit(): void
    {
        $this->assertEquals([1], $this->service->split(1, 200));
    }

    public function test_split_exactly_max_stack(): void
    {
        $this->assertEquals([200], $this->service->split(200, 200));
    }

    public function test_split_zero_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->service->split(0, 200);
    }

    public function test_split_negative_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->service->split(-5, 200);
    }

    public function test_slot_count_for_unlimited(): void
    {
        $this->assertEquals(1, $this->service->slotCount(600, null));
    }

    public function test_slot_count_for_limited(): void
    {
        $this->assertEquals(3, $this->service->slotCount(600, 200));
    }
}
