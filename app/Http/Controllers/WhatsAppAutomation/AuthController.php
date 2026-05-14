<?php

namespace App\Http\Controllers\WhatsAppAutomation;

use App\Http\Controllers\Controller;
use App\Services\WhatsAppAutomation\ReactTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Cookie;

class AuthController extends Controller
{
    protected function makeReactCookie(string $token): Cookie
    {
        $minutes = (int) config('whatsapp_automation.react_token_ttl_minutes', 30);

        return cookie(
            (string) config('whatsapp_automation.react_cookie_name', 'whatsapp_react_session'),
            $token,
            $minutes,
            '/',
            config('whatsapp_automation.react_cookie_domain'),
            (bool) config('whatsapp_automation.react_cookie_secure', true),
            true,
            false,
            (string) config('whatsapp_automation.react_cookie_same_site', 'none')
        );
    }

    protected function forgetReactCookie(): Cookie
    {
        return cookie()->forget(
            (string) config('whatsapp_automation.react_cookie_name', 'whatsapp_react_session'),
            '/',
            config('whatsapp_automation.react_cookie_domain')
        );
    }

    protected function transformAuthPayload(array $payload): array
    {
        return [
            'id' => $payload['uid'] ?? null,
            'name' => $payload['name'] ?? null,
            'email' => $payload['email'] ?? null,
            'role' => $payload['role'] ?? null,
            'permissions' => $payload['permissions'] ?? [],
            'exp' => $payload['exp'] ?? null,
        ];
    }

    public function launch(Request $request, ReactTokenService $reactTokenService)
    {
        $user = Auth::user();

        if (!$user) {
            abort(403, 'Unauthorized');
        }

        // For now, allow only super admin
        $isSuperAdmin = in_array($user->type ?? '', ['super_admin', 'superadmin'], true)
            || (int) ($user->is_super_admin ?? 0) === 1;

        if (!$isSuperAdmin) {
            abort(403, 'You are not allowed to access WhatsApp Automation.');
        }

        $permissions = [
            'whatsapp.access',
            'whatsapp.dashboard.view',
            'whatsapp.leads.view',
            'whatsapp.handoff.resolve',
        ];

        $adminSessionId = (string) $request->session()->getId();
        $reactTokenService->markAdminSessionActive((int) $user->id, $adminSessionId);

        $token = $reactTokenService->issue($user, $permissions, $adminSessionId);
        $code = $reactTokenService->issueOneTimeCode($token);

        $frontendUrl = rtrim((string) config('whatsapp_automation.frontend_url'), '/');

        return redirect()->away($frontendUrl . '/auth/callback?code=' . urlencode($code));
    }

    public function embeddedSession(Request $request, ReactTokenService $reactTokenService): JsonResponse
    {
        $user = Auth::user();

        if (!$user) {
            abort(403, 'Unauthorized');
        }

        $isSuperAdmin = in_array($user->type ?? '', ['super_admin', 'superadmin'], true)
            || (int) ($user->is_super_admin ?? 0) === 1;

        if (!$isSuperAdmin) {
            abort(403, 'You are not allowed to access WhatsApp Automation.');
        }

        $permissions = [
            'whatsapp.access',
            'whatsapp.dashboard.view',
            'whatsapp.leads.view',
            'whatsapp.handoff.resolve',
        ];

        $adminSessionId = (string) $request->session()->getId();
        $reactTokenService->markAdminSessionActive((int) $user->id, $adminSessionId);
        $token = $reactTokenService->issue($user, $permissions, $adminSessionId);
        $payload = $reactTokenService->verify($token);

        return response()->json([
            'success' => true,
            'user' => $this->transformAuthPayload($payload),
        ])->withCookie($this->makeReactCookie($token));
    }

    public function verify(Request $request, ReactTokenService $reactTokenService): JsonResponse
    {
        $code = (string) $request->input('code', '');

        if ($code === '') {
            return response()->json([
                'success' => false,
                'error' => 'Code is required.',
            ], 422);
        }

        try {
            $token = $reactTokenService->consumeOneTimeCode($code);
            $payload = $reactTokenService->verify($token);

            return response()->json([
                'success' => true,
                'user' => $this->transformAuthPayload($payload),
            ])->withCookie($this->makeReactCookie($token));
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 401);
        }
    }

    public function me(Request $request): JsonResponse
    {
        $payload = $request->attributes->get('whatsapp_auth', []);

        return response()->json([
            'success' => true,
            'user' => $this->transformAuthPayload($payload),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $payload = $request->attributes->get('whatsapp_auth', []);

        $adminSessionId = (string) ($payload['admin_session_id'] ?? '');
        $userId = (int) ($payload['uid'] ?? 0);

        if ($userId > 0 && $adminSessionId !== '') {
            $reactTokenService = app(ReactTokenService::class);
            $reactTokenService->revokeAdminSession($userId, $adminSessionId);
        }

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully.',
            'user' => $this->transformAuthPayload($payload),
        ])->withCookie($this->forgetReactCookie());
    }
}
