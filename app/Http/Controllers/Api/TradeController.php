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

    public function show(Request $request, string $id): JsonResponse
    {
        $userId = $request->query('user_id');
        if (!$userId) {
            return response()->json(['error' => 'user_id required'], 400);
        }

        try {
            return response()->json([
                'trade' => $this->tradeService->getTrade((int)$id, (int)$userId),
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
            return response()->json([
                'success' => true,
                'trade_id' => $trade->id,
                'message' => 'Обмен создан',
            ], 201);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function addItem(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|integer',
            'template_id' => 'required|integer',
            'quantity' => 'required|integer|min:1',
        ]);

        try {
            $tradeId = (int)$id;
            $userId = (int)$request->input('user_id');

            $this->tradeService->addItem(
                $userId,
                $tradeId,
                (int)$request->input('template_id'),
                (int)$request->input('quantity')
            );
            return response()->json([
                'message' => 'Предмет добавлен',
                'trade' => $this->tradeService->getTrade($tradeId, $userId),
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function reduceItem(Request $request, string $id, string $itemId): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|integer',
            'quantity' => 'required|integer|min:1',
        ]);

        try {
            $tradeId = (int)$id;
            $userId = (int)$request->input('user_id');

            $this->tradeService->reduceItem(
                $userId,
                $tradeId,
                (int)$itemId,
                (int)$request->input('quantity')
            );
            return response()->json([
                'message' => 'Количество уменьшено',
                'trade' => $this->tradeService->getTrade($tradeId, $userId),
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function removeItem(Request $request, string $id, string $itemId): JsonResponse
    {
        $request->validate(['user_id' => 'required|integer']);

        try {
            $tradeId = (int)$id;
            $userId = (int)$request->input('user_id');

            $this->tradeService->removeItem(
                $userId,
                $tradeId,
                (int)$itemId
            );
            return response()->json([
                'message' => 'Предмет убран',
                'trade' => $this->tradeService->getTrade($tradeId, $userId),
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function addGold(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|integer',
            'amount' => 'required|integer|min:0',
        ]);

        try {
            $tradeId = (int)$id;
            $userId = (int)$request->input('user_id');

            $this->tradeService->addGold(
                $userId,
                $tradeId,
                (int)$request->input('amount')
            );
            return response()->json([
                'message' => 'Золото добавлено',
                'trade' => $this->tradeService->getTrade($tradeId, $userId),
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function accept(Request $request, string $id): JsonResponse
    {
        $request->validate(['user_id' => 'required|integer']);

        try {
            $tradeId = (int)$id;
            $userId = (int)$request->input('user_id');

            $trade = $this->tradeService->accept($userId, $tradeId);
            return response()->json([
                'message' => $trade->status === 'completed' ? 'Обмен завершён!' : 'Подтверждено',
                'trade' => $this->tradeService->getTrade($tradeId, $userId),
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function cancel(Request $request, string $id): JsonResponse
    {
        $request->validate(['user_id' => 'required|integer']);

        try {
            $tradeId = (int)$id;
            $userId = (int)$request->input('user_id');

            $this->tradeService->cancel($userId, $tradeId);
            return response()->json(['message' => 'Обмен отменён']);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }
}
