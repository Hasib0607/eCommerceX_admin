<?php

namespace App\Http\Middleware;

use App\Services\WhatsAppAutomation\ReactTokenService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyWhatsAppReactToken
{
    public function __construct(
        protected ReactTokenService $reactTokenService
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $cookieName = (string) config('whatsapp_automation.react_cookie_name', 'whatsapp_react_session');
        $token = (string) $request->cookie($cookieName, '');

        if ($token === '') {
            return response()->json([
                'success' => false,
                'error' => 'Missing authentication session.',
            ], 401);
        }

        try {
            $payload = $this->reactTokenService->verify($token);
            $this->reactTokenService->assertAdminSessionActive($payload);

            $request->attributes->set('whatsapp_auth', $payload);

            return $next($request);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 401);
        }
    }
}
