<?php

namespace App\Http\Controllers\Api\v2;

use App\Http\Controllers\Controller;
use App\Models\Addon;
use App\Models\AddonsApi;
use App\Models\AddonsOrder;
use App\Models\Customer;
use App\Models\Digitalplan;
use App\Models\Paymenttoken;
use App\Models\Plan;
use App\Models\Planorder;
use App\Models\Posplan;
use App\Models\Staff;
use App\Models\Store;
use App\Models\User;
use Auth;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentPageController extends Controller
{
    public function index(Request $request)
    {
        $payt = Paymenttoken::where("token", $request->token)->first();

        if (isset ($payt)) {
            $user = User::find($payt->uid);

            if ($user->type == 'admin') {
                $customer = Customer::where('uid', $user->id)->first();
                $store_id = $customer->active_store;
            } elseif ($user->type == 'staff') {
                $staff = Staff::where('uid', $user->id)->first();
                $store_id = $staff->store_id;
            }

            $store = Store::find($store_id);

            $planss = Plan::where('status', 'active')->get();

            foreach ($planss as $key => $plans) {
                $plan[$key]['id'] = $plans->id;
                $plan[$key]['name'] = $plans->name;
                $plan[$key]['type'] = 'website';
                $plan[$key]['subtitle'] = $plans->subtitle;
                $plan[$key]['discount_type'] = $plans->discount_type;
                $plan[$key]['price'] = $plans->price;

                $plan[$key]['months'][] = [
                    'name' => "1 month",
                    'month' => "1",
                    'value' => $plans->onedis,
                    'price' => $plans->price * 1

                ];

                $plan[$key]['months'][] = [
                    'name' => "6 month",
                    'month' => "6",
                    'value' => $plans->sixdis,
                    'price' => $plans->price * 6
                ];

                $plan[$key]['months'][] = [
                    'name' => "12 month",
                    'month' => "12",
                    'value' => $plans->twelvedis,
                    'price' => $plans->price * 12
                ];
            }

            $pplanss = Posplan::where('status', 'active')->get();

            foreach ($pplanss as $key => $plans) {
                $posplan[$key]['id'] = $plans->id;
                $posplan[$key]['name'] = $plans->name;
                $posplan[$key]['type'] = 'pos';
                $posplan[$key]['subtitle'] = $plans->subtitle;
                $posplan[$key]['discount_type'] = $plans->discount_type;
                $posplan[$key]['price'] = $plans->price;

                $posplan[$key]['months'][] = [
                    'name' => "1 month",
                    'month' => "1",
                    'value' => $plans->onedis,
                    'price' => $plans->price * 1

                ];

                $posplan[$key]['months'][] = [
                    'name' => "6 month",
                    'month' => "6",
                    'value' => $plans->sixdis,
                    'price' => $plans->price * 6
                ];

                $posplan[$key]['months'][] = [
                    'name' => "12 month",
                    'month' => "12",
                    'value' => $plans->twelvedis,
                    'price' => $plans->price * 12
                ];
            }

            $dplanss = Digitalplan::where('status', 'active')->get();

            foreach ($dplanss as $key => $plans) {
                $digitalplan[$key]['id'] = $plans->id;
                $digitalplan[$key]['name'] = $plans->name;
                $digitalplan[$key]['type'] = 'digital';
                $digitalplan[$key]['subtitle'] = $plans->subtitle;
                $digitalplan[$key]['discount_type'] = $plans->discount_type;
                $digitalplan[$key]['price'] = $plans->price;

                $digitalplan[$key]['months'][] = [
                    'name' => "1 month",
                    'month' => "1",
                    'value' => $plans->onedis,
                    'price' => $plans->price * 1

                ];

                $digitalplan[$key]['months'][] = [
                    'name' => "6 month",
                    'month' => "6",
                    'value' => $plans->sixdis,
                    'price' => $plans->price * 6
                ];

                $digitalplan[$key]['months'][] = [
                    'name' => "12 month",
                    'month' => "12",
                    'value' => $plans->twelvedis,
                    'price' => $plans->price * 12
                ];
            }

            return [
                'plan' => $plan,
                'posplan' => $posplan,
                'digitalplan' => $digitalplan,
                'store' => $store,
                'choose_plan' => null
            ];
        } else {
            return [
                'url' => 'https://admin.ebitans.com/login'
            ];
        }
    }

    public function placeplan(Request $request)
    {
        $tokens = Paymenttoken::where('token', $request->token)->first();
        $user_id = $tokens->uid;
        $customer = Customer::where('uid', $user_id)->first();
        $store = Store::where('id', $customer->active_store)->first();

        $post_data = array();
        $post_data['total_amount'] = $request->total; # You cant not pay less than 10
        $post_data['currency'] = "BDT";
        $post_data['tran_id'] = uniqid(); // tran_id must be unique
        $post_data['cus_name'] = 'Customer Name';
        $post_data['cus_email'] = 'customer@mail.com';
        $post_data['cus_add1'] = 'Customer Address';
        $post_data['cus_add2'] = "";
        $post_data['cus_city'] = "";
        $post_data['cus_state'] = "";
        $post_data['cus_postcode'] = "";
        $post_data['cus_country'] = "Bangladesh";
        $post_data['cus_phone'] = '8801XXXXXXXXX';
        $post_data['cus_fax'] = "";

        # SHIPMENT INFORMATION
        $post_data['ship_name'] = "Store Test";
        $post_data['ship_add1'] = "Dhaka";
        $post_data['ship_add2'] = "Dhaka";
        $post_data['ship_city'] = "Dhaka";
        $post_data['ship_state'] = "Dhaka";
        $post_data['ship_postcode'] = "1000";
        $post_data['ship_phone'] = "";
        $post_data['ship_country'] = "Bangladesh";

        $post_data['shipping_method'] = "NO";
        $post_data['product_name'] = "Computer";
        $post_data['product_category'] = "Goods";
        $post_data['product_profile'] = "physical-goods";
        if ($request->selectpackage == "0") {
            $order = new Planorder;
            $order->customer_id = $customer->id;
            $order->store_id = $store->id;
            $order->total_amount = $request->total;
            $order->status = "Processing";
            $order->view = "0";

            if ($request->paymentMethod == 'bkash') {
                $order->method = "bkash";
                $order->number = $request->bkash;
                $order->transaction_id = $request->bkash_transaction_id;
            } elseif ($request->paymentMethod == 'nagad') {
                $order->method = "nagad";
                $order->number = $request->nagad;
                $order->transaction_id = $request->nagad_transaction_id;
            }

            $order->discount = $request->discount;
            $order->addons_price = $request->addons;
            $order->save();

            if ($request->mobileapps) {
                $olds = Addon::where('name', 'mobileapps')->where('store_id', $store->id)->first();
                $addonss = new Addon();
                $addonss->plan_order_id = $order->id;
                $addonss->name = $request->mobileapps;
                $addonss->price = $request->mobileappsmonth * 100;
                $addonss->store_id = $store->id;
                $addonss->status = "Pending";
                $addonss->month = $request->mobileappsmonth;
                $addonss->start_date = Carbon::now();
                $addonss->expiry_date = Carbon::now()->addDays($request->mobileappsmonth * 30);
                $addonss->save();
            }

            if ($request->activitylog) {
                $addonss = new Addon();
                $addonss->plan_order_id = $order->id;
                $addonss->name = $request->activitylog;
                $addonss->price = $request->activitymonth * 50;
                $addonss->store_id = $store->id;
                $addonss->status = "Pending";
                $addonss->month = $request->activitymonth;
                $addonss->start_date = Carbon::now();
                $addonss->expiry_date = Carbon::now()->addDays($request->mobileappsmonth * 30);
                $addonss->save();
            }

            if ($request->adminpanelapps) {
                $addonss = new Addon();
                $addonss->plan_order_id = $order->id;
                $addonss->name = $request->adminpanelapps;
                $addonss->price = $request->adminmobileappsmonth * 100;
                $addonss->store_id = $store->id;
                $addonss->status = "Pending";
                $addonss->month = $request->adminmobileappsmonth;
                $addonss->start_date = Carbon::now();
                $addonss->expiry_date = Carbon::now()->addDays($request->adminmobileappsmonth * 30);
                $addonss->save();
            }

            if ($request->websitesetup) {
                $addonss = new Addon();
                $addonss->plan_order_id = $order->id;
                $addonss->name = $request->websitesetup;
                $addonss->price = 1000;
                $addonss->store_id = $store->id;
                $addonss->status = "Pending";
                $addonss->month = 0;
                $addonss->start_date = Carbon::now();
                $addonss->expiry_date = Carbon::now()->addYears(20);
                $addonss->save();
            }

            if ($request->paymentgateway) {
                $addonss = new Addon();
                $addonss->plan_order_id = $order->id;
                $addonss->name = $request->paymentgateway;
                $addonss->price = 1000;
                $addonss->store_id = $store->id;
                $addonss->status = "Pending";
                $addonss->month = 0;
                $addonss->start_date = Carbon::now();
                $addonss->expiry_date = Carbon::now()->addYears(20);
                $addonss->save();
            }
        } else {
            $order = new Planorder;
            if (isset ($request->selectpackage) || $request->selectpackage != "") {
                $order->plan_id = $request->selectpackage;
                $order->active_date = Carbon::now();
                $exp = $request->month * 30;
                $order->expiry_date = Carbon::now()->addDays($exp);
                $order->total_month = $request->month;
            }

            $order->customer_id = $customer->id;
            $order->store_id = $store->id;
            $order->total_amount = $request->total;
            $order->status = "Processing";
            $order->view = "0";

            if (isset ($request->pos_plan_id) || $request->pos_plan_id != "") {
                $order->pos_plan_id = $request->pos_plan_id;
                $order->pos_plan_start_date = Carbon::now();
                $pos_exp = (int)$request->pos_plan_month * 30;
                $order->pos_plan_expiry_date = Carbon::now()->addDays($pos_exp);
                $order->pos_plan_month = $request->pos_plan_month;
            }

            if (isset ($request->digital_plan_id) || $request->digital_plan_id != "") {
                $order->digital_plan_id = $request->digital_plan_id;
                $order->digital_plan_start_date = Carbon::now();
                $digital_exp = (int)$request->digital_plan_month * 30;
                $order->digital_plan_expiry_date = Carbon::now()->addDays($digital_exp);
                $order->digital_plan_month = $request->digital_plan_month;
            }

            if ($request->paymentMethod == 'bkash') {
                $order->method = "bkash";
                $order->number = $request->bkash;
                $order->transaction_id = $request->bkash_transaction_id;
            } elseif ($request->paymentMethod == 'nagad') {
                $order->method = "nagad";
                $order->number = $request->nagad;
                $order->transaction_id = $request->nagad_transaction_id;
            } else {
                $order->method = "Free";
                $order->number = $request->nagad;
                $order->transaction_id = $request->nagad_transaction_id;
            }

            $order->discount = $request->discount;
            $order->addons_price = $request->addons;
            $order->save();

            if ($request->mobileapps) {
                $addonss = new Addon();
                $addonss->plan_order_id = $order->id;
                $addonss->name = $request->mobileapps;
                $addonss->price = $request->mobileappsmonth * 100;
                $addonss->store_id = $store->id;
                $addonss->status = "Pending";
                $addonss->month = $request->mobileappsmonth;
                $addonss->start_date = Carbon::now();
                $addonss->expiry_date = Carbon::now()->addDays($request->mobileappsmonth * 30);
                $addonss->save();
            }

            if ($request->activitylog) {
                $addonss = new Addon();
                $addonss->plan_order_id = $order->id;
                $addonss->name = $request->activitylog;
                $addonss->price = (int)$request->activitymonth * 50;
                $addonss->store_id = $store->id;
                $addonss->status = "Pending";
                $addonss->month = $request->activitymonth;
                $addonss->start_date = Carbon::now();
                $addonss->expiry_date = Carbon::now()->addDays($request->mobileappsmonth * 30);
                $addonss->save();
            }

            if ($request->adminpanelapps) {
                $addonss = new Addon();
                $addonss->plan_order_id = $order->id;
                $addonss->name = $request->adminpanelapps;
                $addonss->price = (int)$request->adminmobileappsmonth * 100;
                $addonss->store_id = $store->id;
                $addonss->status = "Pending";
                $addonss->month = $request->adminmobileappsmonth;
                $addonss->start_date = Carbon::now();
                $addonss->expiry_date = Carbon::now()->addDays($request->adminmobileappsmonth * 30);
                $addonss->save();
            }

            if ($request->websitesetup) {
                $addonss = new Addon();
                $addonss->plan_order_id = $order->id;
                $addonss->name = $request->websitesetup;
                $addonss->price = 1000;
                $addonss->store_id = $store->id;
                $addonss->status = "Pending";
                $addonss->month = 0;
                $addonss->start_date = Carbon::now();
                $addonss->expiry_date = Carbon::now()->addYears(20);
                $addonss->save();
            }

            if ($request->paymentgateway) {
                $addonss = new Addon();
                $addonss->plan_order_id = $order->id;
                $addonss->name = $request->paymentgateway;
                $addonss->price = 1000;
                $addonss->store_id = $store->id;
                $addonss->status = "Pending";
                $addonss->month = 0;
                $addonss->start_date = Carbon::now();
                $addonss->expiry_date = Carbon::now()->addYears(20);
                $addonss->save();
            }
        }

        $url = "https://admin.ebitans.com";
        return $url;
    }


    public function addonsBuy(Request $request)
    {
        if ($request->addons == "" && $request->combo_packages == "" && $request->plan_id == "") {
            return response()->json(['warning' => 'Please Select any Package or Addons.']);
        }

        $tokens = Paymenttoken::where('token', $request->token)->first();
        $user_id = $tokens->uid;
        $customer = Customer::where('uid', $user_id)->first();
        $store = Store::where('id', $customer->active_store)->first();

        $currency_id = $store->currency;

        $addonsOrder = new AddonsOrder();
        if ($request->payment_method == 'bkash' || $request->payment_method == 'nagad') {
            $currency_id = 1;
        }

        $addonsOrder->user_id = $user_id;
        $addonsOrder->store_id = $store->id;
        $addonsOrder->currency_id = $currency_id;
        $addonsOrder->addons = $request->addons;
        $addonsOrder->payment_method = $request->payment_method;
        $addonsOrder->payment_number = $request->payment_number;
        $addonsOrder->transaction_id = $request->transaction_id;
        $addonsOrder->combopackages = $request->combo_packages;
        $addonsOrder->plan_id = $request->plan_id;
        $addonsOrder->plan_month = $request->plan_month;
        $addonsOrder->plan_type = $request->plan_type;
        $addonsOrder->total = $request->total;
        $addonsOrder->coupon = $request->coupon;
        $addonsOrder->plan_check = $request->plan_check;
        $addonsOrder->status = 'Processing';
        $addonsOrder->save();

        if ($request->plan_id == 6) {
            $addonsOrder->status = 'Complete';
            $store->status = 'active';
            if ($addonsOrder->plan_type == 'website') {
                $store->plan_status = "active";
                $store->purchase_date = Carbon::now();
                if (Carbon::parse($store->expiry_date) <= Carbon::now() || $store->plan_id == 6 || $addonsOrder['plan_check'] == 1) {
                    $store->plan_id = $addonsOrder->plan_id;
                    $store->expiry_date = Carbon::now()->addMonths($addonsOrder->plan_month);

                    $store->upcoming_plan_id = null;
                    $store->upcoming_plan_month = null;
                    $store->upcoming_plan_purchase_date = null;
                    $store->upcoming_plan_expiry_date = null;
                } else {
                    $store->upcoming_plan_id = $addonsOrder->plan_id;
                    $store->upcoming_plan_month = $addonsOrder->plan_month;
                    $store->upcoming_plan_purchase_date = Carbon::parse($store->expiry_date)->addMonths(1);
                    $store->upcoming_plan_expiry_date = Carbon::parse($store->expiry_date)->addMonths($addonsOrder->plan_month);
                }

                if ($addonsOrder->plan_id == 6) {
                    $store->url = $store->slug . '.ebitans.com';
                }
            }

            $store->update();
            $addonsOrder->update();
        }

        if ($addonsOrder->payment_method == 'bkash' && $request->total != 0) {
            $addonsOrder->status = 'Failed';
            $addonsOrder->update();

            $url = 'https://admin.ebitans.com/api/v2/admin/bkash/checkout-url/orderPay?order=' . $addonsOrder->id;
            return response()->json(['addonsOrder' => $addonsOrder, 'url' => $url]);
        }

        return response()->json(['addonsOrder' => $addonsOrder]);
    }

    public function paymentHistory(Request $request)
    {
        $tokens = Paymenttoken::where('token', $request->token)->first();

        if (empty ($tokens)) {
            return [
                'status' => '201',
                'message' => 'Invalid token bro'
            ];
        }

        $user_id = $tokens->uid;
        $customer = Customer::where('uid', $user_id)->first();
        $store = Store::where('id', $customer->active_store)->first();

        $data['history'] = AddonsOrder::where('store_id', $store->id)->get();
        $addonsInfos = AddonsOrder::where('store_id', $store->id)->get();

        $orderHistory = []; // Initialize $orderHistory here

        foreach ($addonsInfos as $key => $dm) {
            $orderHistory[$key]['id'] = $dm->id;
            if (!empty ($dm->plan_id)) {
                $orderHistory[$key]['PlanName'] = $dm->plan_type;
            } else {
                if (isset ($dm->combopackages)) {
                    foreach ($dm->combopackages as $y => $ite) {
                        if ($y) {
                            $orderHistory[$key]['PlanName'] = $orderHistory[$key]['PlanName'] . ' + ' . $ite['type'];
                        } else {
                            $orderHistory[$key]['PlanName'] = $ite['type'];
                        }
                    }
                } else {
                    $orderHistory[$key]['PlanName'] = 'No packages selected';
                }
            }

            if (!empty ($dm->plan_id)) {
                $orderHistory[$key]['PackageName'] = $dm->PlanName->name ?? '';
            } else {
                if (isset ($dm->combopackages)) {
                    foreach ($dm->combopackages as $y => $ite) {
                        $planName = DB::table('plans')->where('id', $ite['id'])->first();
                        if ($y) {
                            $orderHistory[$key]['PackageName'] = $orderHistory[$key]['PackageName'] . ' + ' . $planName->name ?? '';
                        } else {
                            $orderHistory[$key]['PackageName'] = $planName->name ?? '';
                        }
                    }
                } else {
                    $orderHistory[$key]['PackageName'] = 'No packages selected';
                }
            }

            if (!empty ($dm->plan_id)) {
                $orderHistory[$key]['Month'] = $dm->PlanName->name ?? '';
            } else {
                if (isset ($dm->combopackages)) {
                    foreach ($dm->combopackages as $y => $ite) {
                        $planName = DB::table('plans')->where('id', $ite['id'])->first();
                        if ($y) {
                            $orderHistory[$key]['Month'] = $orderHistory[$key]['Month'] . ' + ' . $ite['month'] ?? '';
                        } else {
                            $orderHistory[$key]['Month'] = $ite['month'] ?? '';
                        }
                    }
                } else {
                    $orderHistory[$key]['Month'] = 'No packages selected';
                }
            }

            $orderHistory[$key]['TotalAmount'] = $dm->total;
            $orderHistory[$key]['PaymentMethod'] = $dm->payment_method;
            $orderHistory[$key]['TransactionId'] = $dm->transaction_id;
            $orderHistory[$key]['PaymentNumber'] = $dm->payment_number;

            if (isset ($dm->addons) && count($dm->addons) > 0) {
                foreach ($dm->addons as $addonsKye => $adonsItem) {
                    $orderHistory[$key]['Addons'][$addonsKye]['title'] = $adonsItem['title'];
                    $orderHistory[$key]['Addons'][$addonsKye]['name'] = $adonsItem['name'];
                    $orderHistory[$key]['Addons'][$addonsKye]['months'] = $adonsItem['months'];
                    $orderHistory[$key]['Addons'][$addonsKye]['type'] = $adonsItem['type'];
                    $orderHistory[$key]['Addons'][$addonsKye]['price'] = $adonsItem['price'];
                }
            }

            $orderHistory[$key]['Status'] = $dm->status;
            $orderHistory[$key]['CreateDate'] = date('d-m-Y H:m:s', strtotime($dm->created_at));
        }

        return response()->json(['data' => $orderHistory]);
    }

    public function initialactiveplan(Request $request)
    {
        $tokens = Paymenttoken::where('token', $request->token)->first();
        $user = $tokens->uid;
        $customer = Customer::where('uid', $user)->first();
        $store = Store::where('user_id', $user)->where('id', $customer->active_store)->first();
        $str = Store::find($store->id);
        if ($str->purchase_date == "0000-00-00") {
            if (isset ($request->website_plan_id) || $request->website_plan_id != "") {
                $str->plan_id = $request->website_plan_id;
                $str->month = $request->website_month;
                $str->purchase_date = Carbon::now();
                $str->expiry_date = Carbon::now()->addDays(7);
            }
            if (isset ($request->pos_plan_id) || $request->pos_plan_id != "") {
                $str->pos_plan_id = $request->pos_plan_id;
                $str->pos_plan_start_date = Carbon::now();
                $str->pos_plan_expiry_date = Carbon::now()->addDays(7);
            }
            if (isset ($request->digital_plan_id) || $request->digital_plan_id != "") {
                $str->digital_plan_id = $request->digital_plan_id;
                $str->digital_plan_start_date = Carbon::now();
                $str->digital_plan_end_date = Carbon::now()->addDays(7);
            }
            $str->trail = 0;
            $str->plan_status = "active";
            $str->status = "active";
            $str->save();
            $custo = Customer::find($customer->id);
            $custo->active_store = $str->id;
            $custo->save();
            return "https://admin.ebitans.com";
        }
    }

    public function activepage(Request $request)
    {
        $payt = Paymenttoken::where("token", $request->token)->first();
        if (isset ($payt)) {
            $user = User::find($payt->uid);
            if ($user->type == 'admin') {
                $customer = Customer::where('uid', $user->id)->first();
                $store_id = $customer->active_store;
            } elseif ($user->type == 'staff') {
                $staff = Staff::where('uid', $user->id)->first();
                $store_id = $staff->store_id;
            }
            $store = Store::find($store_id);

            $planInfo['trail'] = $store->trail ?? 0;
            $planInfo['plan_id'] = $store->plan_id ?? 0;
            $planInfo['upcoming_plan'] = $store->upcoming_plan_id ?? 0;
            $plan = AddonsOrder::where('store_id', $store->id)->where('status', 'Processing')->orderBy('id',
                'DESC')->first();

            $planInfo['payment_method'] = $plan->payment_method ?? 0;
            if (isset ($plan)) {
                return [
                    'data' => 'deactive',
                    'planInfo' => $planInfo
                ];
            } else {
                $plan = AddonsOrder::where('store_id', $store->id)->where('status', '!=',
                    'Processing')->orderBy('id', 'DESC')->first();

                $planInfo['payment_method'] = $plan->payment_method ?? 0;
                return [
                    'data' => 'active',
                    'planInfo' => $planInfo
                ];
            }
        } else {
            return [
                'url' => 'https://admin.ebitans.com/login'
            ];
        }
    }

    public function deactivestore(Request $request)
    {
        $payt = Paymenttoken::where("token", $request->token)->first();
        if (isset ($payt)) {
            $customer = Customer::where('uid', $payt->uid)->first();
            $store = Store::where('id', $customer->active_store)->first();
            $store->status = "deactive";
            $store->save();
            $custom = Customer::find($customer->id);
            $custom->active_store = "0";
            $custom->save();
            return [
                'url' => 'https://admin.ebitans.com/store'
            ];
        } else {
            return [
                'url' => 'https://admin.ebitans.com/login'
            ];
        }
    }

    public function addons()
    {
        $addons = AddonsApi::all();
        $url = 'addons/';
        return response()->json(['url' => $url, 'addons' => $addons]);

    }
}
