<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\ZoneStampCatalog;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ZoneStampCatalogTest extends TestCase
{
    private string $testId = 'test_stamp_house';

    protected function tearDown(): void
    {
        $path = base_path('content/zone_stamps/' . $this->testId . '.json');
        if (File::exists($path)) {
            File::delete($path);
        }
        parent::tearDown();
    }

    public function test_save_and_load_stamp_with_relative_cells(): void
    {
        $catalog = new ZoneStampCatalog();
        $catalog->save($this->testId, [
            'name' => 'Test House',
            'cells' => [
                '0,0' => ['ground' => 'world/dirt_E.png', 'walkable' => true],
                '1,0' => ['overlay' => 'world/fence.png', 'walkable' => false],
            ],
        ]);

        $data = $catalog->get($this->testId);
        $this->assertSame('Test House', $data['name']);
        $this->assertSame('world/dirt_E.png', $data['cells']['0,0']['ground']);
        $this->assertFalse($data['cells']['1,0']['walkable']);
    }

    public function test_list_includes_saved_stamp(): void
    {
        $catalog = new ZoneStampCatalog();
        $catalog->save($this->testId, [
            'name' => 'Listed House',
            'cells' => ['0,0' => ['ground' => 'world/a.png']],
        ]);

        $found = collect($catalog->list())->firstWhere('id', $this->testId);
        $this->assertNotNull($found);
        $this->assertSame('Listed House', $found['name']);
        $this->assertSame(1, $found['cell_count']);
    }

    public function test_delete_removes_stamp(): void
    {
        $catalog = new ZoneStampCatalog();
        $catalog->save($this->testId, [
            'name' => 'Temp',
            'cells' => ['0,0' => ['ground' => 'world/a.png']],
        ]);
        $catalog->delete($this->testId);
        $this->assertFalse($catalog->exists($this->testId));
    }
}
