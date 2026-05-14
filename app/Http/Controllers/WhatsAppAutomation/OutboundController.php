<?php

namespace App\Http\Controllers\WhatsAppAutomation;

use App\Http\Controllers\Controller;
use App\Services\WhatsAppAutomation\BotApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OutboundController extends Controller
{
    public function __construct(
        protected BotApiService $botApiService
    ) {
    }

    public function types(): JsonResponse
    {
        return response()->json(
            $this->botApiService->getOutboundTypes()
        );
    }

    public function index(Request $request): JsonResponse
    {
        $query = $request->only([
            'status',
            'source_type',
            'session_id',
        ]);

        return response()->json(
            $this->botApiService->getOutbound($query)
        );
    }
}
