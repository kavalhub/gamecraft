<?php

declare(strict_types=1);

use App\Http\Controllers\Api\AuctionController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CraftingController;
use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\Api\InventoryController;
use App\Http\Controllers\Api\PlayerController;
use App\Http\Controllers\Api\TradeController;
use Illuminate\Support\Facades\Route;

// Аутентификация
Route::post('/register', [AuthController::class, 'register']);
Route::get('/login', [AuthController::class, 'login']);

// Инвентарь (API — используется в layout)
Route::get('/inventory', [InventoryController::class, 'index']);

// Крафт
Route::get('/recipes', [CraftingController::class, 'recipes']);
Route::post('/craft', [CraftingController::class, 'craft']);
Route::post('/disassemble', [CraftingController::class, 'disassemble']);

// Аукцион
Route::get('/auction', [AuctionController::class, 'index']);
Route::get('/auction/my', [AuctionController::class, 'my']);
Route::post('/auction', [AuctionController::class, 'store']);
Route::post('/auction/{id}/buy', [AuctionController::class, 'buy']);
Route::post('/auction/{id}/cancel', [AuctionController::class, 'cancel']);

// События
Route::get('/events/latest', [EventController::class, 'latest']);
Route::get('/events/user', [EventController::class, 'userHistory']);
Route::get('/events/operation/{correlationId}', [EventController::class, 'operationDetails']);

// Обмен
Route::get('/trade/active', [TradeController::class, 'active']);
Route::get('/trade/{id}', [TradeController::class, 'show']);
Route::post('/trade', [TradeController::class, 'store']);
Route::post('/trade/{id}/item', [TradeController::class, 'addItem']);
Route::delete('/trade/{id}/item/{itemId}', [TradeController::class, 'removeItem']);
Route::post('/trade/{id}/gold', [TradeController::class, 'addGold']);
Route::post('/trade/{id}/accept', [TradeController::class, 'accept']);
Route::post('/trade/{id}/cancel', [TradeController::class, 'cancel']);

// Игроки
Route::post('/heartbeat', [PlayerController::class, 'heartbeat']);
Route::get('/players/online', [PlayerController::class, 'online']);
