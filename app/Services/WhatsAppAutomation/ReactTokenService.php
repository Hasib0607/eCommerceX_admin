<?php

namespace App\Services\WhatsAppAutomation;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class ReactTokenService
{
    public function issue(User $user, array $permissions = [], ?string $adminSessionId = null): string
    {
        $ttlMinutes = (int) config('whatsapp_automation.react_token_ttl_minutes', 30);
        $secret = (string) config('whatsapp_automation.react_token_secret');

        if ($secret === '') {
            throw new \RuntimeException('WHATSAPP_REACT_TOKEN_SECRET is not configured.');
        }

        $now = Carbon::now()->timestamp;
        $exp = Carbon::now()->addMinutes($ttlMinutes)->timestamp;

        $payload = [
            'uid' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->type ?? null,
            'permissions' => $permissions,
            'admin_session_id' => $adminSessionId,
            'iat' => $now,
            'exp' => $exp,
        ];

        $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $payloadB64 = $this->base64UrlEncode($payloadJson);
        $signature = hash_hmac('sha256', $payloadB64, $secret);
        $signatureB64 = $this->base64UrlEncode($signature);

        return $payloadB64 . '.' . $signatureB64;
    }

    public function verify(string $token): array
    {
        $secret = (string) config('whatsapp_automation.react_token_secret');

        if ($secret === '') {
            throw new \RuntimeException('WHATSAPP_REACT_TOKEN_SECRET is not configured.');
        }

        $parts = explode('.', $token);
        if (count($parts) !== 2) {
            throw new \RuntimeException('Invalid token format.');
        }

        [$payloadB64, $signatureB64] = $parts;

        $expectedSignature = hash_hmac('sha256', $payloadB64, $secret);
        $expectedSignatureB64 = $this->base64UrlEncode($expectedSignature);

        if (!hash_equals($expectedSignatureB64, $signatureB64)) {
            throw new \RuntimeException('Invalid token signature.');
        }

        $payloadJson = $this->base64UrlDecode($payloadB64);
        $payload = json_decode($payloadJson, true);

        if (!is_array($payload)) {
            throw new \RuntimeException('Invalid token payload.');
        }

        if (!isset($payload['exp']) || time() > (int) $payload['exp']) {
            throw new \RuntimeException('Token expired.');
        }

        return $payload;
    }

    public function markAdminSessionActive(int $userId, string $sessionId): void
    {
        $normalized = trim($sessionId);

        if ($userId <= 0 || $normalized === '') {
            return;
        }

        $ttlMinutes = max((int) config('whatsapp_automation.react_token_ttl_minutes', 30), 1);

        Cache::put(
            $this->adminSessionCacheKey($userId, $normalized),
            true,
            now()->addMinutes($ttlMinutes)
        );
    }

    public function revokeAdminSession(int $userId, string $sessionId): void
    {
        $normalized = trim($sessionId);

        if ($userId <= 0 || $normalized === '') {
            return;
        }

        Cache::forget($this->adminSessionCacheKey($userId, $normalized));
    }

    public function assertAdminSessionActive(array $payload): void
    {
        $userId = (int) ($payload['uid'] ?? 0);
        $sessionId = trim((string) ($payload['admin_session_id'] ?? ''));

        if ($userId <= 0 || $sessionId === '') {
            throw new \RuntimeException('Main admin session metadata missing.');
        }

        if (!Cache::get($this->adminSessionCacheKey($userId, $sessionId))) {
            throw new \RuntimeException('Main admin session expired.');
        }
    }

    public function issueOneTimeCode(string $token): string
    {
        $ttlMinutes = max((int) config('whatsapp_automation.react_code_ttl_minutes', 5), 1);
        $code = Str::random(64);

        Cache::put($this->codeCacheKey($code), $token, now()->addMinutes($ttlMinutes));

        return $code;
    }

    public function consumeOneTimeCode(string $code): string
    {
        $normalized = trim($code);

        if ($normalized === '') {
            throw new \RuntimeException('Code is required.');
        }

        $token = Cache::pull($this->codeCacheKey($normalized));

        if (!is_string($token) || $token === '') {
            throw new \RuntimeException('Invalid or expired code.');
        }

        return $token;
    }

    protected function codeCacheKey(string $code): string
    {
        return 'whatsapp_react_code:' . $code;
    }

    protected function adminSessionCacheKey(int $userId, string $sessionId): string
    {
        return 'whatsapp_react_admin_session:' . $userId . ':' . sha1($sessionId);
    }

    protected function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    protected function base64UrlDecode(string $value): string
    {
        $remainder = strlen($value) % 4;
        if ($remainder > 0) {
            $value .= str_repeat('=', 4 - $remainder);
        }

        return base64_decode(strtr($value, '-_', '+/')) ?: '';
    }
}
