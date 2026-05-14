<?php

namespace App\Http\Controllers\WhatsAppAutomation;

use App\Http\Controllers\Controller;
use App\Services\WhatsAppAutomation\BotApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CampaignController extends Controller
{
    public function __construct(
        protected BotApiService $botApiService
    ) {
    }

    public function types(): JsonResponse
    {
        return response()->json(
            $this->botApiService->getCampaignTypes()
        );
    }

    public function index(): JsonResponse
    {
        return response()->json(
            $this->botApiService->getCampaigns()
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string'],
            'bot_type' => ['required', 'string'],
            'target_tag' => ['required', 'string'],
            'campaign_type' => ['required', 'string'],
            'message_text' => ['required', 'string'],
            'image_url' => ['nullable', 'string'],
        ]);

        return response()->json(
            $this->botApiService->createCampaign([
                'name' => $validated['name'],
                'bot_type' => $validated['bot_type'],
                'target_tag' => $validated['target_tag'],
                'campaign_type' => $validated['campaign_type'],
                'message_text' => $validated['message_text'],
                'image_url' => $validated['image_url'] ?? '',
            ])
        );
    }

    public function show(int $id): JsonResponse
    {
        return response()->json(
            $this->botApiService->getCampaign($id)
        );
    }

    public function recipients(int $id): JsonResponse
    {
        return response()->json(
            $this->botApiService->getCampaignRecipients($id)
        );
    }

    public function sendTagPromotion(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['nullable', 'string'],
            'bot_type' => ['required', 'string'],
            'target_tag' => ['required', 'string'],
            'campaign_type' => ['required', 'string'],
            'message_text' => ['required', 'string'],
            'image_url' => ['nullable', 'string'],
            'dispatch_immediately' => ['nullable', 'boolean'],
        ]);

        return response()->json(
            $this->botApiService->sendTagPromotion([
                'name' => $validated['name'] ?? 'Tag Promotion',
                'bot_type' => $validated['bot_type'],
                'target_tag' => $validated['target_tag'],
                'campaign_type' => $validated['campaign_type'],
                'message_text' => $validated['message_text'],
                'image_url' => $validated['image_url'] ?? '',
                'dispatch_immediately' => (bool) ($validated['dispatch_immediately'] ?? true),
            ])
        );
    }
}
