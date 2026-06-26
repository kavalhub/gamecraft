<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Character;
use App\Services\TradeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TradeController extends Controller
{
    public function __construct(
        private TradeService $tradeService
    ) {}

    public function index(string $characterUuid): JsonResponse
    {
        $character = Character::where('uuid', $characterUuid)->firstOrFail();
        $trades = $this->tradeService->getCharacterTrades($character);

        return response()->json([
            'trades' => $trades->map(fn($trade) => [
                'uuid' => $trade->uuid,
                'initiator' => [
                    'uuid' => $trade->initiator->uuid,
                    'name' => $trade->initiator->name,
                ],
                'partner' => [
                    'uuid' => $trade->partner->uuid,
                    'name' => $trade->partner->name,
                ],
                'status' => $trade->status,
                'initiator_accepted' => $trade->initiator_accepted,
                'partner_accepted' => $trade->partner_accepted,
                'items' => $trade->items->map(fn($item) => [
                    'character_uuid' => $item->character_uuid,
                    'item_uuid' => $item->item_uuid,
                    'resource_uuid' => $item->resource_uuid,
                    'quantity' => $item->quantity,
                ]),
            ]),
        ]);
    }

    public function create(Request $request, string $characterUuid): JsonResponse
    {
        $request->validate([
            'partner_uuid' => 'required|string',
        ]);

        $initiator = Character::where('uuid', $characterUuid)->firstOrFail();
        $partner = Character::where('uuid', $request->partner_uuid)->firstOrFail();

        try {
            $trade = $this->tradeService->createTrade($initiator, $partner);

            return response()->json([
                'success' => true,
                'trade' => [
                    'uuid' => $trade->uuid,
                    'status' => $trade->status,
                ],
            ], 201);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function addItem(Request $request, string $characterUuid): JsonResponse
    {
        $request->validate([
            'trade_uuid' => 'required|string',
            'item_uuid' => 'required|string',
        ]);

        $character = Character::where('uuid', $characterUuid)->firstOrFail();
        $trade = \App\Models\TradeOffer::where('uuid', $request->trade_uuid)->firstOrFail();

        try {
            $tradeItem = $this->tradeService->addItemToTrade(
                $character,
                $trade,
                $request->item_uuid
            );

            return response()->json([
                'success' => true,
                'trade_item_uuid' => $tradeItem->uuid,
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function addResource(Request $request, string $characterUuid): JsonResponse
    {
        $request->validate([
            'trade_uuid' => 'required|string',
            'template_slug' => 'required|string',
            'quantity' => 'required|integer|min:1',
        ]);

        $character = Character::where('uuid', $characterUuid)->firstOrFail();
        $trade = \App\Models\TradeOffer::where('uuid', $request->trade_uuid)->firstOrFail();

        try {
            $tradeItem = $this->tradeService->addResourceToTrade(
                $character,
                $trade,
                $request->template_slug,
                $request->quantity
            );

            return response()->json([
                'success' => true,
                'trade_item_uuid' => $tradeItem->uuid,
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function confirm(Request $request, string $characterUuid): JsonResponse
    {
        $request->validate([
            'trade_uuid' => 'required|string',
        ]);

        $character = Character::where('uuid', $characterUuid)->firstOrFail();
        $trade = \App\Models\TradeOffer::where('uuid', $request->trade_uuid)->firstOrFail();

        try {
            $trade = $this->tradeService->confirmTrade($character, $trade);

            return response()->json([
                'success' => true,
                'trade' => [
                    'uuid' => $trade->uuid,
                    'status' => $trade->status,
                    'initiator_accepted' => $trade->initiator_accepted,
                    'partner_accepted' => $trade->partner_accepted,
                ],
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function cancel(Request $request, string $characterUuid): JsonResponse
    {
        $request->validate([
            'trade_uuid' => 'required|string',
        ]);

        $character = Character::where('uuid', $characterUuid)->firstOrFail();
        $trade = \App\Models\TradeOffer::where('uuid', $request->trade_uuid)->firstOrFail();

        try {
            $trade = $this->tradeService->cancelTrade($character, $trade);

            return response()->json([
                'success' => true,
                'trade_uuid' => $trade->uuid,
                'status' => $trade->status,
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }
}
