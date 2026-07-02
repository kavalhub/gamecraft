<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\InventoryController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\CraftingController;
use App\Http\Controllers\Api\EncounterController;
use App\Http\Controllers\Api\DuelController;
use App\Http\Controllers\Api\AuctionController;
use App\Http\Controllers\Api\TradeController;
use App\Http\Controllers\Api\SettingsController;
use App\Http\Controllers\Api\EventsController;
use App\Http\Controllers\Api\EventsStreamController;
use App\Http\Controllers\Api\HeartbeatController;
use App\Http\Controllers\Api\QuestController;
use App\Http\Controllers\Api\StorageController;
use App\Http\Controllers\Api\ItemTemplateController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/online', [HeartbeatController::class, 'online']);
    Route::get('/auction/lots', [AuctionController::class, 'activeLots']);
    Route::get('/templates', [ItemTemplateController::class, 'index']);

    Route::middleware('character.owner')->group(function () {
        Route::get('/inventory/{characterUuid}', [InventoryController::class, 'index']);
        Route::get('/storage/{characterUuid}', [StorageController::class, 'show']);
        Route::post('/storage/{characterUuid}/move', [StorageController::class, 'move']);
        Route::post('/storage/{characterUuid}/quick-move', [StorageController::class, 'quickMove']);
        Route::post('/storage/{characterUuid}/clear-craft-station', [StorageController::class, 'clearCraftStation']);
        Route::post('/storage/{characterUuid}/clear-disassemble-station', [StorageController::class, 'clearDisassembleStation']);
        Route::post('/storage/{characterUuid}/clear-quest', [StorageController::class, 'clearQuest']);
        Route::post('/storage/{characterUuid}/drop-to-world', [StorageController::class, 'dropToWorld']);

        Route::get('/crafting/{characterUuid}/recipes', [CraftingController::class, 'recipes']);
        Route::post('/crafting/{characterUuid}/craft-resource', [CraftingController::class, 'craftResource']);
        Route::post('/crafting/{characterUuid}/create-blueprint', [CraftingController::class, 'createBlueprint']);
        Route::post('/crafting/{characterUuid}/craft-item', [CraftingController::class, 'craftItem']);
        Route::post('/crafting/{characterUuid}/disassemble', [CraftingController::class, 'disassemble']);

        Route::get('/encounter/{characterUuid}/catalog', [EncounterController::class, 'catalog']);
        Route::post('/encounter/{characterUuid}/resolve', [EncounterController::class, 'resolve']);
        Route::post('/encounter/{characterUuid}/claim', [EncounterController::class, 'claim']);
        Route::post('/encounter/{characterUuid}/refuse', [EncounterController::class, 'refuse']);

        Route::get('/duel/{characterUuid}/current', [DuelController::class, 'current']);
        Route::post('/duel/{characterUuid}/challenge', [DuelController::class, 'challenge']);
        Route::post('/duel/{characterUuid}/accept', [DuelController::class, 'accept']);
        Route::post('/duel/{characterUuid}/decline', [DuelController::class, 'decline']);

        Route::get('/quests/{characterUuid}', [QuestController::class, 'index']);
        Route::get('/quests/{characterUuid}/{questSlug}', [QuestController::class, 'show']);
        Route::post('/quests/{characterUuid}/accept', [QuestController::class, 'accept']);
        Route::post('/quests/{characterUuid}/turn-in', [QuestController::class, 'turnIn']);

        Route::get('/auction/{characterUuid}/my-lots', [AuctionController::class, 'myLots']);
        Route::get('/auction/{characterUuid}/lot/{lotUuid}/buy-info', [AuctionController::class, 'buyInfo']);
        Route::post('/auction/{characterUuid}/prepare', [AuctionController::class, 'prepareLot']);
        Route::post('/auction/{characterUuid}/confirm', [AuctionController::class, 'confirmLot']);
        Route::post('/auction/{characterUuid}/list', [AuctionController::class, 'listLot']);
        Route::post('/auction/{characterUuid}/buy', [AuctionController::class, 'buyLot']);
        Route::post('/auction/{characterUuid}/cancel', [AuctionController::class, 'cancelLot']);

        Route::get('/trade/{characterUuid}/current', [TradeController::class, 'getCurrentTrade']);
        Route::get('/trade/{characterUuid}', [TradeController::class, 'index']);
        Route::post('/trade/{characterUuid}/create', [TradeController::class, 'create']);
        Route::post('/trade/{characterUuid}/add-item', [TradeController::class, 'addItem']);
        Route::post('/trade/{characterUuid}/add-resource', [TradeController::class, 'addResource']);
        Route::post('/trade/{characterUuid}/confirm', [TradeController::class, 'confirm']);
        Route::post('/trade/{characterUuid}/cancel', [TradeController::class, 'cancel']);

        Route::post('/heartbeat/{characterUuid}', [HeartbeatController::class, 'ping']);

        Route::get('/events/{characterUuid}/stream', [EventsStreamController::class, 'stream']);
        Route::get('/events/{characterUuid}/latest', [EventsController::class, 'latest']);

        Route::get('/chat/{characterUuid}/messages', [ChatController::class, 'messages']);
        Route::post('/chat/{characterUuid}/send', [ChatController::class, 'send']);

        Route::get('/play-panel/{characterUuid}', [PlayPanelController::class, 'show']);

        Route::get('/settings/{characterUuid}', [SettingsController::class, 'get']);
        Route::post('/settings/{characterUuid}', [SettingsController::class, 'set']);
        Route::post('/settings/{characterUuid}/multiple', [SettingsController::class, 'setMultiple']);
    });
});
