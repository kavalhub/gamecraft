<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\TradeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TradeController extends Controller
{
    public function __construct(
        private TradeService $tradeService
    ) {}

    public function active(Request $request): JsonResponse
    {
        $userId = $request->query('user_id');
        if (!$userId) {
            return response()->json(['error' => 'user_id required'], 400);
        }

        return response()->json([
            'trades' => $this->tradeService->getActiveTrades((int)$userId),
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $userId = $request->query('user_id');
        if (!$userId) {
            return response()->json(['error' => 'user_id required'], 400);
        }

        try {
            return response()->json([
                'trade' => $this->tradeService->getTrade($id, (int)$userId),
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'initiator_id' => 'required|integer',
            'partner_id' => 'required|integer',
        ]);

        try {
            $trade = $this->tradeService->createTrade(
                (int)$request->input('initiator_id'),
                (int)$request->input('partner_id')
            );
            return response()->json(['trade_id' => $trade->id], 201);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function addItem(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|integer',
            'instance_id' => 'required|integer',
            'quantity' => 'sometimes|integer|min:1',
        ]);

        try {
            $trade = $this->tradeService->addItem(
                (int)$request->input('user_id'),
                $id,
                (int)$request->input('instance_id'),
                (int)$request->input('quantity', 1)
            );
            return response()->json([
                'message' => 'Предмет добавлен',
                'trade' => $this->tradeService->getTrade($id, (int)$request->input('user_id')),
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function removeItem(Request $request, int $id, int $itemId): JsonResponse
    {
        $request->validate(['user_id' => 'required|integer']);

        try {
            $this->tradeService->removeItem(
                (int)$request->input('user_id'),
                $id,
                $itemId
            );
            return response()->json([
                'message' => 'Предмет убран',
                'trade' => $this->tradeService->getTrade($id, (int)$request->input('user_id')),
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function addGold(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|integer',
            'amount' => 'required|integer|min:0',
        ]);

        try {
            $this->tradeService->addGold(
                (int)$request->input('user_id'),
                $id,
                (int)$request->input('amount')
            );
            return response()->json([
                'message' => 'Золото добавлено',
                'trade' => $this->tradeService->getTrade($id, (int)$request->input('user_id')),
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function accept(Request $request, int $id): JsonResponse
    {
        $request->validate(['user_id' => 'required|integer']);

        try {
            $trade = $this->tradeService->accept(
                (int)$request->input('user_id'),
                $id
            );
            return response()->json([
                'message' => $trade->status === 'completed' ? 'Обмен завершён!' : 'Подтверждено',
                'trade' => $this->tradeService->getTrade($id, (int)$request->input('user_id')),
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function cancel(Request $request, int $id): JsonResponse
    {
        $request->validate(['user_id' => 'required|integer']);

        try {
            $this->tradeService->cancel(
                (int)$request->input('user_id'),
                $id
            );
            return response()->json(['message' => 'Обмен отменён']);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }
}
