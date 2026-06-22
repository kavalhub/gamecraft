<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AuctionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuctionController extends Controller
{
    public function __construct(
        private AuctionService $auctionService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $type = $request->query('type');
        $templateId = $request->query('template_id') ? (int)$request->query('template_id') : null;

        return response()->json([
            'lots' => $this->auctionService->getActiveLots($type, $templateId),
        ]);
    }

    public function my(Request $request): JsonResponse
    {
        $userId = $request->query('user_id');
        if (!$userId) {
            return response()->json(['error' => 'user_id required'], 400);
        }

        return response()->json([
            'lots' => $this->auctionService->getMyLots((int)$userId),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|integer',
            'template_id' => 'required|integer',
            'price' => 'required|integer|min:1',
            'quantity' => 'sometimes|integer|min:1',
        ]);

        try {
            $lot = $this->auctionService->listLot(
                (int)$request->input('user_id'),
                (int)$request->input('template_id'),
                (int)$request->input('quantity', 1),
                (int)$request->input('price')
            );

            return response()->json([
                'message' => 'Лот выставлен',
                'lot_id' => $lot->id,
            ], 201);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function buy(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|integer',
        ]);

        try {
            $result = $this->auctionService->buyLot(
                (int)$request->input('user_id'),
                $id
            );

            $lot = $result['lot'];
            $buyer = $result['buyer'];
            $totalPrice = $lot->price * $lot->quantity;

            return response()->json([
                'message' => 'Покупка успешна',
                'item_name' => $lot->template->name,
                'item_type' => $lot->template->type,
                'item_icon' => $lot->template->icon,
                'item_quantity' => $lot->quantity,
                'payment_amount' => $totalPrice,
                'seller_name' => $lot->seller?->name ?? 'Неизвестный',
                'buyer_gold' => $buyer->gold,
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function cancel(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|integer',
        ]);

        try {
            $result = $this->auctionService->cancelLot(
                (int)$request->input('user_id'),
                $id
            );

            return response()->json([
                'message' => 'Лот отменён',
                'lot' => $result,
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }
}
