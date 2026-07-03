<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\ZoneTileCatalog;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ZoneTileCatalogTest extends TestCase
{
    private string $testSlug = 'test_zone_tiles';

    protected function tearDown(): void
    {
        $path = base_path('content/zone_tiles/' . $this->testSlug . '.json');
        if (File::exists($path)) {
            File::delete($path);
        }
        app(ZoneTileCatalog::class)->forget($this->testSlug);
        parent::tearDown();
    }

    public function test_save_and_load_cells(): void
    {
        $catalog = app(ZoneTileCatalog::class);
        $catalog->save($this->testSlug, [
            'cells' => [
                '1,2' => ['ground' => 'world/grass.png', 'overlay' => 'world/fence.png', 'walkable' => true],
                '3,4' => ['walkable' => false],
            ],
        ]);

        $data = $catalog->get($this->testSlug);
        $this->assertSame('world/grass.png', $data['cells']['1,2']['ground']);
        $this->assertSame('world/fence.png', $data['cells']['1,2']['overlay']);
        $this->assertTrue($data['cells']['1,2']['walkable']);
        $this->assertFalse($data['cells']['3,4']['walkable']);
    }

    public function test_save_migrates_legacy_sprite_to_ground(): void
    {
        $catalog = app(ZoneTileCatalog::class);
        $catalog->save($this->testSlug, [
            'cells' => [
                '0,0' => ['sprite' => 'world/dirt.png'],
            ],
        ]);

        $data = $catalog->get($this->testSlug);
        $this->assertSame('world/dirt.png', $data['cells']['0,0']['ground']);
    }

    public function test_save_empty_cells_clears_zone(): void
    {
        $catalog = app(ZoneTileCatalog::class);
        $catalog->save($this->testSlug, [
            'cells' => ['1,1' => ['ground' => 'world/a.png']],
        ]);
        $catalog->save($this->testSlug, ['cells' => []]);

        $data = $catalog->get($this->testSlug);
        $this->assertSame([], $data['cells']);
    }

    public function test_is_walkable_defaults_to_true(): void
    {
        $catalog = app(ZoneTileCatalog::class);
        $this->assertTrue($catalog->isWalkable($this->testSlug, 0.5, 0.5));
    }

    public function test_is_walkable_respects_blocked_cell(): void
    {
        $catalog = app(ZoneTileCatalog::class);
        $catalog->save($this->testSlug, [
            'cells' => [
                '0,0' => ['walkable' => false],
            ],
        ]);

        $this->assertFalse($catalog->isWalkable($this->testSlug, 0.2, 0.8));
        $this->assertTrue($catalog->isWalkable($this->testSlug, 1.2, 1.2));
    }

    public function test_world_to_cell_uses_diamond_grid(): void
    {
        $catalog = app(ZoneTileCatalog::class);
        $cell = $catalog->worldToCell(0.6, 0.0);
        $this->assertSame(1, $cell['x']);
        $this->assertSame(0, $cell['z']);
    }
}
