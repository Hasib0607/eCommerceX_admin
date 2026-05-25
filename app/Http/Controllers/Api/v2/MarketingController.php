<?php

namespace App\Http\Controllers\Api\v2;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Services\TrackingCredentialService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;

class MarketingController extends Controller
{
    /**
     * Get Marketing Modules Status (Facebook Pixel + Google Analytics)
     *
     * @param mixed $store (store ID)
     * @return JsonResponse
     */
    public function index($store): JsonResponse
    {
        try {
            if (isset($store) && !empty($store)) {

                // ✅ Same style as AnnouncementController: ModulusStatus(store_id, modulus_id)
                $facebookPixelStatus = (bool) ModulusStatus($store, 11); // FB Pixel
                $googleAnalyticsStatus = (bool) ModulusStatus($store, 10); // Google Analytics

                return response()->json([
                    "status"  => true,
                    "message" => "Success",
                    "data"    => [
                        "facebook_pixel"   => $facebookPixelStatus,
                        "google_analytics" => $googleAnalyticsStatus,
                    ],
                ], 200);
            }

            return sendError("No store found.", '', 404);

        } catch (\Exception $e) {
            return serverError();
        }
    }

    /**
     * If you ever need store id by URL (kept same as your AnnouncementController)
     */
    public function getStoreByURL($name = "")
    {
        $store = Store::where('url', $name)->where('expiry_date', '>=', Carbon::now())->first();
        return $store->id ?? "";
    }

    public function trackMetaConversion(Request $request, $store): JsonResponse
    {
        try {
            if (empty($store) || !ModulusStatus($store, 11)) {
                return response()->json(['status' => false, 'message' => 'Facebook Pixel is disabled.']);
            }

            $validator = Validator::make($request->all(), [
                'event_name' => ['required', 'string', 'max:100'],
                'event_id' => ['nullable', 'string', 'max:255'],
                'event_source_url' => ['nullable', 'string', 'max:2048'],
                'user_data' => ['nullable', 'array'],
                'custom_data' => ['nullable', 'array'],
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Invalid conversion payload.',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $validated = $validator->validated();

            $credential = TrackingCredentialService::facebookForStore($store);
            $pixelId = $credential['pixel_id'] ?? '';
            $accessToken = $credential['access_token'] ?? '';

            if ($pixelId === '' || $accessToken === '') {
                return response()->json(['status' => false, 'message' => 'Facebook conversion credentials are missing.']);
            }

            $event = [
                'event_name' => $validated['event_name'],
                'event_time' => time(),
                'action_source' => 'website',
                'event_source_url' => $validated['event_source_url'] ?? $request->headers->get('referer'),
                'user_data' => array_filter([
                    'client_ip_address' => $validated['user_data']['client_ip_address'] ?? $request->ip(),
                    'client_user_agent' => $validated['user_data']['client_user_agent'] ?? $request->userAgent(),
                ]),
                'custom_data' => $validated['custom_data'] ?? [],
            ];

            if (!empty($validated['event_id'])) {
                $event['event_id'] = $validated['event_id'];
            }

            $payload = ['data' => [$event]];
            $testEventCode = $credential['test_event_code'] ?? '';
            if ($testEventCode !== '') {
                $payload['test_event_code'] = $testEventCode;
            }

            $response = Http::timeout(5)
                ->asJson()
                ->post(
                    'https://graph.facebook.com/v18.0/' . rawurlencode($pixelId) . '/events?access_token=' . rawurlencode($accessToken),
                    $payload
                );

            return response()->json([
                'status' => $response->successful(),
                'message' => $response->successful() ? 'Success' : 'Facebook conversion request failed.',
                'data' => $response->json(),
            ], $response->status());
        } catch (\Throwable $exception) {
            report($exception);

            return serverError();
        }
    }
}
