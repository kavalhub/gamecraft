<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Character;
use App\Models\Item;
use App\Models\Resources;
use App\Models\Slot;
use App\Models\Storage;
use App\Models\TemporarySlot;
use App\Services\SlotCellResolver;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SlotCellResolverTest extends TestCase
{
    use RefreshDatabase;

    private SlotCellResolver $resolver;
    private Character $player;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->resolver = app(SlotCellResolver::class);
        $user = \App\Models\User::where('email', 'test@example.com')->first();
        $this->player = $user->characters()->where('character_type', 'player')->first();
    }

    public function test_resolves_regular_and_temporary_cells(): void
    {
        $inventory = $this->player->storages()->where('storage_type', 'inventory')->firstOrFail();
        $slot = $inventory->slots()->whereNull('slot_type')->firstOrFail();

        $resolved = $this->resolver->resolve($slot->uuid);
        $this->assertSame('regular', $resolved['kind']);

        $temp = TemporarySlot::create([
            'uuid' => Str::uuid()->toString(),
            'storage_uuid' => $inventory->uuid,
            'character_uuid' => $this->player->uuid,
            'slot_index' => 99,
            'active' => true,
        ]);

        $resolvedTemp = $this->resolver->resolve($temp->uuid);
        $this->assertSame('temporary', $resolvedTemp['kind']);
    }

    public function test_finds_buffered_and_canonical_occupants_on_temporary_slot(): void
    {
        $inventory = $this->player->storages()->where('storage_type', 'inventory')->firstOrFail();
        $homeSlot = $inventory->slots()->whereNull('slot_type')->firstOrFail();

        $temp = TemporarySlot::create([
            'uuid' => Str::uuid()->toString(),
            'storage_uuid' => $inventory->uuid,
            'character_uuid' => $this->player->uuid,
            'slot_index' => 100,
            'active' => true,
        ]);

        $buffered = Resources::create([
            'uuid' => Str::uuid()->toString(),
            'slot_uuid' => $homeSlot->uuid,
            'buffer_slot_uuid' => $temp->uuid,
            'recipe_slug' => 'wood',
            'template_slug' => 'wood',
            'slot_type' => 'wood',
            'max_stack' => 99,
            'quantity' => 3,
        ]);

        $found = $this->resolver->getOccupantForTemporarySlot($temp);
        $this->assertNotNull($found);
        $this->assertSame($buffered->uuid, $found->uuid);

        $buffered->delete();

        $canonical = Resources::create([
            'uuid' => Str::uuid()->toString(),
            'slot_uuid' => $temp->uuid,
            'buffer_slot_uuid' => null,
            'recipe_slug' => 'gold',
            'template_slug' => 'gold',
            'slot_type' => 'gold',
            'max_stack' => null,
            'quantity' => 5,
        ]);

        $foundCanonical = $this->resolver->getOccupantForTemporarySlot($temp);
        $this->assertNotNull($foundCanonical);
        $this->assertSame($canonical->uuid, $foundCanonical->uuid);
    }

    public function test_regular_slot_ignores_buffered_occupant(): void
    {
        $inventory = $this->player->storages()->where('storage_type', 'inventory')->firstOrFail();
        $homeSlot = $inventory->slots()->whereNull('slot_type')->firstOrFail();

        $temp = TemporarySlot::create([
            'uuid' => Str::uuid()->toString(),
            'storage_uuid' => $inventory->uuid,
            'character_uuid' => $this->player->uuid,
            'slot_index' => 101,
            'active' => true,
        ]);

        Resources::create([
            'uuid' => Str::uuid()->toString(),
            'slot_uuid' => $homeSlot->uuid,
            'buffer_slot_uuid' => $temp->uuid,
            'recipe_slug' => 'wood',
            'template_slug' => 'wood',
            'slot_type' => 'wood',
            'max_stack' => 99,
            'quantity' => 1,
        ]);

        $this->assertNull($this->resolver->getOccupantForRegularSlot($homeSlot));
        $this->assertTrue($this->resolver->hasBufferedOccupantOnRegularSlot($homeSlot));
    }
}
