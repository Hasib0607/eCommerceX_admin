<?php

namespace App\Http\Controllers\WhatsAppAutomation;

use App\Http\Controllers\Controller;
use App\Services\WhatsAppAutomation\BotApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(
        protected BotApiService $botApiService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $auth = $request->attributes->get('whatsapp_auth', []);
        $compact = ! $request->has('compact')
            ? true
            : $request->boolean('compact');

        return response()->json([
            'success' => true,
            'auth' => [
                'uid' => $auth['uid'] ?? null,
                'name' => $auth['name'] ?? null,
                'email' => $auth['email'] ?? null,
                'role' => $auth['role'] ?? null,
                'permissions' => $auth['permissions'] ?? [],
                'exp' => $auth['exp'] ?? null,
            ],
            'bot_dashboard' => $this->botApiService->getDashboard(compact: $compact),
        ]);
    }
}
