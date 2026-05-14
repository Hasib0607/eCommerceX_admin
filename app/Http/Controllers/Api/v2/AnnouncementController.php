<?php

namespace App\Http\Controllers\Api\v2;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\Store;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnnouncementController extends Controller
{

    /**
     *
     * Get All Announcement
     *
     * @param $store
     * @return JsonResponse
     */
    public function index($store)
    {
        try {
            if (isset($store) && !empty($store)) {
                $isModulus = ModulusStatus($store, 117);

                if ($isModulus) {
                    // Retrieve paginated blogs, manipulate images, and return JSON response
                    $announcement = Announcement::where('store_id', $store)->where('status', 1)->get();

                    if (is_null($announcement)) {
                        // Return a 404 response if blog not found
                        return sendError("No announcement found.", '', 404);
                    }

                    return sendResponse("Success", $announcement);
                }

                return sendError("You have to active this modulus first.", '', 200);
            }

            return sendError("No store found.", '', 404);

        } catch (\Exception $e) {
            // Return error response in case of exception
            return serverError();
        }
    }

    public function getStoreByURL($name = "")
    {
        $store = Store::where('url', $name)->where('expiry_date', '>=', Carbon::now())->first();
        return $store->id ?? "";
    }

}
