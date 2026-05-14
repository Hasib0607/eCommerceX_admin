<?php

namespace App\Http\Controllers\Courier;

use App\Models\Store;
use GuzzleHttp\Client;
use App\Http\Controllers\Controller;
use App\Models\Courier;
use App\Models\CourierDelivery;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;
use Codeboxr\PathaoCourier\Facade\PathaoCourier;
use SteadFast\SteadFastCourierLaravelPackage\Facades\SteadfastCourier;
use Codeboxr\EcourierCourier\Facade\Ecourier;
use Illuminate\Support\Facades\Http;
use Codeboxr\RedxCourier\Facade\RedxCourier;

class CourierController extends Controller
{

    /**
     * Create a new AuthController instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth')->except("getCourierList");
    }

    /***
     *
     * Show courier index page
     *
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View
     */
    public function index()
    {
        $userData = getUserData();
        $store_id = $userData['store_id'] ?? "";

        $couriers = CourierDelivery::where('store_id', $store_id)->get();

        return view("admin.courier.index", ["couriers" => $couriers]);
    }


    /**
     *
     * Show all courier page
     *
     * @param $name
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View|\Illuminate\Http\RedirectResponse
     */
    public function courierPage($name)
    {
        if (empty($name) || !isset($name)) {
            Session::flash("error", "Invalid request!");
            return back();
        }

        return view("admin.courier." . $name . ".index", ["courier" => $this->getCourierData($name)]);
    }

    /**
     *
     *  Get courier data
     *
     * @param $name
     * @return mixed
     */
    public function getCourierData($name)
    {
        $userData = getUserData();
        $user_id = $userData["user_id"];
        $store_id = $userData["store_id"];

        return Courier::where('user_id', $user_id)->where('store_id', $store_id)->where('courier_name', $name)->first();
    }

    /**
     *
     * Store courier data
     *
     * @param Request $request
     * @param $name
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View|\Illuminate\Http\RedirectResponse
     */
    public function courierStore(Request $request, $name)
    {
        try {
            // Validated all input
            $validator = $this->validateFormRequest($request, $name);

            if ($name == "pathao") {
                if (empty($request->courier_store_id) || !isset($request->courier_store_id)) {
                    $validator->getMessageBag()->add("courier_store_id", "Store ID is required!");
                }
            }

            // Check validation fails or pass
            if ($validator->fails()) {
                return redirect()->back()
                    ->withErrors($validator)
                    ->withInput();
            } else {
                $userData = getUserData();
                $user_id = $userData["user_id"];
                $store_id = $userData["store_id"];

                $courier = Courier::where('user_id', $user_id)->where('store_id', $store_id)->where('courier_name', $name)->first();
                if (!isset($courier)) {
                    $courier = new Courier();
                }

                $courier->courier_name = $name;
                $courier->courier_store_id = $request->courier_store_id ?? null;
                $courier->username = $request->username;
                $courier->password = $request->password;
                $courier->api_key = $request->api_key ?? null;
                $courier->api_secret = $request->api_secret ?? null;
                $courier->access_token = $request->access_token ?? null;
                $courier->store_id = $store_id;
                $courier->user_id = $user_id;
                $courier->status = isset($request->status) && $request->status == "on" ? 1 : 0;
                $courier->save();

                Session::flash("success", "Credentials save successfully!");
                return back();
            }


        } catch (\Exception $e) {
            return view('error');
        }

    }

    /**
     *
     * Input validate function
     *
     * @param $request
     * @param $name
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Validation\Validator
     */
    public function validateFormRequest($request, $name)
    {
        if (empty($name) || empty($request->name)) {
            Session::flash("error", "Invalid request");
            return back();
        } elseif ($name != $request->name) {
            Session::flash("error", "Invalid request");
            return back();
        }

        switch ($name) {
            case "pathao":
                $keyName = "Client ID";
                $secretName = "Client Secret";
                break;
            case "steadfast":
                $keyName = "API Key";
                $secretName = "Secret Key";
                break;
//            case "ecourier":
//                $keyName = "API Key";
//                $secretName = "API Secret";
//                break;
            case "redx":
                $keyName = "Key";
                $secretName = "Secret";
                break;
            default:
                $keyName = "API Key";
                $secretName = "API Secret";
        }

        if ($name != "redx") {
            // Input validation rules
            $rules = array(
                'courier_store_id' => 'nullable|string',
                'username' => 'nullable|string',
                'password' => 'nullable|string',
                'api_key' => 'required|string',
                'api_secret' => 'required|string',
            );

            // Input vaidation message
            $errorMessage = array(
                "courier_store_id.string" => "Store ID must be a string.",
                "username.string" => "Username must be a string.",
                "password.string" => "Password must be a string.",
                "api_key.required" => "$keyName is required.",
                'api_key.string' => "$keyName must be a string.",
                'api_secret.required' => "$secretName is required.",
                'api_secret.string' => "$secretName must be a string.",
            );
        } else {
            // Input validation rules
            $rules = array(
                'access_token' => 'required|string',
            );

            // Input vaidation message
            $errorMessage = array(
                'access_token.required' => "Access Token is required.",
                'access_token.string' => "Access Token must be a string.",
            );
        }

        return Validator::make($request->all(), $rules, $errorMessage);
    }

    /**
     *
     * Create pathao new parcel
     *
     * @param Request $request
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View|\Illuminate\Http\RedirectResponse
     */
    public function createPathaoOrder(Request $request)
    {
        try {
            $rules = array(
                'recipient_name' => 'required|string',
                'recipient_phone' => 'required|string',
                'recipient_address' => 'required|string|min:10',
                'item_description' => 'nullable|string',
                'recipient_city' => 'required|numeric',
                'recipient_zone' => 'required|numeric',
                'recipient_area' => 'required|numeric',
                'item_weight' => 'required|numeric|min:0.001',
                'special_instruction' => 'nullable|string',
            );

            // Input vaidation message
            $errorMessage = array(
                "recipient_name.required" => "Recipient name is required.",
                "recipient_name.string" => "Recipient name must be a string.",
                "recipient_phone.required" => "Recipient phone is required.",
                "recipient_phone.string" => "Recipient phone must be a string.",
                "recipient_address.required" => "Recipient address is required.",
                "recipient_address.string" => "Recipient address must be a string.",
                "recipient_address.min" => "The recipient address must be at least 10 characters.",
                "item_description.string" => "Item Description & Price must be a string.",
                "recipient_city.required" => "City is required.",
                "recipient_city.numeric" => "City must be a number.",
                "recipient_zone.required" => "Zone is required.",
                "recipient_zone.numeric" => "Zone must be a number.",
                "recipient_area.required" => "Area is required.",
                "recipient_area.numeric" => "Area must be a number.",
                "item_weight.required" => "Item weight is required.",
                "item_weight.numeric" => "Item weight must be a number.",
                "item_weight.min" => "Item weight must be greater than 0.001.",
                "special_instruction.string" => "Special Instructions must be a string.",
            );

            $validator = Validator::make($request->all(), $rules, $errorMessage);

            if ($validator->fails()) {
                Session::flash("courier_oprn", "pathao");
                return redirect()->back()
                    ->withErrors($validator)
                    ->withInput();
            } else {
                if (empty($request->order_ids)) {
                    Session::flash("courier_oprn", "pathao");
                    Session::flash("error", "Order ID not found!");
                    return redirect()->back()->withInput();
                }

                // Set pathao courier config
                self::setCourierConfig("pathao");
                $pathao_store_id = config('pathao.store_id') ?? "";
                if (empty($pathao_store_id) || is_null($pathao_store_id)) {
                    Session::flash("courier_oprn", "pathao");
                    Session::flash("error", "Pathao store ID not found! Check your credentials.");
                    return redirect()->back()->withInput();
                }

                $pathao_store = null;
                $response = PathaoController::getStores(); // Get store list

                if (!$response['status']) {
                    Session::flash("courier_oprn", "pathao");
                    Session::flash("error", "Pathao store ID not found! Check your credentials.");
                    return redirect()->back()->withInput();
                }

                $stores = $response['data'] ?? [];

                if (isset($stores->data) && count($stores->data) > 0) {
                    foreach ($stores->data as $key => $store) {
                        $store = (array)$store;
                        if ($store['store_id'] == $pathao_store_id) {
                            $pathao_store = $store;
                        }
                    }
                }

                if (is_null($pathao_store)) {
                    Session::flash("courier_oprn", "pathao");
                    Session::flash("error", "Pathao store not found! Check your credentials.");
                    return redirect()->back()->withInput();
                }

                $orderIDs = explode(",", $request->order_ids);

                foreach ($orderIDs as $orderID) {
                    $order = DB::table('orders')
                        ->leftJoin('orderitems', 'orderitems.order_id', '=', 'orders.id')
                        ->where('orders.id', $orderID)
                        ->groupBy('orders.id') // Group by orders.id to aggregate quantities correctly
                        ->select('orders.*', DB::raw('SUM(orderitems.quantity) as total_quantity'))
                        ->first();

                    $amount_to_collect = $order->due;
                    if ($order->due == 0) {
                        $amount_to_collect = 1;
                    }

                    $courierData = [
                        "store_id" => $pathao_store_id, // Find in store list,
                        "merchant_order_id" => $order->reference_no, // Unique order id
                        "recipient_name" => $request->recipient_name, // Customer name
                        "recipient_phone" => $request->recipient_phone, // Customer phone
                        "recipient_address" => $request->recipient_address, // Customer address
                        "recipient_city" => $request->recipient_city, // Find in city method
                        "recipient_zone" => $request->recipient_zone, // Find in zone method
                        "recipient_area" => $request->recipient_area, // Find in Area method
                        "delivery_type" => "48", // 48 for normal delivery or 12 for on demand delivery
                        "item_type" => "2", // 1 for document,2 for parcel
                        "special_instruction" => $request->special_instruction,
                        "item_quantity" => $order->total_quantity, // item quantity
                        "item_weight" => $request->item_weight, // parcel weight
                        "amount_to_collect" => $amount_to_collect, // amount to collect
                        "item_description" => $request->item_description // product details
                    ];

                    $responseData = PathaoController::createOrder($courierData); // Get store list

                    if (!$responseData['status']) {
                        Session::flash("courier_oprn", "pathao");
                        Session::flash("error", "Something went wrong. Try again!");
                        return redirect()->back()->withInput();
                    }

                    if (isset($responseData["data"])) {
                        $response = $responseData["data"];

                        $courierDelivery = new CourierDelivery();
                        $courierDelivery->courier_name = "pathao";
                        $courierDelivery->courier_store_id = $pathao_store_id;
                        $courierDelivery->consignment_id = $response['data']['consignment_id'];
                        $courierDelivery->merchant_order_id = $response['data']['merchant_order_id'];
                        $courierDelivery->store_id = $order->store_id;
                        $courierDelivery->delivery_status = $response['data']['order_status'];
                        $courierDelivery->delivery_fee = $response['data']['delivery_fee'];
                        $courierDelivery->save();

                        $this->orderStatusChange($orderID, "Shipping");
                    }
                }

                Session::flash("success", "Order Created Successfully!");
                return redirect()->back();
            }
        } catch (\Exception $e) {
            Session::flash("error", "Something went wrong. Try again!");
            return redirect()->back();
        }
    }

    /**
     *
     * Set Courier config data
     *
     * @param $courier
     * @return void
     */
    public static function setCourierConfig($courier)
    {
        $instance = new self();

        if ($courier == "pathao") {
            $data = $instance->getCourierData("pathao");

            Config::set('pathao.store_id', $data->courier_store_id ?? "");
            Config::set('pathao.client_id', $data->api_key ?? "");
            Config::set('pathao.client_secret', $data->api_secret ?? "");
            Config::set('pathao.username', $data->username ?? "");
            Config::set('pathao.password', $data->password ?? "");
        } elseif ($courier == "steadfast") {
            $data = $instance->getCourierData("steadfast");

            Config::set('steadfast-courier.api_key', $data->api_key ?? "");
            Config::set('steadfast-courier.secret_key', $data->api_secret ?? "");
        } elseif ($courier == "redx") {
            $data = $instance->getCourierData("redx");

            Config::set('redx.access_token', $data->access_token ?? "");

            // optional (keep false for production)
            Config::set('redx.sandbox', false);
        }
    }

    /**
     *
     * Change order delivery status
     *
     * @param $orderID
     * @param $status
     * @return void
     */
    public function orderStatusChange($orderID, $status = "Shipping")
    {
        $order = Order::where('id', $orderID)->first();
        if (isset($order)) {
            $order->status = $status;
            $order->save();
        }
    }


    /**
     *
     * Get pathao zone
     *
     * @param $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function getPathaoZone($id)
    {
        if (empty($id)) {
            return response()->json(['status' => false, "message" => "City ID not found!", "data" => []]);
        }

        $zone = PathaoCourier::area()->zone($id);

        $options = '<option value="">Select zone</option>';
        if ($zone->data) {
            foreach ($zone->data as $key => $zone) {
                $options .= '<option value="' . $zone->zone_id . '">' . $zone->zone_name . '</option>';
            }
        }

        return response()->json(['status' => true, "message" => "Successful!", "data" => $options]);
    }

    /**
     *  Get pathao area
     *
     * @param $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function getPathaoArea($id)
    {
        if (empty($id)) {
            return response()->json(['status' => false, "message" => "Zone ID not found!", "data" => []]);
        }

        $area = PathaoCourier::area()->area($id);

        $options = '<option value="">Select area</option>';
        if ($area->data) {
            foreach ($area->data as $key => $area) {
                $options .= '<option value="' . $area->area_id . '">' . $area->area_name . '</option>';
            }
        }

        return response()->json(['status' => true, "message" => "Successful!", "data" => $options]);
    }

    /**
     * Create steadfast new parcel
     *
     * @param Request $request
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View|\Illuminate\Http\RedirectResponse
     */
    public function createSteadfastOrder(Request $request)
    {
        try {
            self::setCourierConfig("steadfast");

            if (empty($request->order_ids)) {
                Session::flash("error", "Order ID not found!");
                return redirect()->back();
            }

            $orderIDs = explode(",", $request->order_ids);

            foreach ($orderIDs as $orderID) {
                $order = DB::table('orders')
                    ->leftJoin('orderitems', 'orderitems.order_id', '=', 'orders.id')
                    ->where('orders.id', $orderID)
                    ->groupBy('orders.id') // Group by orders.id to aggregate quantities correctly
                    ->select('orders.*', DB::raw('SUM(orderitems.quantity) as total_quantity'))
                    ->first();

                $orderData = [
                    'invoice' => $order->reference_no,
                    'recipient_name' => $order->name,
                    'recipient_phone' => $order->phone,
                    // ✅ Use updated address if available, otherwise fallback to original address
                    'recipient_address' => !empty($order->edited_address) ? $order->edited_address : $order->address,
                    'cod_amount' => $order->due,
                    'note' => $order->description
                ];

                $response = SteadfastCourier::placeOrder($orderData);

                if (isset($response['status']) && $response['status'] == 400) {
                    if (isset($response['status']['errors']['invoice'])) {
                        Session::flash("error", $response['status']['errors']['invoice'][0]);
                        return redirect()->back();
                    }
                    Session::flash("error", "Something went wrong. Try again!");
                    return redirect()->back();
                } elseif (isset($response['status']) && $response['status'] != 200) {
                    Session::flash("error", "Something went wrong. Try again!");
                    return redirect()->back();
                } elseif (is_null($response)) {
                    Session::flash("error", "The courier connection failed. Check your courier credentials!");
                    return redirect()->back();
                }

                $courierDelivery = new CourierDelivery();
                $courierDelivery->courier_name = "steadfast";
                $courierDelivery->consignment_id = $response['consignment']['consignment_id'];
                $courierDelivery->tracking_code = $response['consignment']['tracking_code'];
                $courierDelivery->merchant_order_id = $response['consignment']['invoice'];
                $courierDelivery->store_id = $order->store_id;
                $courierDelivery->delivery_status = $response['consignment']['status'];
                $courierDelivery->save();

                $this->orderStatusChange($orderID, "Shipping");

            }

            Session::flash("success", "Order Created Successfully!");
            return redirect()->back();

        } catch (\Exception $e) {
            return view('error');
        }
    }

    /**
     *
     * Create eCourie new parcel
     *
     * @return void
     */
    public function createEcourierOrder(Request $request)
    {
//        dd($request->all());
//        dd("eCourie");
    }

    /**
     *
     * Get Ecourier Thana
     *
     * @param $cityName
     * @return \Illuminate\Http\JsonResponse
     */
    public function getEcourierZone($cityName)
    {
        if (empty($cityName)) {
            return response()->json(['status' => false, "message" => "City not found!", "data" => []]);
        }

        $thana = Ecourier::area()->thana($cityName);

        dd($thana);

        $options = '<option value="">Select thana</option>';
        if ($thana->data) {
            foreach ($thana->data as $key => $thana) {
                $options .= '<option value="' . $thana->zone_id . '">' . $thana->zone_name . '</option>';
            }
        }

        return response()->json(['status' => true, "message" => "Successful!", "data" => $options]);
    }

    /**
     *  Get Ecourier Postcode
     *
     * @param $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function getEcourierPostcode($cityName, $thanaName)
    {
        if (empty($cityName)) {
            return response()->json(['status' => false, "message" => "Thana not found!", "data" => []]);
        } elseif (empty($thanaName)) {
            return response()->json(['status' => false, "message" => "Thana not found!", "data" => []]);
        }

        $postcode = Ecourier::area()->postcode($cityName, $thanaName);

        dd($postcode);

        $options = '<option value="">Select area</option>';
        if ($postcode->data) {
            foreach ($postcode->data as $key => $post) {
                $options .= '<option value="' . $post->area_id . '">' . $post->area_name . '</option>';
            }
        }

        return response()->json(['status' => true, "message" => "Successful!", "data" => $options]);
    }


    /**
     *  Get Ecourier Area
     *
     * @param $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function getEcourierArea($postcode)
    {
        if (empty($postcode)) {
            return response()->json(['status' => false, "message" => "Post code not found!", "data" => []]);
        }

        $areaList = Ecourier::area()->areaList($postcode);

        dd($areaList);

        $options = '<option value="">Select area</option>';
        if ($areaList) {
            foreach ($areaList as $key => $area) {
                $options .= '<option value="' . $area->area_id . '">' . $area->area_name . '</option>';
            }
        }

        return response()->json(['status' => true, "message" => "Successful!", "data" => $options]);
    }


/**
 *
 * Create RedX new parcel
 *
 * @param Request $request
 * @return \Illuminate\Http\RedirectResponse
 */
/**
 *
 * Create RedX new parcel
 *
 * This method creates one or multiple RedX parcel orders
 * from selected eBitans order IDs and stores the returned
 * tracking information in the courier_deliveries table.
 *
 * @param Request $request
 * @return \Illuminate\Http\RedirectResponse
 */
public function createRedxOrder(Request $request)
{
    try {
        /**
         * ------------------------------------------------------------------
         * Step 1: Validate required RedX request fields
         * ------------------------------------------------------------------
         */
        $validator = Validator::make($request->all(), [
            'delivery_area'    => 'required|string',
            'delivery_area_id' => 'required|numeric',
            'parcel_weight'    => 'required|numeric|min:0.001',
            'pickup_store_id'  => 'required|numeric',
            'instruction'      => 'nullable|string',
            'value'            => 'nullable|numeric|min:0',
        ], [
            'delivery_area.required'    => 'Delivery area is required.',
            'delivery_area.string'      => 'Delivery area must be a string.',
            'delivery_area_id.required' => 'Delivery area ID is required.',
            'delivery_area_id.numeric'  => 'Delivery area ID must be numeric.',
            'parcel_weight.required'    => 'Parcel weight is required.',
            'parcel_weight.numeric'     => 'Parcel weight must be numeric.',
            'parcel_weight.min'         => 'Parcel weight must be greater than 0.',
            'pickup_store_id.required'  => 'Pickup store ID is required.',
            'pickup_store_id.numeric'   => 'Pickup store ID must be numeric.',
            'instruction.string'        => 'Instruction must be a string.',
            'value.numeric'             => 'Value must be numeric.',
            'value.min'                 => 'Value cannot be negative.',
        ]);

        if ($validator->fails()) {
            Session::flash("courier_oprn", "redx");

            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        /**
         * ------------------------------------------------------------------
         * Step 2: Check whether order IDs were passed
         * ------------------------------------------------------------------
         */
        if (empty($request->order_ids)) {
            Session::flash("courier_oprn", "redx");
            Session::flash("error", "Order ID not found!");
            return redirect()->back()->withInput();
        }

        /**
         * ------------------------------------------------------------------
         * Step 3: Load RedX config dynamically from database
         * ------------------------------------------------------------------
         */
        self::setCourierConfig("redx");

        /**
         * ------------------------------------------------------------------
         * Step 4: Split selected order IDs
         * ------------------------------------------------------------------
         */
        $orderIDs = explode(",", $request->order_ids);

        foreach ($orderIDs as $orderID) {
            $orderID = trim($orderID);

            if (empty($orderID)) {
                continue;
            }

            /**
             * --------------------------------------------------------------
             * Fetch order with total item quantity
             * --------------------------------------------------------------
             */
            $order = DB::table('orders')
                ->leftJoin('orderitems', 'orderitems.order_id', '=', 'orders.id')
                ->where('orders.id', $orderID)
                ->groupBy('orders.id')
                ->select('orders.*', DB::raw('SUM(orderitems.quantity) as total_quantity'))
                ->first();

            if (!$order) {
                Session::flash("courier_oprn", "redx");
                Session::flash("error", "Order not found!");
                return redirect()->back()->withInput();
            }

            /**
             * --------------------------------------------------------------
             * Prevent duplicate RedX parcel creation for same order
             * --------------------------------------------------------------
             */
            $existingCourierOrder = CourierDelivery::where('courier_name', 'redx')
                ->where('merchant_order_id', $order->reference_no)
                ->first();

            if ($existingCourierOrder) {
                Session::flash("courier_oprn", "redx");
                Session::flash("error", "RedX parcel already created for order invoice: " . $order->reference_no);
                return redirect()->back()->withInput();
            }

            /**
             * --------------------------------------------------------------
             * Use edited address if available, otherwise fallback to original
             * --------------------------------------------------------------
             */
            $customerAddress = !empty($order->edited_address)
                ? $order->edited_address
                : $order->address;

            /**
             * --------------------------------------------------------------
             * RedX payload
             *
             * Important:
             * parcel_details_json must be an array of objects
             * --------------------------------------------------------------
             */
            $payload = [
                "customer_name"          => $order->name,
                "customer_phone"         => $order->phone,
                "delivery_area"          => $request->delivery_area,
                "delivery_area_id"       => (int) $request->delivery_area_id,
                "customer_address"       => $customerAddress,
                "merchant_invoice_id"    => $order->reference_no,
                "cash_collection_amount" => (float) $order->due,
                "parcel_weight"          => (float) $request->parcel_weight, // weight in gram
                "instruction"            => $request->instruction ?? "",
                "value"                  => !empty($request->value) ? (float) $request->value : (float) $order->due,
                "pickup_store_id"        => (int) $request->pickup_store_id,
                "parcel_details_json"    => [
                    [
                        "item_quantity"    => (int) ($order->total_quantity ?? 1),
                        "item_description" => $order->description ?? "",
                    ]
                ],
            ];

            /**
             * --------------------------------------------------------------
             * Create RedX parcel
             * --------------------------------------------------------------
             */
            $response = RedxCourier::order()->create($payload);

            if (!$response) {
                Session::flash("courier_oprn", "redx");
                Session::flash("error", "RedX connection failed!");
                return redirect()->back()->withInput();
            }

            /**
             * --------------------------------------------------------------
             * Convert response object to array for safer handling
             * --------------------------------------------------------------
             */
            $res = json_decode(json_encode($response), true);

            /**
             * --------------------------------------------------------------
             * Extract tracking ID
             * --------------------------------------------------------------
             */
            $trackingId = $res['tracking_id'] ?? null;

            if (empty($trackingId)) {
                Session::flash("courier_oprn", "redx");
                Session::flash("error", $res['message'] ?? "RedX order failed!");
                return redirect()->back()->withInput();
            }

            /**
             * --------------------------------------------------------------
             * Save courier delivery info
             * --------------------------------------------------------------
             */
            $courierDelivery = new CourierDelivery();
            $courierDelivery->courier_name = "redx";
            $courierDelivery->consignment_id = $trackingId;
            $courierDelivery->tracking_code = $trackingId;
            $courierDelivery->merchant_order_id = $order->reference_no;
            $courierDelivery->store_id = $order->store_id;
            $courierDelivery->delivery_status = "Parcel Created";
            $courierDelivery->save();

            /**
             * --------------------------------------------------------------
             * Update eBitans order status to Shipping
             * --------------------------------------------------------------
             */
            $this->orderStatusChange($orderID, "Shipping");
        }

        Session::flash("success", "RedX Order Created Successfully!");
        return redirect()->back();

    } catch (\Exception $e) {
        Session::flash("courier_oprn", "redx");
        Session::flash("error", "RedX error: " . $e->getMessage());
        return redirect()->back()->withInput();
    }
}


    public function getCourierList($store)
    {
        $storeEx = Store::where("id", $store)->first();

        if (!isset($storeEx)) {
            return sendError("Store not found!");
        }

        $couriers = Courier::where('store_id', $store)->select("courier_name")->get();

        return sendResponse("Success", $couriers);
    }


}
