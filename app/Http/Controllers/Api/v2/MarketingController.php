<?php

namespace App\Http\Controllers\Api\v2;

use App\Http\Controllers\Controller;
use App\Models\Store;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;

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
}