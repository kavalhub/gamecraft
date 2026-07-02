<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuctionLot;
use App\Models\Character;
use App\Services\AuctionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuctionController extends Controller
{
    public function __construct(
        private AuctionService $auctionService
    ) {}

    public function activeLots(Request $request): JsonResponse
    {
        $templateSlug = $request->query('template_slug');
        $lots = $this->auctionService->getActiveLots($templateSlug);

        $buyer = null;
        $characterUuid = $request->query('character_uuid');
        if ($characterUuid) {
            $buyer = Character::where('uuid', $characterUuid)->first();
        }

        return response()->json([
            'lots' => $lots
                ->filter(fn ($lot) => !$buyer || $this->auctionService->isLotVisibleToBuyer($buyer, $lot))
                ->map(fn ($lot) => $this->formatLot($lot, $buyer))
                ->values(),
        ]);
    }

    public function myLots(string $characterUuid): JsonResponse
    {
        $character = Character::where('uuid', $characterUuid)->firstOrFail();
        $lots = $this->auctionService->getMyLots($character);

        return response()->json([
            'lots' => $lots->map(fn ($lot) => $this->formatLot($lot, $character)),
        ]);
    }

    public function buyInfo(string $characterUuid, string $lotUuid): JsonResponse
    {
        $character = Character::where('uuid', $characterUuid)->firstOrFail();
        $lot = AuctionLot::with('template')
            ->where('uuid', $lotUuid)
            ->where('status', 'active')
            ->firstOrFail();

        $limits = $this->auctionService->getBuyLimits($character, $lot);

        return response()->json([
            'lot_uuid' => $lot->uuid,
            'is_infinite' => $lot->is_infinite,
            'quantity' => $lot->quantity,
            'price' => $lot->price,
            'max_purchasable' => $limits['max_purchasable'],
            'max_by_gold' => $limits['max_by_gold'],
            'max_by_inventory' => $limits['max_by_inventory'],
            'gold_available' => $limits['gold_available'],
            'template_name' => $lot->template->name,
            'template_icon' => $lot->template->icon,
            'template_description' => $lot->template->description,
            'max_stack' => $lot->template->max_stack,
            'template_type' => $lot->template->type,
            'template_slug' => $lot->template_slug,
        ]);
    }

    public function prepareLot(Request $request, string $characterUuid): JsonResponse
    {
        $request->validate([
            'item_uuid' => 'required|string',
            'price' => 'required|integer|min:1',
        ]);

        $character = Character::where('uuid', $characterUuid)->firstOrFail();

        try {
            $temporarySlot = $this->auctionService->prepareLot(
                $character,
                $request->item_uuid,
                $request->price
            );

            return response()->json([
                'success' => true,
                'buffer_slot_uuid' => $temporarySlot->uuid,
                'expires_at' => $temporarySlot->timestamps_end,
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function confirmLot(Request $request, string $characterUuid): JsonResponse
    {
        $request->validate([
            'item_uuid' => 'required|string',
            'price' => 'required|integer|min:1',
        ]);

        $character = Character::where('uuid', $characterUuid)->firstOrFail();

        try {
            $lot = $this->auctionService->confirmLot(
                $character,
                $request->item_uuid,
                $request->price
            );

            return response()->json([
                'success' => true,
                'lot' => [
                    'uuid' => $lot->uuid,
                    'template_slug' => $lot->template_slug,
                    'price' => $lot->price,
                    'status' => $lot->status,
                ],
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function listLot(Request $request, string $characterUuid): JsonResponse
    {
        $request->validate([
            'item_uuid' => 'required|string',
            'price' => 'required|integer|min:1',
        ]);

        $character = Character::where('uuid', $characterUuid)->firstOrFail();

        try {
            $lot = $this->auctionService->listLot(
                $character,
                $request->item_uuid,
                $request->price
            );

            return response()->json([
                'success' => true,
                'lot' => [
                    'uuid' => $lot->uuid,
                    'template_slug' => $lot->template_slug,
                    'price' => $lot->price,
                    'status' => $lot->status,
                ],
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function buyLot(Request $request, string $characterUuid): JsonResponse
    {
        $request->validate([
            'lot_uuid' => 'required|string',
            'quantity' => 'sometimes|integer|min:1',
        ]);

        $character = Character::where('uuid', $characterUuid)->firstOrFail();

        try {
            $result = $this->auctionService->buyLot(
                $character,
                $request->lot_uuid,
                (int) ($request->quantity ?? 1)
            );

            return response()->json([
                'success' => true,
                'is_infinite' => $result['is_infinite'],
                'lot_uuid' => $result['lot']->uuid,
                'template_slug' => $result['lot']->template_slug,
                'quantity' => $result['quantity'] ?? 1,
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function cancelLot(Request $request, string $characterUuid): JsonResponse
    {
        $request->validate([
            'lot_uuid' => 'required|string',
        ]);

        $character = Character::where('uuid', $characterUuid)->firstOrFail();

        try {
            $lot = $this->auctionService->cancelLot(
                $character,
                $request->lot_uuid
            );

            return response()->json([
                'success' => true,
                'lot_uuid' => $lot->uuid,
                'status' => $lot->status,
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    private function formatLot(AuctionLot $lot, ?Character $buyer = null): array
    {
        $data = [
            'uuid' => $lot->uuid,
            'template_slug' => $lot->template_slug,
            'template_name' => $lot->template->name,
            'template_icon' => $lot->template->icon,
            'template_description' => $lot->template->description,
            'template_type' => $lot->template->type,
            'max_stack' => $lot->template->max_stack,
            'quantity' => $lot->quantity,
            'price' => $lot->price,
            'seller_uuid' => $lot->seller_uuid,
            'seller_name' => $lot->seller->name,
            'is_infinite' => $lot->is_infinite,
            'status' => $lot->status,
        ];

        if ($buyer) {
            $limits = $this->auctionService->getBuyLimits($buyer, $lot);
            $data['max_purchasable'] = $limits['max_purchasable'];
            $data['max_by_gold'] = $limits['max_by_gold'];
            $data['max_by_inventory'] = $limits['max_by_inventory'];
            $data['gold_available'] = $limits['gold_available'];
        }

        return $data;
    }
}
