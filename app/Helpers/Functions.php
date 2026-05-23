<?php

use App\Models\AdminVisitor;
use App\Models\BuyModulus;
use App\Models\ChatConversation;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\Domain;
use App\Models\Notification;
use App\Models\Posplan;
use App\Models\Role;
use App\Models\Staff;
use App\Models\Store;
use App\Models\StorePurchaseHistory;
use App\Models\SuperstaffSalesCommission;
use App\Models\Toptool;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use WebPConvert\Convert\Exceptions\ConversionFailedException;
use WebPConvert\WebPConvert;
use App\Models\RegistrationFee;
use App\Models\Plan;
use App\Models\Order;

/**
 * @param $message
 * @param array $data
 * @return \Illuminate\Http\JsonResponse
 */
function sendResponse($message = '', $data = [], $code = 200)
{
    $response = [
        'status' => true,
        'message' => $message,
        'data' => []
    ];

    !empty($data) ? $response['data'] = $data : [];

    return response()->json($response, $code);
}


/**
 * @param $message
 * @param array $messages
 * @param int $code
 * @return \Illuminate\Http\JsonResponse
 */
function sendError($message = '', $errors = [], $code = 404)
{
    $response = [
        'status' => false,
        'message' => $message,
    ];

    !empty($errors) ? $response['errors'] = $errors : [];

    return response()->json($response, $code);
}


/**
 * server error response function
 *
 * @return \Illuminate\Http\JsonResponse
 */
function serverError()
{
    return sendError('Server error', [], 500);
}


/*user role access*/
if (!function_exists('canAccess')) {
    /**
     * Check user access permission
     *
     * @param $permissionString
     * @return bool
     */
    function canAccess($permissionString = '')
    {
        if (Auth::check()) {
            if (Auth::user()->type == 'staff') {
                $staff = Staff::where('uid', Auth::user()->id)->first(); // Get staff data
                $store_id = $staff->store_id; // Get staff store_id (What is him/her store ID)
                $role = Role::where('id', $staff->role_id)->first(); // Get staff role ( Role of this store )

                $allPermission = array();

                if (isset($role)) {
                    $permission = explode(',', $role->permission);
                    foreach ($permission as $key => $pr) {
                        $allPermission[$pr] = $key;
                    }
                }
                if (isset($allPermission[$permissionString])) {
                    return true;
                } else {
                    return false;
                }
            } else {
                if (Auth::user()->type == 'admin' || Auth::user()->type == 'dropshipper') {
                    return true;
                } else {
                    if (Auth::user()->type == 'superadmin') {
                        return true;
                    } else {
                        if (Auth::user()->type == 'superstaff') {
                            $superstaff = DB::table('superstaffs')
                                ->where('uid', Auth::user()->id)
                                ->first();
                            $superrole = DB::table('superroles')
                                ->where('id', $superstaff->role_id)
                                ->first();

                            $permission = explode(',', $superrole->permission);

                            if (isset(Auth::user()->store_id) && !is_null(Auth::user()->store_id)) {
                                $superrolePermission = DB::table('superstaff_permissions')
                                    ->where('role_id', $superstaff->role_id)
                                    ->first();

                                if (isset($superrolePermission)) {
                                    $superPermission = explode(',', $superrolePermission->permission);

                                    // Merge both permission arrays
                                    $permission = array_merge($superPermission, $permission);
                                }
                            }

                            $allPermission = array();

                            foreach ($permission as $key => $pr) {
                                $allPermission[$pr] = $key;
                            }

                            if (isset($allPermission[$permissionString])) {
                                return true;
                            } else {
                                return false;
                            }
                        } else {
                            return false;
                        }
                    }
                }
            }

        } else {
            return false;
        }

    }

}

/*superuser staff role access*/
if (!function_exists('canSuperStaffAccess')) {
    /**
     * Check super staff user access permission
     *
     * @param $permissionString
     * @return bool
     */
    function canSuperStaffAccess($permissionString = '')
    {
        if (Auth::check()) {
            if (Auth::user()->type == 'superadmin') {
                return true;
            } elseif (Auth::user()->type == 'superadminstaff' || Auth::user()->type == 'superstaff') {
                $superstaff = DB::table('superstaffs')
                    ->where('uid', Auth::user()->id)
                    ->first();
                $superrole = DB::table('superroles')
                    ->where('id', $superstaff->role_id)
                    ->first();
                $permission = explode(',', $superrole->permission);

                $allPermission = array();

                foreach ($permission as $key => $pr) {
                    $allPermission[$pr] = $key;
                }

                if (isset($allPermission[$permissionString])) {
                    return true;
                } else {
                    return false;
                }
            } else {
                return false;
            }

        } else {
            return false;
        }

    }

}

/*remove file*/
if (!function_exists('deleteFile')) {
    /**
     * delete file
     *
     * @param $path
     */
    function deleteFile($path)
    {
        if (file_exists($path)) {
            unlink($path);
        }
    }
}

/*upload file*/
if (!function_exists('uploadFile')) {
    /**
     * File upload function
     *
     * @param $image
     * @param $imageUploadPath
     * @return string
     * @throws ConversionFailedException
     */
    function uploadFile($image, $imageUploadPath)
    {
        $imgExtension = $image->getClientOriginalExtension();

        $imgName = "";
        if ($imgExtension === 'gif') {
            $imgName = Carbon::now()->timestamp . rand(1, 9999) . '.gif';
            $image->move($imageUploadPath, $imgName);
        } elseif ($imgExtension == 'webp') {
            $image = imagecreatefromstring(file_get_contents($image));
            ob_start();
            imagejpeg($image, null, 100);
            $cont = ob_get_contents();
            ob_end_clean();

            imagedestroy($image);

            $content = imagecreatefromstring($cont);
            $imgName = Carbon::now()->timestamp . rand(1, 9999) . '.webp';

            $output = $imageUploadPath . $imgName;

            // Save the image as JPEG first
            imagejpeg($content, $output . '.jpg', 100);
            imagedestroy($content);

            // Convert the saved JPEG to WebP using the webp-convert library
            WebPConvert::convert($output . '.jpg', $output);

            // Optionally, you can delete the intermediate JPEG file
            unlink($output . '.jpg');
        } else {
            $extension = $imgExtension == 'gif' ? $imgExtension : "webp";

            // Create unique image name
            $imgName = Carbon::now()->timestamp . rand(1, 9999) . '.' . $extension;
            $imgPath = $imageUploadPath . $imgName;

            $image = Image::make($image->getRealPath(), 'imagick');

            // Get original image dimensions
            $originalWidth = $image->width();
            $originalHeight = $image->height();

            // Determine the shortest side and calculate new dimensions
            $shortestSide = min($originalWidth, $originalHeight);
            $ratio = 800 / $shortestSide;
            $newWidth = $originalWidth * $ratio;
            $newHeight = $originalHeight * $ratio;

            // Reduce image size from original image size
            $image->resize($newWidth, $newHeight, function ($constraint) {
                $constraint->aspectRatio();
                $constraint->upsize();
            })->encode($extension, 90)->save($imgPath);
        }

        return $imgName;
    }
}


if (!function_exists('imageValidation')) {
    function imageValidation($image, $store_id)
    {
        // Check image covert modules is active or not
        $imageModuleID = '107';
        $storeModulu = BuyModulus::where('modulus_id', $imageModuleID)->where('store_id', $store_id)->first();
        if (isset($storeModulu->status) && $storeModulu->status == 1) {
            $imageConvert = true;
        } else {
            $imageConvert = false;
        }

        $imgSize = $image->getSize();
        $imgSize = $imgSize / 1024;  // convert image size to kb

        // Check image converter module is active or not if active then check image size
        if ($imageConvert) {
            // Check image size if the size is greater than 600kb than throw an error.
            if ($imgSize > 6144) {
                return "Media must be lower than or equal to 6MB!";
            }
        } else {
            // Check image size if the size is greater than 200kb than throw an error.
            if ($imgSize > 200) {
                return "Media must be lower than or equal to 200kb.";
            }
        }


        // Check mimeType
        $mimeType = getMimeTypes();
        $imgExt = strtolower($image->getClientOriginalExtension()); // Convert to lowercase

        // Check input image mimeType
        if (!in_array($imgExt, $mimeType)) {
            return getMimeTypesValidationMessage();
        }

        return null;
    }
}


/*update file*/
if (!function_exists('updateFile')) {
    /**
     * File update function
     *
     * @param $image
     * @param $imageUploadPath
     * @param $oldFile
     * @return string
     * @throws ConversionFailedException
     */
    function updateFile($image, $imageUploadPath, $oldFile = '')
    {
        if ($oldFile != null) {
            $path = $imageUploadPath . $oldFile;
            if (!demoImageCheck($oldFile)) {
                deleteFile($path);
            }
        }
        return uploadFile($image, $imageUploadPath);
    }
}

/*user information*/
if (!function_exists('getUserData')) {
    function getUserData()
    {
        /**
         * get user information
         *
         * @return $user, $usertype, $store_id, $customer_id
         */
        /*get user id, type, store_id, customer*/
        if (Auth::check()) {
            $user = Auth::user();
            $user_id = Auth::user()->id;
            $user_type = Auth::user()->type;
            $store_id = null;
            $store = null;
            $customer = null;
            $customer_id = null;

            if ($user_type == 'admin' || $user_type == 'dropshipper') {
                $customer = Customer::with("getStore")->where('uid', $user_id)->first();
                $store = $customer->getStore;
                $store_id = $store->id;
                $customer_id = $customer->id;
            } elseif ($user_type == 'staff') {
                $staff = Staff::where('uid', $user_id)->first();
                $store_id = $staff->store_id ?? "";
                $store = Store::where("id", $store_id)->first();
                $customer_id = $staff->customer_id;
                $customer = Customer::where('id', $staff->customer_id)->first();
            } elseif ($user_type == 'superstaff') {
                $staff = Auth::user();
                $store_id = $staff->store_id ?? "";
                $store = Store::where("id", $store_id)->first();
                $user_id = $store->user_id ?? "";
                $user_type = $user->type ?? "";
                $customer = Customer::where('uid', $user_id)->first();
                $customer_id = $customer->id ?? "";
            }

            return [
                'user' => $user,
                'user_id' => $user_id,
                'user_type' => $user_type,
                'store_id' => $store_id,
                'store' => $store,
                'customer' => $customer,
                'customer_id' => $customer_id
            ];
        }
        return [];

    }
}


if (!function_exists('getStoreExpiryStatus')) {
    function getStoreExpiryStatus()
    {
        /*extract user_id, user_type, store_id, customer_id*/
        extract(getUserData());

        $now = Carbon::now();
        $exp = 0;
        $posplan = null;
        $digitalplan = null;
        $dexp = 1;

        if ($store) {
            // Main plan check
            if ($store->plan_id !== null) {
                $exp = $store->expiry_date <= $now ? 1 : 0;

                if ($exp && isset($store->pos_plan_id)) {
                    $exp = $store->pos_plan_expiry_date > $now ? 0 : 1;
                }
            } else {
                if (isset($store->pos_plan_id) && $store->pos_plan_expiry_date >= $now) {
                    $posplan = 1;
                    $exp = 1;
                }

                if (isset($store->digital_plan_id) && Carbon::parse($store->digital_plan_end_date) >= $now) {
                    $digitalplan = 1;
                }
            }

            // POS plan status
            if (isset($store->pos_plan_id) && $store->pos_plan_expiry_date >= $now) {
                $posplan = 1;
            }

            // Digital plan status
            if (isset($store->digital_plan_id) && Carbon::parse($store->digital_plan_end_date) >= $now) {
                $digitalplan = 1;
                $dexp = 0;
            }
        }

        return [
            'exp' => $exp,
            'posplan' => $posplan,
            'digitalplan' => $digitalplan,
            'dexp' => $dexp
        ];
    }
}

if (!function_exists('getStoreByConversationID')) {

    function getStoreByConversationID($conversationID = "")
    {
        $conversation = ChatConversation::with('visitor.user')->find($conversationID);
        $user = $conversation->visitor->user ?? null;
        $store_id = null;

        if (isset($user)) {
            $user_type = $user->type ?? null;
            $user_id = $user->id ?? null;
            if ($user_type == 'admin' || $user_type == 'dropshipper') {
                $customer = Customer::where('uid', $user_id)->first();
                $store_id = $customer->active_store;
            } elseif ($user_type == 'staff') {
                $staff = Staff::where('uid', $user_id)->first();
                $store_id = $staff->store_id;
            }
        }

        return $store_id;
    }
}

/*top tools counts*/
if (!function_exists('topToolsCount')) {
    /**
     * Tracking information
     *
     * @param $name
     * @param $image
     * @param $url
     */
    function topToolsCount($name, $image, $url)
    {
        /*extract user_id, user_type, store_id, customer_id*/
        extract(getUserData());

        /*create or increment count of toptools*/
        $toptool = Toptool::where('name', $name)->where('uid', $user_id)->where('store_id', $store_id)->first();
        if (isset($toptool)) {
            $toptool->count = $toptool->count + 1;
            $toptool->save();
        } else {
            $toptool = new Toptool();
            $toptool->name = $name;
            $toptool->image = $image;
            $toptool->url = $url;
            $toptool->count = "1";
            $toptool->uid = $user_id;
            $toptool->store_id = $store_id;
            $toptool->customer_id = $customer_id;
            $toptool->creator = $user_id;
            $toptool->editor = $user_id;
            $toptool->save();
        }

    }
}

/* Get visitor info*/
if (!function_exists('getVisitorInfo')) {
    function getVisitorInfo()
    {
        $ip = Request::ip();
//        $ip = "220.158.205.23"; // BD
//        $ip = "102.129.132.255"; // US
        return Location::get($ip);
    }
}

/*currency conversions*/
if (!function_exists('conversionsCurrency')) {
    function conversionsCurrency($currency = null, $id = null, $store_id = null)
    {
        $response = ['amount' => 0, 'symbol' => "", 'code' => ""];
        if (!is_null($store_id) && !is_null($currency) && !is_null($id) && !empty($id)) {
            $store = Store::with('current_currency')->where('id', $store_id)->first();

            if (isset($store)) {
                $currency_rate = $store['currency_rate'];
                $current_currency = $store['current_currency'];
                $symbol = $current_currency->symbol;
                $code = $current_currency->code;
                if ($store['currency'] == $id) {
                    return [
                        'amount' => round($currency, 2),
                        'symbol' => $symbol,
                        'code' => $code
                    ];
                }
                if ($current_currency->customize_rate_status == 1) {
                    $currency = $currency / $currency_rate;
                } else {

                    $conv = Currency::find($id);
                    if (isset($conv)) {
                        $currency = ($currency / $conv->rate) * $current_currency->rate;
                    } else {
                        $currency = 0;
                    }
                }

                $response = [
                    'amount' => round($currency, 2),
                    'symbol' => $symbol,
                    'code' => $code
                ];

                return $response;
            }
        }

        return $response;
    }
}

/*currency symbol and code*/
if (!function_exists('currentCurrency')) {
    function currentCurrency()
    {
        /*extract user_id, user_type, store_id,customer, customer_id*/
        extract(getUserData());

        $store = Store::with('current_currency')->where('id', $store_id)->first();
        return [
            'current_currency' => $store['current_currency'],
            'currency_rate' => $store->currency_rate,
            'currency_id' => $store['currency']
        ];
    }
}

/* Extract Domain Parts */
if (!function_exists('extractDomainParts')) {
    function extractDomainParts($domain)
    {
        $domain = cleanDomain($domain);
        if (filter_var($domain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
            $parts = explode('.', $domain);

            if (count($parts) < 2) {
                return [
                    'subdomain' => null,
                    'domain' => null,
                    'extension' => null,
                ];
            }

            // Extract subdomain and main domain.
            $subdomain = $parts[0];
            $mainDomain = implode('.', array_slice($parts, 0, -1)); // Full domain without TLD
            $extension = end($parts);
        }

        return [
            'subdomain' => $subdomain ?? null,
            'domain' => $mainDomain ?? null,
            'extension' => $extension ?? null,
        ];
    }
}


/* Get Store Analytic Data */
if (!function_exists('getAnalyticData')) {
    function getAnalyticData($store_id, $days = 15)
    {
        // Get the start and end date for the current period (last $days days)
        $currentStartDate = Carbon::now()->subDays($days)->startOfDay();
        $currentEndDate = Carbon::now()->endOfDay();

        // Get the start and end date for the previous period (previous $days days)
        $previousStartDate = Carbon::now()->subDays($days * 2)->startOfDay();
        $previousEndDate = Carbon::now()->subDays($days)->endOfDay();

        // Get the current period visitors count
        $currentVisitors = AdminVisitor::where('store_id', $store_id)
            ->whereBetween('created_at', [$currentStartDate, $currentEndDate])
            ->count();

        // Get the previous period visitors count
        $previousVisitors = AdminVisitor::where('store_id', $store_id)
            ->whereBetween('created_at', [$previousStartDate, $previousEndDate])
            ->count();

        // Calculate percentage change
        if ($previousVisitors > 0) {
            $percentageChange = (($currentVisitors - $previousVisitors) / $previousVisitors) * 100;
        } else {
            $percentageChange = $currentVisitors > 0 ? 100 : 0;
        }

        // Format the percentage with + or - sign
        $visitorChange = ($percentageChange >= 0 ? '+' : '') . round($percentageChange, 2) . '%';

        // Add data to response
        $data['totalVisitor'] = $currentVisitors;
        $data['visitorChange'] = $visitorChange;

        return $data;
    }

}


/* Get New Expiry Date */
if (!function_exists('getNewExpiryDate')) {
    function getNewExpiryDate($item, $tableExpired_date, $plan_id = null, $month = null)
    {
        if (is_null($month)) {
            $expired_date = $item['months'] ?? 0;
        } else {
            $expired_date = $month ?? 0;
        }

        $activeTimeStatus = $item['activeTime'] ?? 0;

        if (isset($plan_id) && $plan_id == 6) {
            $activeTimeStatus = 1;
        }

        $newExpiryDate = Carbon::now()->addMonths($expired_date);

        $isValidDate = true;
        try {
            Carbon::parse($tableExpired_date);
        } catch (\Exception $e) {
            $isValidDate = false;
        }

        if (isset($tableExpired_date) && $isValidDate) {
            if (Carbon::parse($tableExpired_date)->gt(Carbon::now())) {
                $daysLeft = Carbon::now()->diffInDays($tableExpired_date);
            } else {
                $daysLeft = 0;
            }

            if ($daysLeft > 0 && $activeTimeStatus == 0) {
                $newExpiryDate = Carbon::now()->addMonths($expired_date)->addDays($daysLeft);
            }
        }

        return $newExpiryDate;
    }

}


/*currency symbol and code*/
if (!function_exists('storePlanPurchaseHistory')) {
    function storePlanPurchaseHistory($userID, $store_id, $plan_id, $plan_month = 1, $item = [], $active_date = null, $expire_date = null, $single = false)
    {
        try {
            if ($single) {
                $package = [];
                if (isset($item->package)) {
                    $package = json_decode($item->package, true);
                }

                $plan_price = (float)($package['price'] ?? 0);
                $total = (float)($package['offerprice'] ?? 0);
            } else {
                $plan_price = (float)($item['price'] ?? 0);
                $total = (float)($item['discountPrice'] ?? 0);
            }

            $discount = $plan_price - $total;

            $commission = SuperstaffSalesCommission::where("user_id", $userID ?? NULL)->first();
            $seller_id = $commission->staff_id ?? NULL;

            $purchaseHistory = new StorePurchaseHistory();
            $purchaseHistory->store_id = $store_id;
            $purchaseHistory->user_id = $userID ?? NULL;
            $purchaseHistory->plan_id = $plan_id;
            $purchaseHistory->plan_month = $plan_month;
            $purchaseHistory->plan_price = $plan_price;
            $purchaseHistory->discount = $discount;
            $purchaseHistory->total = $total;
            $purchaseHistory->active_date = $active_date;
            $purchaseHistory->expire_date = $expire_date;
            $purchaseHistory->seller_id = $seller_id;
            $purchaseHistory->save();
        } catch (\Exception $e) {

        }
    }

}


/*currency symbol and code*/
if (!function_exists('demoImageCheck')) {

    function demoImageCheck($image)
    {
        $images = [
            "16768161770.jpg", "16768161180.jpg", "16768160520.jpg", "16768841280.jpg", "16768138120.jpg",
            "16768145600.jpg", "16768838720.jpg", "16768139070.jpg", "16768139060.jpg", "16768139810.jpg",
            "16768140280.jpg", "16768140550.jpg", "16768140600.jpg", "16768140610.jpg", "16768120000.jpg",
            "1661848696.jpg", "1677063697.jpg", "1677062447.jpg", "16768977350.jpg", "16768976610.jpg",
            "16768975960.jpg", "16768975560.jpg", "1677062211.jpg", "1677063208.jpg", "16768973230.jpg",
            "16768972610.jpg", "16768974280.jpg", "16768973690.jpg", "16768971510.jpg", "16768971440.jpg",
            "16768972200.jpg", "16768970820.jpg", "16768971040.jpg", "16768971600.jpg", "16768971380.jpg",
            "16768971300.jpg", "16768971210.jpg", "16768971140.jpg", "1683365411.jpg", "1683365259.jpg",
            "1658745955.jpg", "1683366223.jpg", "1676898656.jpg", "1676898693.jpg", "16695251560.jpg",
            "16695253120.jpg", "16695253440.jpg", "16695263050.jpg", "16695264990.jpg", "16695265450.jpg",
            "16695266230.jpg", "16695262110.jpg", "1669531558.jpg", "16695267060.jpg", "16695252390.jpg",
            "1669530312.jpg", "1669528621.jpg", "1669529883.png", "1669532272.jpg", "16791370750.jpg",
            "16791214960.jpg", "16791214660.jpg", "16791214400.jpg", "16791213240.jpg", "16791212960.jpg",
            "16791212610.jpg", "16791212280.jpg", "16791211890.jpg", "16791211550.jpg", "16791211290.jpg",
            "16791211040.jpg", "16791210520.jpg", "16701572460.png", "16701573010.jpg", "16701572730.jpg",
            "16701573300.png", "16701573610.webp", "16701573960.webp", "16701574240.png", "16791208420.jpg",
            "16791207910.webp", "16701575640.webp", "1670158045.jpg", "1670158232.jpg", "1679120053.png",
            "1670157740.jpg", "1679120102.jpg", "1670157806.jpg", "Product_01.jpg", "Product_02.jpg",
            "Product_03.jpg", "Product_04.jpg", "Product_05.jpg", "Product_06.jpg", "Product_07.jpg",
            "Product_08.jpg", "Product_09.jpg", "Product_10.jpg", "Banner_01.jpg", "Banner_02.jpg",
            "Banner_03.jpg", "Slider_01.jpg", "Slider_02.jpg", "Slider_03.jpg", "167655647421161.png",
            "167655647413025.png", "167655647453357.png", "173996857249955.png", "167689265205732.png",
            "167655647448015.png", "167655647436390.png", "1678878721127571.png", "167887872162563.png",
            "167897478705108.png", "1678878721118318.png", "167911921102099.png", "167583851979975.png",
            "167583851952604.png", "167583851937969.png", "167583851914263.png", "167583962916244.png",
            "167689265245873.png", "167689265233069.png", "167689265215598.png",
            "167689265224060.png"
        ];


        // Check if the image exists in the array
        return in_array($image, $images);
    }

}

if (!function_exists('addDomainFromAddon')) {
    /**
     * Add domain
     *
     * @param $domain
     * @param $email
     * @param $user
     * @return void
     */
    function addDomainFromAddon($requestDomain, $email, $user = null, $newPlan = false)
    {
        if ($user) {
            $user_id = $user->id;
            $user_type = $user->type;
            $store = null;
            if ($user_type == 'admin' || $user_type == 'dropshipper') {
                $customer = Customer::where('uid', $user_id)->first();
                $store_id = $customer->active_store;
                $customer_id = $customer->id;
                $store = Store::find($store_id);
            }

            if (!is_null($store) && isset($store->plan_id) && ($store->plan_id != 6 || $newPlan)) {
                $domain = new Domain();
                $domain->name = cleanDomain($requestDomain);
                $domain->email = $email;
                $domain->status = "Buying Request";
                $domain->uid = $user_id;
                $domain->store_id = $store_id;
                $domain->customer_id = $customer_id;
                $domain->creator = $user_id;
                $domain->editor = $user_id;
                $domain->save();

                $linkURL = route("superadmin.domainrequest");
                $notificationData = [
                    "title" => "Domain Buying Request (" . ($domain->name ?? '') . ") - " . formatDateWithTime($domain->created_at),
                    "type" => "domain_request",
                    "user_type" => "superadmin",
                    "link" => $linkURL,
                ];

                if (isset($notificationData['title']) && !empty($notificationData['title'])) {
                    createNotification($notificationData);
                }
            }
        }
    }
}


if (!function_exists('cleanDomain')) {
    function cleanDomain($url)
    {
        // Parse the URL to extract components
        $parsedUrl = parse_url(trim($url)); // Trim to remove extra spaces

        // Extract the host or path if the host is not available
        $domain = isset($parsedUrl['host']) ? $parsedUrl['host'] : (isset($parsedUrl['path']) ? $parsedUrl['path'] : '');

        // Check if the domain starts with 'www.' and remove it
        if (substr($domain, 0, 4) === "www.") {
            $domain = substr($domain, 4);
        }

        // Trim whitespace and return the cleaned domain
        return strtolower(trim($domain));
    }
}

if (!function_exists('getPrice')) {
    function getPrice($regular_price, $discount_price, $discount_type)
    {
        $regular_price = (float)$regular_price; // Ensure the price is treated as an integer
        $discount_price = (float)$discount_price;

        if ($discount_type === "percent") {
            $price = ($regular_price - ($discount_price / 100) * $regular_price);
            return $price;
        }

        if ($discount_type === "fixed") {
            $price = $regular_price - $discount_price;
            return $price;
        }

        if ($discount_type === "no_discount") {
            return $regular_price;
        }

        return $regular_price;
    }

}


if (!function_exists('getDiscountAmount')) {
    function getDiscountAmount($regular_price, $discount_price, $discount_type)
    {
        $regular_price = (float)$regular_price; // Ensure the price is treated as an integer
        $discount_price = (float)$discount_price;

        if ($discount_type === "percent") {
            return ($discount_price / 100) * $regular_price;
        }

        if ($discount_type === "fixed") {
            return $discount_price;
        }

        return 0;
    }

}


if (!function_exists('isAddonActive')) {
    function isAddonActive($addons_id, $storeId = null)
    {
        $store_id = $storeId ?? getUserData()['store_id'] ?? "";
        $currentDate = \Carbon\Carbon::now();
        $addon = DB::table('addons_expireds')
            ->where('addons_id', $addons_id)
            ->where('store_id', $store_id)
            ->where('expired_date', ">=", $currentDate)
            ->first();

        return isset($addon) ? true : false;
    }

}

if (!function_exists('isActivePos')) {
    function isActivePos($addons_id = 13)
    {
        $userData = getUserData();
        $store_id = $storeId ?? $userData['store_id'] ?? null;

        if (!$store_id) {
            return false;
        }

        // Check if POS addon is active (first condition)
        $hasActiveAddon = DB::table('addons_expireds')
            ->where('addons_id', $addons_id)
            ->where('store_id', $store_id)
            ->where('expired_date', '>=', now())
            ->exists();

        // Check if POS plan is active (second condition)
        $store = $userData['store'] ?? null;
        $hasActivePlan = false;

        if ($store && isset($store->pos_plan_id)) {
            $hasActivePlan = ($store->pos_plan_expiry_date >= now()) &&
                Posplan::where('id', $store->pos_plan_id)->exists();
        }

        // Return true if EITHER condition is true
        return $hasActiveAddon || $hasActivePlan;
    }

}


if (!function_exists('sixDigitRandCode')) {
    function sixDigitRandCode()
    {
        return random_int(100000, 999999);
    }
}


if (!function_exists('randNumberGenerate')) {
    function randNumberGenerate($min = 100000, $max = 999999)
    {
        return random_int($min, $max);
    }
}


if (!function_exists('getMimeTypes')) {
    function getMimeTypes()
    {
        return array("jpg", "jpeg", "png", "svg", "webp", "gif");
    }
}

if (!function_exists('getMimeTypesValidationMessage')) {
    function getMimeTypesValidationMessage()
    {
        return "Media should be jpg, jpeg, png, svg, webp, gif.";
    }
}

if (!function_exists('getUserReferralCode')) {
    function getUserReferralCode()
    {
        $user = getUserData()['user'];
        if (isset($user)) {
            if (is_null($user->referral) || empty($user->referral)) {
                $user->referral = Carbon::now()->timestamp . sixDigitRandCode();
                $user->update();

                $referral = $user->referral;
            } else {
                $referral = $user->referral;
            }
        }

        return route("register", ["referral" => $referral ?? ""]);
    }

}


if (!function_exists('normalizeText')) {
    function normalizeText($text)
    {
        // Normalize Unicode characters (NFKD breaks down composed characters)
        if (class_exists('Normalizer')) {
            $text = Normalizer::normalize($text, Normalizer::FORM_KD);
        }

        // Remove any non-printable or non-ASCII characters
        return preg_replace('/[^\x20-\x7E]/u', '', $text);
    }
}

if (!function_exists('normalizeUnicodeText')) {
    function normalizeUnicodeText($text)
    {
        // Keep only letters, numbers, spaces, and dashes (supports Unicode scripts)
        return preg_replace('/[^\p{L}\p{N}\s\-]/u', '', $text);
    }
}

if (!function_exists('generateSlug')) {
    function generateSlug($text, $separator = "-")
    {
        // Attempt to create a slug with normalized Unicode text
        $slug = Str::slug(normalizeUnicodeText($text), $separator);

        // If slug is empty, fallback to normalized ASCII-only text
        if (empty($slug)) {
            $slug = Str::slug(normalizeText($text), $separator);
        }

        // If the slug is still empty, create a custom slug by removing spaces and non-alphanumerics
        if (empty($slug)) {
            $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', $separator, $text));
            $slug = trim($slug, $separator); // Remove leading and trailing separators
        }

        return $slug;
    }
}


if (!function_exists('paginationResponse')) {
    function paginationResponse($products)
    {
        if (!$products) {
            return null;
        }

        return [
            'current_page' => $products->currentPage(),
            'per_page' => $products->perPage(),
            'total' => $products->total(),
            'last_page' => $products->lastPage(),
            'has_more_pages' => $products->hasMorePages(),
            'next_page_url' => $products->nextPageUrl(),
            'prev_page_url' => $products->previousPageUrl(),
            'from' => $products->firstItem(), // First item on the current page
            'to' => $products->lastItem(),   // Last item on the current page
            'first_page_url' => $products->url(1), // URL for the first page
            'last_page_url' => $products->url($products->lastPage()), // URL for the last page
            'links' => $products->linkCollection(), // Link collection (if needed for frontend rendering)
        ];
    }

}


if (!function_exists('generateCustomEmail')) {
    function generateCustomEmail($name, $email = null)
    {
        // If email is provided, return it directly
        if (!empty($email)) {
            return $email;
        }

        // If name contains a space, use both parts to generate the email
        if (str_contains($name, ' ')) {
            $parts = explode(' ', strtolower($name)); // Split name into parts and make it lowercase
            $emailPrefix = implode('.', $parts);     // Join the parts with a dot
        } else {
            $emailPrefix = strtolower($name);        // Use the full name in lowercase
        }

        return $emailPrefix . "@mail.com"; // Append the email domain
    }
}


if (!function_exists('getUserNameOrPhone')) {
    function getUserNameOrPhone($user)
    {
        return !empty($user->name) ? $user->name :
            (!empty($user->phone) ? $user->phone :
                (!empty($user->email) ? $user->email : ''));
    }
}


if (!function_exists("generateShortReferenceNo")) {
    function generateShortReferenceNo()
    {
        $timestamp = now()->format('His'); // Get only Hour, Minute, Second (e.g., 123045)
        $randomDigits = mt_rand(100, 999); // Generate a 3-digit random number
        return "BN" . $timestamp . $randomDigits;
    }
}


if (!function_exists("generateInvoiceNo")) {
    function generateInvoiceNo($storeName = NULL)
    {
        $prefix = "INV";
        if (isset($storeName)) {
            $prefix = getShortName($storeName);
        }
        $timestamp = now()->format('His'); // Get only Hour, Minute, Second (e.g., 123045)
        $randomDigits = mt_rand(100, 999); // Generate a 3-digit random number
        return $prefix . $timestamp . $randomDigits;
    }
}


if (!function_exists("getInitials")) {
    function getShortName($fullName)
    {
        $words = explode(' ', trim($fullName)); // Split the name into words
        $initials = '';

        // Get the first letter of each word
        foreach ($words as $word) {
            if (!empty($word)) {
                $initials .= strtoupper($word[0]);
            }
        }

        // If we have less than 3 letters, take more from the first word
        if (strlen($initials) < 3 && count($words) > 0) {
            $remaining = substr(strtoupper($words[0]), 1, 3 - strlen($initials));
            $initials .= $remaining;
        }

        return $initials;
    }
}


if (!function_exists("createNotification")) {
    function createNotification($data)
    {
        $notification = new Notification();
        $notification->title = $data['title'] ?? null;
        $notification->body = $data['body'] ?? nuLL;
        $notification->type = $data['type'] ?? null;
        $notification->user_type = $data['user_type'] ?? null;
        $notification->user_id = $data['user_id'] ?? null;
        $notification->store_id = $data['store_id'] ?? null;
        $notification->link = $data['link'] ?? null;
        $notification->save();

        pushNotification($notification);
    }
}


if (!function_exists("updateNotification")) {
    function updateNotification($data, $conversation_id)
    {
        $notification = Notification::where("conversation_id", $conversation_id)->first();
        if (!isset($notification)) {
            $notification = new Notification();
            $notification->conversation_id = $conversation_id ?? null;
        }

        $notification->title = $data['title'] ?? null;
        $notification->body = $data['body'] ?? nuLL;
        $notification->type = $data['type'] ?? null;
        $notification->user_type = $data['user_type'] ?? null;
        $notification->user_id = $data['user_id'] ?? null;
        $notification->store_id = $data['store_id'] ?? null;
        $notification->link = $data['link'] ?? null;
        $notification->save();

        pushNotification($notification);
    }
}


if (!function_exists("pushNotification")) {
    function pushNotification($notification)
    {
        $url = route("notification.view-notification", ["id" => $notification->id]);
        if (isset($notification->link) && !empty($notification->link)) {
            $url = $notification->link;
        }

        $socket_url = str_replace("wss://", "https://", env('SOCKET_URL')) . "/sendNotification";

        try {
            Http::post($socket_url, [
                'message' => [
                    "message" => $notification->title ?? "Notification",
                    "user_type" => $notification->user_type ?? "",
                    "store_id" => $notification->store_id ?? "",
                    "user_id" => $notification->user_id ?? "",
                    'url' => $url,
                ],
            ]);
        } catch (Exception $e) {

        }

    }
}


if (!function_exists("appNotification")) {
    function appNotification($notification)
    {
        Http::post('https://app.nativenotify.com/api/notification', [
            "appId" => 3694,
            "appToken" => "KL3ZduYufeIFyrZPHJ6bWK",
            "title" => $notification->title,
            "body" => $notification->body,
            "dateSent" => $notification->created_at,
            "pushData" => [
                "pushData" => $notification->link
            ]
        ]);
    }
}


if (!function_exists("formatDateWithTime")) {
    function formatDateWithTime($time, $format = "Y-m-d H:i:s A")
    {
        if (!is_null($time) && !empty($time)) {
            return \Carbon\Carbon::parse($time)->format($format);
        }

        return $time;
    }
}

if (!function_exists("getOriginDomain")) {
    function getOriginDomain()
    {
        $request = request();

        $domain = parse_url($request->header('Origin'), PHP_URL_HOST) ??
            parse_url($request->header('Referer'), PHP_URL_HOST) ??
            $request->input('origin_domain') ??
            null;

        return $domain ? preg_replace('/^www\./', '', $domain) : null;
    }
}

if (!function_exists("checkPaidRegistration")) {
    function checkPaidRegistration()
    {
        if (env("PAID_REGISTRATION")) {
            $registrationFee = RegistrationFee::where("status", 1)->first();

            if (isset($registrationFee)) {
                return true;
            }
        }
        return false;
    }
}

if (!function_exists("paidTrial")) {
    function paidTrial()
    {
        if (checkPaidRegistration()) {
            $userData = getUserData();
            $store = $userData['store'];

            if (isset($store) && isset($store->created_at) && is_null($store->expiry_date)) {
                $min = env("REGISTRATION_PAYMENT_DELAY", 20);
                $showPaymentTime = Carbon::parse($store->created_at)->addMinutes($min);
                $currentTime = Carbon::now();

                if ($showPaymentTime >= $currentTime) {
                    return true;
                }
            }
        }
        return false;
    }
}

if (!function_exists("setPackageCommission")) {
    function setPackageCommission($store_id = '', $plan_id = null, $commission = null)
    {
        $store = Store::where("id", $store_id)->first();

        if (isset($store)) {
            if (is_null($commission)) {
                if (is_null($plan_id)) {
                    $plan_id = $store->plan_id ?? 6;
                }

                $commission = 0;
                $plan = Plan::where("id", $plan_id)->first();
                if (isset($plan) && $plan->price == 0) {
                    if ($plan_id == 9) {
                        $commission = 3;
                    } else {
                        $commission = 1;
                    }
                }
            }

            $store->dropship_commission = $commission;
            $store->save();
        }
    }
}

if (!function_exists("checkOrderLimit")) {
    function checkOrderLimit($store_id = null)
    {
        if (is_null($store_id)) {
            $userData = getUserData();
            $store = $userData['store'] ?: NULL;
            $store_id = $userData['store_id'] ?: NULL;
        } else {
            $store = Store::where("id", $store_id)->first();
        }


        if (isset($store) && !empty($store->expiry_date) && Carbon::parse($store->expiry_date)->gte(Carbon::now())) {
            $plan = Plan::find($store->plan_id);

            if ($plan && $plan->order > 0) {
                $startOfMonth = Carbon::now()->startOfMonth();
                $endOfMonth = Carbon::now()->endOfMonth();

                $orderCount = Order::where('store_id', $store_id)
                    ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
                    ->count();

                if ($orderCount < $plan->order) {
                    return true;
                }
            }
        }

        return false;
    }
}

if (!function_exists("cleanPrice")) {
    function cleanPrice(string $price)
    {
        $numericValue = preg_replace('/[^\d.]/', '', $price);

        // Check if value is a whole number
        if (preg_match('/^[0-9]+$/', $numericValue)) {
            return (int)$numericValue;  // Return as integer
        }

        return (float)$numericValue;  // Return as float if decimal exists
    }
}


if (!function_exists("formatNumber")) {
    function formatNumber($price, $decimals = 2, $decimal_separator = ".", $thousands_separator = "")
    {
        return number_format($price, $decimals, $decimal_separator, $thousands_separator);
    }
}


if (!function_exists("isProbablyDomainOrUrl")) {
    function isProbablyDomainOrUrl(string $input): bool
    {
        $input = trim($input);
        if (empty($input)) return false;

        // Encode spaces and special characters in query
        $input = preg_replace('/\s+/', '%20', $input);

        if (filter_var($input, FILTER_VALIDATE_URL)) {
            return (bool)parse_url($input, PHP_URL_HOST);
        }

        // Check without protocol
        if (filter_var("http://" . $input, FILTER_VALIDATE_URL)) {
            $host = parse_url("http://" . $input, PHP_URL_HOST);

            return $host && preg_match('/^([a-z0-9-]+\.)+[a-z]{2,}$/i', strtolower($host));
        }

        return false;
    }


}

if (!function_exists('ensure_https_url')) {
    function ensure_https_url(string $url): string
    {
        if (!preg_match('/^https?:\/\//i', $url)) {
            return 'https://' . ltrim($url, '/');
        }

        return $url;
    }
}

if (!function_exists('checkUserFileslimit')) {
    function checkUserFileslimit(int $totalFiles = 0, ?int $store_id = null): bool
    {
        if (is_null($store_id)) {
            $userData = getUserData();
            $store_id = $userData['store_id'];
        }

        $store = Store::with("plan")->where("id", $store_id)->first();

        if (!isset($store)) return false;

        $uploadFileLimit = (int)$store->plan->upload_file_limit ?? 0;

        if ($totalFiles > $uploadFileLimit) return false;

        return true;
    }
}

if (!function_exists('getVariantImagePath')) {
    function getVariantImagePath($image)
    {
        $imagePath = "";
        if (isset($image) && !empty($image)) {
            $imagePath = trim(str_replace(env("APP_URL"), "", $image), '/');
        }

        return $imagePath;
    }
}

if (!function_exists('getLibraryImagePath')) {
    function getLibraryImagePath($image)
    {
        if (!isset($image) || $image === null || $image === '') {
            return '';
        }

        $imagePath = trim((string) $image);
        if ($imagePath === '') {
            return '';
        }

        if (str_contains($imagePath, 'media-library/file?')) {
            $query = parse_url($imagePath, PHP_URL_QUERY);
            if (is_string($query) && $query !== '') {
                parse_str($query, $params);
                $imagePath = (string) ($params['path'] ?? $imagePath);
            }
        }

        $appUrl = rtrim((string) config('app.url'), '/');
        if ($appUrl !== '' && str_starts_with($imagePath, $appUrl)) {
            $imagePath = substr($imagePath, strlen($appUrl));
        }

        $imagePath = ltrim(str_replace('\\', '/', $imagePath), '/');
        if (str_starts_with($imagePath, 'storage/')) {
            return $imagePath;
        }
        if (str_starts_with($imagePath, ['image-library/', 'ai-seed-library/'])) {
            return 'storage/' . $imagePath;
        }

        return $imagePath;
    }
}

if (!function_exists('isPath')) {
    function isPath($value)
    {
//        return strpos($value, '/') !== false;
//        return is_string($value) && substr($value, 0, 8) === 'storage/'; // For lower version < php 8
        return is_string($value) && str_starts_with($value, 'storage/');
    }
}

if (!function_exists('publicMediaLibraryUrl')) {
    function publicMediaLibraryUrl(string $path): string
    {
        $cleanPath = ltrim(str_replace('\\', '/', trim($path)), '/');
        if (str_starts_with($cleanPath, 'storage/')) {
            $cleanPath = substr($cleanPath, strlen('storage/'));
        }

        $base = rtrim((string) config('app.url'), '/');
        $query = http_build_query(['path' => $cleanPath]);

        return ($base !== '' ? $base : '') . '/react-admin-api/public/media-library/file?' . $query;
    }
}

if (!function_exists('isMediaLibraryPath')) {
    function isMediaLibraryPath(string $path): bool
    {
        $cleanPath = ltrim(str_replace('\\', '/', trim($path)), '/');
        if (str_starts_with($cleanPath, 'storage/')) {
            $cleanPath = substr($cleanPath, strlen('storage/'));
        }

        return str_starts_with($cleanPath, ['image-library/', 'ai-seed-library/', 'react-admin-media/']);
    }
}

if (!function_exists('getPath')) {
    function getPath($value, $folder = null)
    {
        if (is_null($value) || empty($value)) {
            return $value;
        }

        $base = trim(config('app.url'), '/');
        $path = trim($value, '/');

        if (is_string($value) && isMediaLibraryPath($value)) {
            return publicMediaLibraryUrl($value);
        }

        if (isPath($value)) {
            return $base . "/" . $path;
        } else {
            if ($folder) {
                $folder = trim($folder, '/');
                $base .= "/" . $folder;
            }

            return $base . "/" . $path;
        }
    }

}


if (!function_exists('getSuperAdminSetting')) {
    function getSuperAdminSetting(string $key, $default = null)
    {
        return \App\Models\SuperAdminSetting::getValue($key, $default);
    }
}

if (!function_exists('setSuperAdminSetting')) {
    function setSuperAdminSetting(string $key, $value, ?int $userId = null)
    {
        return \App\Models\SuperAdminSetting::setValue($key, $value, $userId);
    }
}


if (!function_exists('checkDomainConnectWithCpanel')) {
    function checkDomainConnectWithCpanel()
    {
        $status = getSuperAdminSetting("domain_connect_status");
        if (isset($status) && $status == "1") return false;

        return true;
    }
}

if (!function_exists('deleteTableDataHelper')) {
    function deleteTableDataHelper($modelClass, $store_id, array $columnsWithPaths)
    {
        $items = $modelClass::where('store_id', $store_id)->get();

        if ($items->isNotEmpty()) {
            foreach ($items as $item) {
                foreach ($columnsWithPaths as $column => $path) {
                    $image = $item->$column ?? null;
                    $fullPath = rtrim($path, '/') . '/' . ltrim($image, '/');

                    if ($image && !demoImageCheck($image)) {
                        deleteFile($fullPath);
                    }
                }
                $item->delete();
            }
        }
    }
}

