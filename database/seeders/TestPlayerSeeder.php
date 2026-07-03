<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Character;
use App\Models\User;
use App\Models\Storage;
use App\Services\CurrencyService;
use App\Services\StorageProvisioningService;
use Illuminate\Database\Seeder;

/** Только для PHPUnit — не вызывается из DatabaseSeeder в prod. */
class TestPlayerSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::firstOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'password' => bcrypt('password'),
            ]
        );

        $character = Character::firstOrCreate(
            ['user_uuid' => $user->uuid, 'character_type' => 'player', 'name' => 'Test Character'],
            ['active' => true]
        );

        $provisioning = app(StorageProvisioningService::class);

        $inventory = Storage::firstOrCreate(
            ['characters_uuid' => $character->uuid, 'storage_type' => 'inventory'],
            ['name' => 'Инвентарь', 'active' => true]
        );

        $equipment = Storage::firstOrCreate(
            ['characters_uuid' => $character->uuid, 'storage_type' => 'equipment'],
            ['name' => 'Экипировка', 'active' => true]
        );

        $bank = Storage::firstOrCreate(
            ['characters_uuid' => $character->uuid, 'storage_type' => 'bank'],
            ['name' => 'Банк', 'active' => true]
        );

        if ($inventory->slots()->count() === 0) {
            $provisioning->provisionStorageSlots($inventory);
        }

        if ($equipment->slots()->count() === 0) {
            $provisioning->provisionStorageSlots($equipment);
        }

        if ($bank->slots()->count() === 0) {
            $provisioning->provisionStorageSlots($bank);
        }

        app(CurrencyService::class)->grantStartingGold($character);
    }
}
