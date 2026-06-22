<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Dto\Content\ContentImportDto;
use App\Http\Controllers\Controller;
use App\Services\ContentImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContentImportController extends Controller
{
    public function __construct(
        private ContentImportService $importService
    ) {}

    public function import(Request $request): JsonResponse
    {
        try {
            $data = $request->json()->all();
            $dto = ContentImportDto::fromArray($data);
            $report = $this->importService->import($dto);

            return response()->json([
                'success' => true,
                'report' => $report,
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Validation failed',
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => 'Import failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
