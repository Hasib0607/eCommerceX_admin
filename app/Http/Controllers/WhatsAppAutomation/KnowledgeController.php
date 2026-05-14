<?php

namespace App\Http\Controllers\WhatsAppAutomation;

use App\Http\Controllers\Controller;
use App\Services\WhatsAppAutomation\BotApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KnowledgeController extends Controller
{
    public function __construct(
        protected BotApiService $botApiService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $query = $request->only([
            'bot_type',
            'kind',
            'status',
            'search',
            'page',
            'limit',
        ]);

        return response()->json(
            $this->botApiService->getKnowledgeItems($query)
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'bot_type' => ['required', 'in:sales,support'],
            'kind' => ['required', 'in:training,faq,static_reply,content_pack'],
            'status' => ['nullable', 'in:active,archived'],
            'title' => ['nullable', 'string'],
            'content' => ['required', 'string'],
        ]);

        return response()->json(
            $this->botApiService->createKnowledgeItem([
                'bot_type' => $validated['bot_type'],
                'kind' => $validated['kind'],
                'status' => $validated['status'] ?? 'active',
                'title' => $validated['title'] ?? null,
                'content' => $validated['content'],
            ])
        );
    }

    public function show(int $id): JsonResponse
    {
        return response()->json(
            $this->botApiService->getKnowledgeItem($id)
        );
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['sometimes', 'nullable', 'string'],
            'content' => ['sometimes', 'required', 'string'],
            'status' => ['sometimes', 'required', 'in:active,archived'],
        ]);

        return response()->json(
            $this->botApiService->updateKnowledgeItem($id, $validated)
        );
    }

    public function destroy(int $id): JsonResponse
    {
        return response()->json(
            $this->botApiService->deleteKnowledgeItem($id)
        );
    }
}
