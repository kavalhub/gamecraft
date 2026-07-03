<?php
declare(strict_types=1);
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CharacterSetting;
use App\Services\CharacterSettingsDefaultsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function __construct(
        private CharacterSettingsDefaultsService $settingsDefaults,
    ) {}

    public function get(string $characterUuid): JsonResponse
    {
        $settings = CharacterSetting::where('character_uuid', $characterUuid)->get();
        
        $result = [];
        foreach ($settings as $setting) {
            $result[$setting->key] = $setting->value;
        }

        $result = $this->settingsDefaults->mergeSettings($result);

        return response()->json(['settings' => $result]);
    }

    public function set(Request $request, string $characterUuid): JsonResponse
    {
        $request->validate([
            'key' => 'required|string',
            'value' => 'required|array',
        ]);

        CharacterSetting::set($characterUuid, $request->key, $request->value);

        return response()->json(['success' => true]);
    }

    public function setMultiple(Request $request, string $characterUuid): JsonResponse
    {
        $request->validate([
            'settings' => 'required|array',
        ]);

        foreach ($request->settings as $key => $value) {
            CharacterSetting::set($characterUuid, $key, $value);
        }

        return response()->json(['success' => true]);
    }
}
