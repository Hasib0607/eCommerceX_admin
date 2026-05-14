<?php

namespace App\Http\Controllers\WhatsAppAutomation;

use App\Http\Controllers\Controller;
use App\Services\WhatsAppAutomation\BotApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HandoffController extends Controller
{
    public function __construct(
        protected BotApiService $botApiService
    ) {
    }

    public function show(string $sessionId): JsonResponse
    {
        return response()->json($this->botApiService->getHandoff($sessionId));
    }

    public function resolve(string $sessionId): JsonResponse
    {
        return response()->json($this->botApiService->resolveHandoff($sessionId));
    }

    public function assignBot(string $sessionId): JsonResponse
    {
        return response()->json($this->botApiService->assignBotToHandoff($sessionId));
    }

    public function sendMessage(Request $request, string $sessionId): JsonResponse
    {
        $validated = $request->validate([
            'message_text' => ['required', 'string'],
            'image_url' => ['nullable', 'string'],
        ]);

        $leadPayload = $this->botApiService->getLead($sessionId, compact: true);
        $lead = $leadPayload['lead'] ?? null;
        $handoff = $leadPayload['handoff'] ?? null;

        $isHandoffActive = (int) ($handoff['is_handoff_active'] ?? 0) === 1
            || strtolower((string) ($handoff['status'] ?? '')) === 'active';

        if (!$lead) {
            return response()->json([
                'success' => false,
                'error' => 'Lead not found for this session.',
            ], 404);
        }

        if (!$isHandoffActive) {
            return response()->json([
                'success' => false,
                'error' => 'Manual reply is available only during active handoff.',
            ], 422);
        }

        $queued = $this->botApiService->queueManualOutbound(
            $sessionId,
            (string) ($lead['bot_type'] ?? 'sales'),
            $validated['message_text'],
            $validated['image_url'] ?? null,
            'manual'
        );

        $outboundId = (int) ($queued['outbound_id'] ?? 0);
        $dispatch = $outboundId > 0
            ? $this->botApiService->dispatchOutbound($outboundId)
            : null;

        return response()->json([
            'success' => (bool) ($dispatch['success'] ?? $queued['success'] ?? true),
            'message' => $dispatch['message'] ?? 'Manual message sent successfully.',
            'outbound_id' => $outboundId ?: null,
            'queue' => $queued,
            'dispatch' => $dispatch,
        ]);
    }
}
