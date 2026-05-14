<?php

namespace App\Http\Controllers\WhatsAppAutomation;

use App\Http\Controllers\Controller;
use App\Services\WhatsAppAutomation\BotApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TrainingController extends Controller
{
    public function __construct(
        protected BotApiService $botApiService
    ) {
    }

    protected function assertBotType(string $botType): string
    {
        $normalized = strtolower(trim($botType));
        abort_unless(in_array($normalized, ['sales', 'support'], true), 404);

        return $normalized;
    }

    public function index(string $botType): JsonResponse
    {
        $botType = $this->assertBotType($botType);

        return response()->json(
            $this->botApiService->getTrainingItems($botType)
        );
    }

    public function store(Request $request, string $botType): JsonResponse
    {
        $botType = $this->assertBotType($botType);
        $validated = $request->validate([
            'content' => ['required', 'string'],
        ]);

        return response()->json(
            $this->botApiService->createTrainingItem($botType, $validated['content'])
        );
    }

    public function destroy(string $botType, int $id): JsonResponse
    {
        $botType = $this->assertBotType($botType);

        return response()->json(
            $this->botApiService->deleteTrainingItem($botType, $id)
        );
    }
}
