<?php

namespace App\Http\Controllers\Api\v2;

use App\Http\Controllers\Controller;
use App\Models\BookingCustomerFiled;
use Illuminate\Http\Request;
use App\Models\BuyModulus;
use App\Models\Modulus;
use App\Models\Store;

class BookingController extends Controller
{
    public function index($store, $id = "")
    {
        try {
            if (empty($store) || is_null($store)) {
                return response()->json(['status' => false, 'message' => 'Store id is required']);
            }

            if (empty($id) || is_null($id)) {
                return response()->json(['status' => false, 'message' => 'Module ID is required']);
            }

            $is_module_active = BuyModulus::where('store_id', $store)->where('modulus_id', $id)->first();

            if (is_null($is_module_active)) {
                return sendError("The store or module not fund.");
            }

            if ($is_module_active->status == 0) {
                return sendError("Module not active");
            }

            $find_store_id = Store::where('id', $store)->first();
            if (empty($find_store_id)) {
                return sendError("Store not found.");
            }

            $module_is_find = Modulus::where('id', $id)->first();
            if (empty($module_is_find)) {
                return sendError("Module not found.");
            }

            $field = BookingCustomerFiled::selectRaw('booking_tags.name as field_name, booking_tags.type as type, CASE WHEN booking_customer_fields.is_required = 1 THEN "required" ELSE "optional" END as requirement_status')
                ->selectRaw('COALESCE(NULLIF(booking_customer_fields.name, ""), booking_tags.name) as c_name')
                ->leftJoin('booking_tags', function ($join) {
                    $join->on('booking_tags.id', '=', 'booking_customer_fields.tagId');
                })
                ->where('booking_customer_fields.store_id', '=', $is_module_active->store_id)
                ->where('booking_customer_fields.modulus_id', '=', $is_module_active->modulus_id)
                ->where('booking_customer_fields.is_checked', '=', 1)
                ->get();

            $fieldType = BookingCustomerFiled::selectRaw("CASE WHEN is_single = 1 THEN 'single' ELSE 'double' END as from_type")
                ->where("modulus_id", "=", $is_module_active->modulus_id)
                ->where("store_id", "=", $is_module_active->store_id)
                ->where("is_checked", "=", 1)
                ->first();

            if ($field->isEmpty()) {
                return sendError("Data not found");
            }

            $data = [
                'from_type' => $fieldType->from_type,
                'data' => $field
            ];

            return sendResponse("Success", $data);
        } catch (\Exception $e) {
            return serverError();
        }
    }
}
