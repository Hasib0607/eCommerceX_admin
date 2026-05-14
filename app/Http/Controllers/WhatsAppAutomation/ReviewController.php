<?php

namespace App\Http\Controllers\WhatsAppAutomation;

use App\Http\Controllers\Controller;
use App\Services\WhatsAppAutomation\BotApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function __construct(
        protected BotApiService $botApiService
    ) {
    }

    protected function queueResponse(string $queue, Request $request): JsonResponse
    {
        $query = $request->only([
            'page',
            'limit',
        ]);

        try {
            return response()->json(
                $this->botApiService->getReviewQueue($queue, $query)
            );
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => sprintf('Failed to load %s review queue.', $queue),
                'details' => $e->getMessage(),
                'items' => [],
                'pagination' => [
                    'page' => (int) ($query['page'] ?? 1),
                    'limit' => (int) ($query['limit'] ?? 25),
                    'total' => 0,
                    'total_pages' => 0,
                    'has_next' => false,
                    'has_prev' => false,
                ],
            ], 502);
        }
    }

    public function handoffs(Request $request): JsonResponse
    {
        return $this->queueResponse('handoffs', $request);
    }

    public function abusive(Request $request): JsonResponse
    {
        return $this->queueResponse('abusive', $request);
    }

    public function manual(Request $request): JsonResponse
    {
        return $this->queueResponse('manual', $request);
    }

    public function unclear(Request $request): JsonResponse
    {
        return $this->queueResponse('unclear', $request);
    }

    public function dropped(Request $request): JsonResponse
    {
        return $this->queueResponse('dropped', $request);
    }

    public function assignHuman(Request $request, string $sessionId): JsonResponse
    {
        $validated = $request->validate([
            'assigned_to' => ['required', 'string'],
            'queue_type' => ['nullable', 'string'],
        ]);

        try {
            return response()->json(
                $this->botApiService->assignReviewToHuman(
                    $sessionId,
                    $validated['assigned_to'],
                    $validated['queue_type'] ?? null
                )
            );
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to assign review item to human.',
                'details' => $e->getMessage(),
            ], 502);
        }
    }

    public function disableBot(string $sessionId): JsonResponse
    {
        try {
            return response()->json(
                $this->botApiService->disableReviewBot($sessionId)
            );
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to disable bot for this review item.',
                'details' => $e->getMessage(),
            ], 502);
        }
    }

    public function enableBot(string $sessionId): JsonResponse
    {
        try {
            return response()->json(
                $this->botApiService->enableReviewBot($sessionId)
            );
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to re-enable bot for this review item.',
                'details' => $e->getMessage(),
            ], 502);
        }
    }

    public function resolve(Request $request, string $sessionId): JsonResponse
    {
        $validated = $request->validate([
            'note' => ['nullable', 'string'],
            'queue_type' => ['nullable', 'string'],
        ]);

        try {
            return response()->json(
                $this->botApiService->resolveReviewItem(
                    $sessionId,
                    $validated['note'] ?? null,
                    $validated['queue_type'] ?? null
                )
            );
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to resolve review item.',
                'details' => $e->getMessage(),
            ], 502);
        }
    }

    public function note(Request $request, string $sessionId): JsonResponse
    {
        $validated = $request->validate([
            'note' => ['required', 'string'],
            'queue_type' => ['nullable', 'string'],
        ]);

        try {
            return response()->json(
                $this->botApiService->addReviewNote(
                    $sessionId,
                    $validated['note'],
                    $validated['queue_type'] ?? null
                )
            );
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to add review note.',
                'details' => $e->getMessage(),
            ], 502);
        }
    }
}
