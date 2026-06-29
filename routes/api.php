<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\InventoryController;
use App\Http\Controllers\Api\CraftingController;
use App\Http\Controllers\Api\AuctionController;
use App\Http\Controllers\Api\TradeController;
use App\Http\Controllers\Api\SettingsController;
use App\Http\Controllers\Api\EventsController;
use App\Http\Controllers\Api\EventsStreamController;
use App\Http\Controllers\Api\HeartbeatController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/online', [HeartbeatController::class, 'online']);
    Route::get('/auction/lots', [AuctionController::class, 'activeLots']);

    Route::middleware('character.owner')->group(function () {
        Route::get('/inventory/{characterUuid}', [InventoryController::class, 'index']);

        Route::get('/crafting/{characterUuid}/recipes', [CraftingController::class, 'recipes']);
        Route::post('/crafting/{characterUuid}/craft-resource', [CraftingController::class, 'craftResource']);
        Route::post('/crafting/{characterUuid}/create-blueprint', [CraftingController::class, 'createBlueprint']);
        Route::post('/crafting/{characterUuid}/craft-item', [CraftingController::class, 'craftItem']);
        Route::post('/crafting/{characterUuid}/disassemble', [CraftingController::class, 'disassemble']);

        Route::get('/auction/{characterUuid}/my-lots', [AuctionController::class, 'myLots']);
        Route::post('/auction/{characterUuid}/prepare', [AuctionController::class, 'prepareLot']);
        Route::post('/auction/{characterUuid}/confirm', [AuctionController::class, 'confirmLot']);
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

        Route::get('/settings/{characterUuid}', [SettingsController::class, 'get']);
        Route::post('/settings/{characterUuid}', [SettingsController::class, 'set']);
        Route::post('/settings/{characterUuid}/multiple', [SettingsController::class, 'setMultiple']);
    });
});
