<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
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

        return response()->json([
            'lots' => $lots->map(fn($lot) => [
                'uuid' => $lot->uuid,
                'template_slug' => $lot->template_slug,
                'template_name' => $lot->template->name,
                'template_icon' => $lot->template->icon,
                'quantity' => $lot->quantity,
                'price' => $lot->price,
                'seller_name' => $lot->seller->name,
                'is_infinite' => $lot->is_infinite,
                'status' => $lot->status,
            ]),
        ]);
    }

    public function myLots(string $characterUuid): JsonResponse
    {
        $character = Character::where('uuid', $characterUuid)->firstOrFail();
        $lots = $this->auctionService->getMyLots($character);

        return response()->json([
            'lots' => $lots->map(fn($lot) => [
                'uuid' => $lot->uuid,
                'template_slug' => $lot->template_slug,
                'template_name' => $lot->template->name,
                'quantity' => $lot->quantity,
                'price' => $lot->price,
                'status' => $lot->status,
                'is_infinite' => $lot->is_infinite,
            ]),
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
                'temporary_slot_uuid' => $temporarySlot->uuid,
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

    public function buyLot(Request $request, string $characterUuid): JsonResponse
    {
        $request->validate([
            'lot_uuid' => 'required|string',
        ]);

        $character = Character::where('uuid', $characterUuid)->firstOrFail();

        try {
            $result = $this->auctionService->buyLot(
                $character,
                $request->lot_uuid
            );

            return response()->json([
                'success' => true,
                'is_infinite' => $result['is_infinite'],
                'lot_uuid' => $result['lot']->uuid,
                'template_slug' => $result['lot']->template_slug,
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
}
