<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\TradeUuidRequest;
use App\Models\Character;
use App\Models\TradeOffer;
use App\Services\TradeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TradeController extends Controller
{
    public function __construct(
        private TradeService $tradeService
    ) {}

    public function index(Request $request): JsonResponse
    {
        /** @var Character $character */
        $character = $request->attributes->get('character');
        $trades = $this->tradeService->getCharacterTrades($character);

        return response()->json([
            'trades' => $trades->map(fn (TradeOffer $trade) => $this->formatTrade($trade)),
        ]);
    }

    public function getCurrentTrade(Request $request): JsonResponse
    {
        /** @var Character $character */
        $character = $request->attributes->get('character');

        $trade = TradeOffer::where('status', 'pending')
            ->where(function ($q) use ($character) {
                $q->where('initiator_uuid', $character->uuid)
                    ->orWhere('partner_uuid', $character->uuid);
            })
            ->with(['initiator', 'partner', 'items.item.template', 'items.resource.template'])
            ->first();

        return response()->json([
            'trade' => $trade ? $this->formatTrade($trade) : null,
        ]);
    }

    public function create(Request $request): JsonResponse
    {
        $request->validate([
            'partner_uuid' => 'required|string|exists:characters,uuid',
        ]);

        /** @var Character $character */
        $character = $request->attributes->get('character');
        $partner = Character::where('uuid', $request->partner_uuid)->firstOrFail();

        try {
            $trade = $this->tradeService->createTrade($character, $partner);

            return response()->json([
                'success' => true,
                'trade' => $this->formatTrade($trade->load(['initiator', 'partner', 'items'])),
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function addItem(Request $request): JsonResponse
    {
        $request->validate([
            'trade_uuid' => 'required|string|exists:trade_offers,uuid',
            'item_uuid' => 'required|string|exists:items,uuid',
        ]);

        /** @var Character $character */
        $character = $request->attributes->get('character');
        $trade = TradeOffer::where('uuid', $request->trade_uuid)->firstOrFail();

        try {
            $this->tradeService->addItemToTrade($character, $trade, $request->item_uuid);

            return response()->json(['success' => true]);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function addResource(Request $request): JsonResponse
    {
        $request->validate([
            'trade_uuid' => 'required|string|exists:trade_offers,uuid',
            'template_slug' => 'required|string',
            'quantity' => 'required|integer|min:1',
        ]);

        /** @var Character $character */
        $character = $request->attributes->get('character');
        $trade = TradeOffer::where('uuid', $request->trade_uuid)->firstOrFail();

        try {
            $this->tradeService->addResourceToTrade(
                $character,
                $trade,
                $request->template_slug,
                (int) $request->quantity
            );

            return response()->json(['success' => true]);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function confirm(TradeUuidRequest $request): JsonResponse
    {
        /** @var Character $character */
        $character = $request->attributes->get('character');
        $trade = TradeOffer::where('uuid', $request->trade_uuid)->firstOrFail();

        try {
            $trade = $this->tradeService->confirmTrade($character, $trade);
            $trade->load(['initiator', 'partner', 'items.item.template', 'items.resource.template']);

            return response()->json([
                'success' => true,
                'trade' => $this->formatTrade($trade),
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function cancel(TradeUuidRequest $request): JsonResponse
    {
        /** @var Character $character */
        $character = $request->attributes->get('character');
        $trade = TradeOffer::where('uuid', $request->trade_uuid)->firstOrFail();

        try {
            $this->tradeService->cancelTrade($character, $trade);

            return response()->json(['success' => true]);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    private function formatTrade(TradeOffer $trade): array
    {
        $trade->loadMissing(['initiator', 'partner', 'items.item.template', 'items.resource.template']);

        return [
            'uuid' => $trade->uuid,
            'status' => $trade->status,
            'initiator_uuid' => $trade->initiator_uuid,
            'partner_uuid' => $trade->partner_uuid,
            'initiator_accepted' => $trade->initiator_accepted,
            'partner_accepted' => $trade->partner_accepted,
            'created_at' => $trade->created_at,
            'initiator' => $trade->initiator ? [
                'uuid' => $trade->initiator->uuid,
                'name' => $trade->initiator->name,
            ] : null,
            'partner' => $trade->partner ? [
                'uuid' => $trade->partner->uuid,
                'name' => $trade->partner->name,
            ] : null,
            'items' => $trade->items->map(function ($item) {
                return [
                    'uuid' => $item->uuid,
                    'character_uuid' => $item->character_uuid,
                    'item_uuid' => $item->item_uuid,
                    'resource_uuid' => $item->resource_uuid,
                    'template_slug' => $item->template_slug
                        ?? $item->item?->template_slug
                        ?? $item->resource?->template_slug,
                    'quantity' => $item->quantity,
                ];
            }),
        ];
    }
}
