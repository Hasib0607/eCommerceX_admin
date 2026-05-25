<?php

namespace App\Http\Controllers\Api\v2;

use App\Http\Controllers\Controller;
use App\Http\Resources\CustomDesignResource;
use App\Http\Resources\LayoutDesignResource;
use App\Models\Headersetting;
use App\Models\Product;
use App\Models\QuickLogin;
use App\Models\Store;
use App\Models\StoreDesign;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;

class ThemeController extends Controller
{

    public function headerSettings($name)
    {
        try {
            if (empty($name) || is_null($name)) {
                return response()->json(['status' => false, 'message' => 'Domain name is required!.']);
            }

            $store = Store::with('current_currency')
                ->where('url', $name)
                ->where('expiry_date', '>=', Carbon::now())
                ->first();

            if (!$store) {
                return response()->json(['status' => false, 'message' => 'Store not found!.']);
            }

            $header_setting = Headersetting::convertCurrency($store->id)->first();

            if (!$header_setting) {
                return response()->json(['status' => false, 'message' => 'No header settings found']);
            }

            $quickLogin = QuickLogin::where('store_id', $store->id)
                ->whereIn('modulus_id', [10, 11])
                ->get()
                ->keyBy('modulus_id');

            $header_setting->gtm = $quickLogin[10]->google_tag_manager ?? null;
            $header_setting->google_analytics = $quickLogin[10]->google_analytics ?? null;
            $header_setting->google_search_console = $quickLogin[10]->google_search_console ?? null;
            $header_setting->facebook_pixel = $quickLogin[11]->facebook_pixel ?? null;
            $header_setting->domain_verification_code = $quickLogin[11]->domain_verification_code ?? null;

            $header_setting->currency = $store->current_currency;
            $header_setting->total_sms = Cache::remember("sms_count_{$store->id}", 300, fn() => getSmsCount($store->id));
            $header_setting->allowOrder = Cache::remember("order_limit_{$store->id}", 300, fn() => checkOrderLimit($store->id));

            $header_setting->makeHidden([
                'shipping_area_1',
                'shipping_area_1_cost',
                'shipping_area_2',
                'shipping_area_2_cost',
                'shipping_area_3',
                'shipping_area_3_cost',
            ]);


            if (!is_null($header_setting->shipping_methods) && $header_setting->shipping_methods) {

                if (is_array($header_setting->shipping_methods)) {
                    $header_setting->shipping_methods = json_encode($header_setting->shipping_methods);
                } elseif (is_string($header_setting->shipping_methods)) {
                    $header_setting->shipping_methods = $header_setting->shipping_methods;
                } else {
                    $header_setting->shipping_methods = json_encode([]);
                }
            } else {
                $header_setting->shipping_methods = json_encode([]);
            }

            $header_setting->favicon = getPath($header_setting->favicon, 'assets/images/setting');
            $header_setting->logo = getPath($header_setting->logo, 'assets/images/setting');

            $designs = StoreDesign::select([
                'id', 'title', 'title_color', 'subtitle', 'subtitle_color', 'button',
                'button_color', 'button_bg_color', 'button1', 'button1_color', 'button1_bg_color',
                'link', 'bg_image', 'image_description', 'is_buy_now_cart', 'is_buy_now_cart1', 'type'
            ])
                ->where('store_id', $store->id)
                ->get();

            $header_setting->custom_design = CustomDesignResource::collection($designs)->groupBy('type');

            $header_setting->amarpay = merchantPaymentStatus($store->id, 125, "amarpay", $header_setting->amarpay);
            $header_setting->merchant_bkash = merchantPaymentStatus($store->id, 128, "bkash", $header_setting->merchant_bkash);
            $header_setting->merchant_nagad = merchantPaymentStatus($store->id, 129, "nagad", $header_setting->merchant_nagad);
            $header_setting->merchant_rocket = merchantPaymentStatus($store->id, 130, "rocket", $header_setting->merchant_rocket);

            return response()->json(['status' => true, 'message' => 'success', 'data' => $header_setting]);
        } catch (\Exception $exception) {
            return serverError();
        }
    }


    public function layoutProducts(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required',
            'id' => 'required',
        ], [
            'name.required' => "Name is required",
            'id.required' => "ID is required",
        ]);
        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()]);
        }

        $name = $validator->validate()['name'];
        $store = Store::where('url', $name)
            ->where('expiry_date', '>=', Carbon::now())
            ->first();
        if (!isset($store)) {
            return response()->json(['error' => 'your account not found or expired'], 404);
        }

        $customizable = ModulusStatus($store->id, 121);
        if (!$customizable) {
            return response()->json(['error' => 'Access Denied'], 400);
        }
        $product = Product::with(['layout.design'])->convertCurrency($store->id)->where('products.id', $request->id)->first();
        if (!isset($product)) {
            return response()->json(['error' => 'product not found'], 404);
        }

        $final_product = new LayoutDesignResource($product);
        $final_product_json = $final_product->toJson();
        $final_product = json_decode($final_product_json, true);

        return response()->json(['success' => 'Get data with layout successfully', 'product' => $final_product]);
    }

    public function getProductForLayout($name)
    {
        $store = Store::where('url', $name)
            ->where('expiry_date', '>=', Carbon::now())
            ->first();
        if (!isset($store)) {
            return response()->json(['error' => 'your account not found or expired'], 404);
        }
        $customizable = ModulusStatus($store->id, 121);
        if (!$customizable) {
            return response()->json(['error' => 'Access Denied'], 400);
        }

        $products = Product::convertCurrency($store->id)
            ->distinct()
            ->join('product_layouts as layout', 'layout.product_id', '=', 'products.id')
            ->get();
        if (!isset($products)) {
            return response()->json(['error' => 'products not found'], 404);
        }
        return response()->json(['success' => 'Get data with layout successfully', 'products' => $products]);
    }
}
