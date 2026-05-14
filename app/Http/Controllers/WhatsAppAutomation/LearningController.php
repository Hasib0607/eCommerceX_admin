<?php

namespace App\Http\Controllers\WhatsAppAutomation;

use App\Http\Controllers\Controller;
use App\Services\WhatsAppAutomation\BotApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LearningController extends Controller
{
    public function __construct(
        protected BotApiService $botApiService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $query = $request->only([
            'status',
            'bot_type',
        ]);

        return response()->json(
            $this->botApiService->getLearningQuestions($query)
        );
    }

    public function show(int $id): JsonResponse
    {
        return response()->json(
            $this->botApiService->getLearningQuestion($id)
        );
    }

    public function resolve(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'manual_answer' => ['required', 'string'],
            'training_content' => ['nullable', 'string'],
            'add_to_training' => ['nullable', 'boolean'],
        ]);

        return response()->json(
            $this->botApiService->resolveLearningQuestion($id, [
                'manual_answer' => $validated['manual_answer'],
                'training_content' => $validated['training_content'] ?? '',
                'add_to_training' => (bool) ($validated['add_to_training'] ?? true),
            ])
        );
    }
}
