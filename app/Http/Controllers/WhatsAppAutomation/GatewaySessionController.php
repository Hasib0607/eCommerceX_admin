<?php

namespace App\Http\Controllers\WhatsAppAutomation;

use App\Http\Controllers\Controller;
use App\Services\WhatsAppAutomation\BotApiService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Throwable;

class GatewaySessionController extends Controller
{
    private const OTP_GATEWAY_TENANT_CACHE_KEY = 'whatsapp_gateway.active_otp_tenant_id';

    public function __construct(
        protected BotApiService $botApiService
    ) {
    }

    protected function cleanTenantId(string $tenantId): string
    {
        $tenantId = trim($tenantId);

        if ($tenantId === '') {
            throw new HttpResponseException(response()->json([
                'success' => false,
                'message' => 'Tenant ID is required.',
            ], 422));
        }

        if (! preg_match('/^[A-Za-z0-9._:-]{2,120}$/', $tenantId)) {
            throw new HttpResponseException(response()->json([
                'success' => false,
                'message' => 'Tenant ID may contain letters, numbers, dot, dash, underscore or colon only.',
            ], 422));
        }

        return $tenantId;
    }

    protected function respond(array $payload): JsonResponse
    {
        $status = ($payload['success'] ?? true) === false ? 502 : 200;

        return response()->json($payload, $status);
    }

    protected function runGatewayRequest(callable $callback, string $tenantId, ?callable $afterSuccess = null): JsonResponse
    {
        try {
            $payload = $callback();

            if (($payload['success'] ?? true) !== false && $afterSuccess) {
                $afterSuccess($payload);
            }

            return $this->respond($payload);
        } catch (Throwable $e) {
            $message = trim(strip_tags($e->getMessage()));

            if ($message === '' || str_contains(strtolower($message), 'doctype html')) {
                $message = 'WhatsApp gateway is unavailable or the session route is not deployed.';
            }

            return response()->json([
                'success' => false,
                'tenant_id' => $tenantId,
                'status' => 'gateway_unavailable',
                'message' => mb_substr(preg_replace('/\s+/', ' ', $message) ?: $message, 0, 220),
            ], 502);
        }
    }

    private function rememberOtpTenant(string $tenantId): void
    {
        Cache::forever(self::OTP_GATEWAY_TENANT_CACHE_KEY, $tenantId);
    }

    private function forgetOtpTenant(string $tenantId): void
    {
        if (Cache::get(self::OTP_GATEWAY_TENANT_CACHE_KEY) === $tenantId) {
            Cache::forget(self::OTP_GATEWAY_TENANT_CACHE_KEY);
        }
    }

    public function create(Request $request): JsonResponse
    {
        $tenantId = $this->cleanTenantId((string) ($request->input('tenant_id') ?: $request->input('tenantId')));

        return $this->runGatewayRequest(
            fn () => $this->botApiService->createGatewaySession($tenantId),
            $tenantId,
            fn () => $this->rememberOtpTenant($tenantId)
        );
    }

    public function status(string $tenantId): JsonResponse
    {
        $tenantId = $this->cleanTenantId(rawurldecode($tenantId));

        return $this->runGatewayRequest(
            fn () => $this->botApiService->getGatewaySessionStatus($tenantId),
            $tenantId,
            fn () => $this->rememberOtpTenant($tenantId)
        );
    }

    public function qr(string $tenantId): JsonResponse
    {
        $tenantId = $this->cleanTenantId(rawurldecode($tenantId));

        return $this->runGatewayRequest(
            fn () => $this->botApiService->getGatewaySessionQr($tenantId),
            $tenantId
        );
    }

    public function logout(string $tenantId): JsonResponse
    {
        $tenantId = $this->cleanTenantId(rawurldecode($tenantId));

        return $this->runGatewayRequest(
            fn () => $this->botApiService->logoutGatewaySession($tenantId),
            $tenantId,
            fn () => $this->forgetOtpTenant($tenantId)
        );
    }
}
