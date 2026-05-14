<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Read-only (plus registration) JSON for the marketing / landing site (e.g. Vite on :5173).
 * All routes live under /react-admin-api/public/landing/* and are guest-accessible.
 */
class LandingPublicApiController extends Controller
{
    public function __construct(
        protected AdminReactController $adminReact,
    ) {
    }

    /**
     * Single entry for registration OTP flow (session + cookies; use credentials: "include" from the browser).
     *
     * step=request_otp — same body as POST /react-admin-api/register/request-otp
     * step=verify_otp — same body as POST /react-admin-api/register/verify-otp (creates user, logs in, returns user JSON)
     */
    public function register(Request $request): JsonResponse
    {
        $step = (string) $request->input('step', '');

        return match ($step) {
            'request_otp' => $this->adminReact->registerRequestOtp($request),
            'verify_otp' => $this->adminReact->registerVerifyOtp($request),
            default => response()->json([
                'message' => 'Provide JSON field "step": "request_otp" or "verify_otp".',
                'steps' => [
                    'request_otp' => [
                        'step' => 'request_otp',
                        'name' => 'string',
                        'phone' => 'string (E.164-ish)',
                        'email' => 'optional email',
                        'password' => 'min 8',
                        'password_confirmation' => 'must match password',
                        'user_type' => 'admin|dropshipper',
                    ],
                    'verify_otp' => [
                        'step' => 'verify_otp',
                        'otp' => '6 digits (after OTP SMS, same browser session)',
                    ],
                ],
            ], 422),
        };
    }

    public function registerResendOtp(Request $request): JsonResponse
    {
        return $this->adminReact->registerResendOtp($request);
    }

    /**
     * Active subscription packages for pricing tables (no login). Default currency BDT; pass ?currency=USD if you map it later.
     */
    public function plans(Request $request): JsonResponse
    {
        $currencyCode = strtoupper(trim((string) $request->query('currency', 'BDT')));

        $plansQuery = Plan::query()
            ->with('details')
            ->where('status', 'active')
            ->orderByRaw('CASE WHEN position IS NULL THEN 1 ELSE 0 END ASC')
            ->orderBy('position', 'ASC')
            ->orderBy('id', 'ASC')
            ->whereNotIn('id', [8, 9]);

        $packages = $plansQuery->get()->map(function (Plan $plan) use ($currencyCode) {
            $featureSource = $plan->details;
            $activeFeatures = $featureSource->where('status', true)->pluck('title')->filter();
            $featureTitles = ($activeFeatures->count() > 0 ? $activeFeatures : $featureSource->pluck('title'))
                ->map(fn ($title) => trim((string) $title))
                ->filter()
                ->values();

            $quickFacts = collect([
                !empty($plan->category) ? ((int) $plan->category) . ' categories' : null,
                !empty($plan->product) ? ((int) $plan->product) . ' products' : null,
                !empty($plan->staff) ? ((int) $plan->staff) . ' staffs' : null,
                !empty($plan->order) ? ((int) $plan->order) . ' orders' : null,
            ])->filter()->values();

            $monthlyPrice = (float) ($plan->price ?? 0);
            $yearlyBase = $monthlyPrice * 12;
            $yearlyDiscount = (float) ($plan->twelvedis ?? 0);
            $discountType = strtolower(trim((string) ($plan->discount_type ?? '')));
            $yearlyPrice = $yearlyBase;
            if ($yearlyDiscount > 0) {
                if (in_array($discountType, ['percent', 'percentage'], true)) {
                    $yearlyPrice = max(0, $yearlyBase - (($yearlyBase * $yearlyDiscount) / 100));
                } else {
                    $yearlyPrice = max(0, $yearlyBase - $yearlyDiscount);
                }
            }
            $yearlySaveText = '';
            if ($yearlyBase > 0 && $yearlyPrice < $yearlyBase) {
                $yearlySaveText = 'Save ' . $currencyCode . ' ' . number_format($yearlyBase - $yearlyPrice, 0);
            }

            return [
                'id' => (int) $plan->id,
                'name' => (string) ($plan->name ?? ''),
                'subtitle' => (string) ($plan->subtitle ?? ''),
                'currency_code' => $currencyCode,
                'price_monthly' => $monthlyPrice,
                'price_yearly' => (float) $yearlyPrice,
                'yearly_save_text' => $yearlySaveText,
                'price_label' => $monthlyPrice > 0
                    ? $currencyCode . ' ' . number_format($monthlyPrice, 0) . ' / month'
                    : 'Contact sales',
                'best_for' => !empty($plan->subtitle) ? (string) $plan->subtitle : 'Best for growing stores',
                'quick_facts' => $quickFacts->all(),
                'features' => $featureTitles->all(),
            ];
        })->values();

        return response()->json([
            'currency_code' => $currencyCode,
            'packages' => $packages,
        ]);
    }

    /**
     * Panel branding colours/logos plus AI store style presets (same payloads as public branding + store create AI meta).
     */
    public function theme(Request $request): JsonResponse
    {
        $branding = $this->adminReact->publicBranding($request);
        $aiMeta = $this->adminReact->storeCreateAiMeta($request);

        return response()->json([
            'branding' => $branding->getData(true),
            'store_ai_meta' => $aiMeta->getData(true),
        ]);
    }

    /**
     * Live client showcase links for the landing page.
     * Proxies to WHATSAPP_BOT_API_URL + WHATSAPP_BOT_LIVE_CLIENTS_PATH when configured (same data source as the WhatsApp admin UI).
     */
    public function liveClientShowcases(Request $request): JsonResponse
    {
        $base = rtrim((string) config('whatsapp_automation.bot_api_url', ''), '/');
        $token = (string) config('whatsapp_automation.bot_admin_token', '');
        $path = trim((string) config('whatsapp_automation.live_clients_path', 'live-client-showcases'), '/');

        if ($base === '') {
            return response()->json([
                'items' => [],
                'showcases' => [],
                'meta' => [
                    'source' => 'unconfigured',
                    'hint' => 'Set WHATSAPP_BOT_API_URL (and WHATSAPP_BOT_ADMIN_TOKEN if required) to proxy live client data from the automation service.',
                ],
            ]);
        }

        $url = $base . '/' . $path;
        $query = array_filter([
            'limit' => (int) $request->query('limit', 50),
            'is_active' => $request->query('is_active', '1'),
        ], static fn ($v) => $v !== null && $v !== '');

        try {
            $pending = Http::timeout(12)->acceptJson();
            if ($token !== '') {
                $pending = $pending->withHeaders([
                    'Authorization' => 'Bearer ' . $token,
                    'X-Admin-Token' => $token,
                ]);
            }
            $resp = $pending->get($url, $query);

            if (!$resp->successful()) {
                return response()->json([
                    'items' => [],
                    'showcases' => [],
                    'meta' => [
                        'source' => 'proxy_error',
                        'upstream_status' => $resp->status(),
                        'upstream_body' => Str::limit($resp->body(), 500),
                    ],
                ]);
            }

            $json = $resp->json();
            if (!is_array($json)) {
                return response()->json([
                    'items' => [],
                    'showcases' => [],
                    'meta' => ['source' => 'proxy_invalid_json'],
                ]);
            }

            return response()->json($json);
        } catch (\Throwable $e) {
            return response()->json([
                'items' => [],
                'showcases' => [],
                'meta' => [
                    'source' => 'proxy_exception',
                    'message' => $e->getMessage(),
                ],
            ]);
        }
    }
}
