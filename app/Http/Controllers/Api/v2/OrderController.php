<?php

namespace App\Http\Controllers\Api\v2;

use App\Helpers\CheckClientSms;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResourceV2;
use App\Mail\OPTSendMail;
use App\Models\AccountJournal;
use App\Models\Address;
use App\Models\Booking;
use App\Models\Coupon;
use App\Models\Currency;
use App\Models\Design;
use App\Models\Headersetting;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Orderitem;
use App\Models\OrderStatus;
use App\Models\Prereguser;
use App\Models\Product;
use App\Models\ProductAffiliateCommission;
use App\Models\ProductAffiliateInfo;
use App\Models\Review;
use App\Models\Store;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Veriant;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;

class OrderController extends Controller
{
    public function placeorder(Request $request)
    {
        try {
            $store = Store::find($request->store_id);

            if (!isset($store)) {
                return sendError("Invalid request");
            }

            if (isset($store->plan_id) && in_array($store->plan_id, [6, 9])) {
                return sendError("Order access denied");
            }

            if (!isset($store->currency)) {
                $store->currency = 1;
            }

            $authenticate = false;

            if (!is_array($request->product)) {
                return sendError('Invalid product list.');
            }

            foreach ($request->product as $key => $item) {
                $productId = $item['id'] ?? null;
                $variantId = $item['variant_id'] ?? null;
                $quantityRequested = $item['quantity'] ?? 0;

                if (!$productId || $quantityRequested <= 0) {
                    return sendError("Invalid product data at index $key");
                }


                $variant = Veriant::convertCurrency($productId, $request->store_id)
                    ->where('veriants.pid', $productId)
                    ->where('veriants.id', $variantId)
                    ->first();

                if (isset($variant)) {
                    if ($variant->quantity < $quantityRequested) {
                        $product = Product::where('id', $productId)->first();
                        $er = "Quantity not exist for " . $product->name;
                        return sendError($er);
                    }
                } else {
                    $product = Product::where('id', $productId)->first();
                    if (!$product || $product->quantity < $quantityRequested) {
                        return sendError("Quantity not available for " . ($product->name ?? 'Product'));
                    }
                }
            }

            $user_id = "";
            if (Auth::guard('sanctum')->check()) {
                $user = Auth::guard('sanctum')->user();
                $user_id = $user->id;
                $authenticate = true;
            }

            if (empty($request->phone) && empty($request->email)) {
                return sendError("Phone/Email is required");
            }

            $validation = $this->userInfoValidation($request);
            if ($validation) {
                return sendError($validation);
            }

            $user = User::where('id', $user_id)->where('store_id', $request->store_id)->first();

            $phone = !empty($request->phone) ? $request->phone : null;
            $email = !empty($request->email) ? strtolower($request->email) : null;

            if (!isset($user) || is_null($user)) {
                $user = User::where('store_id', $request->store_id)
                    ->where(function ($q) use ($phone, $email) {
                        if ($phone) {
                            $q->where('phone', '=', $phone);
                        }
                        if ($email) {
                            $q->orWhere('email', '=', strtolower($email)); // case-insensitive
                        }
                    })
                    ->first();
            }

            if (isset($user)) {
                $uid = $user->id;
            } else {
                $store = Store::find($request->store_id);
                $user = new User;
                $user->phone = $request->phone;
                $user->email = $request->email;
                $user->currency_id = $store->currency ?? 1;
                $code = sixDigitRandCode();
                $pass = trim($store->name, ' ') . "@" . $code;
                $newpass = Hash::make($pass);
                $user->password = $newpass;
                $user->type = "customer";
                $otp = sixDigitRandCode();

                if ($store->auth_type == "EasyOrder" || $store->auth_type == "EmailEasyOrder") {
                    $user->otp = 'NULL';
                    $user->email_verified_at = date("Y-m-d H:i:s");
                } else {
                    $user->otp = $otp;
                }

                $user->auth_type = $store->auth_type;
                $user->store_id = $store->id;
                $user->customer_id = $store->customer_id;
                $user->save();

                $notificationData = [
                    "title" => "New customer register (" . getUserNameOrPhone($user) . ") - " . formatDateWithTime($user->created_at),
                    "type" => "user_create",
                    "user_type" => "admin",
                    "store_id" => $store->id,
                ];

                if (isset($notificationData['title']) && !empty($notificationData['title'])) {
                    createNotification($notificationData);
                }

                $text = ($store->name ?? "Your") . " OTP code is " . $user->otp;

                // Create an instance of CheckClientSms
                $smsChecker = new CheckClientSms($store->id, 5);
                // Check the SMS limit
                $isLimitReached = $smsChecker->checkSmsLimit();

                if (addonSmsCount($store->id) && isset($user->phone) && !empty($user->phone)) {
                    // Send SMS if the limit not 0
                    if ($isLimitReached && is_null($user->email_verified_at)) {
                        $smsresult = SendSms($user->phone, $text); // phone text
                        $p = explode("|", $smsresult);
                        $sendstatus = $p[0];

                        smsLogger($user->phone, $text, "OTP Send", 0, $store->id);
                    }
                }

                $validEmail = false;
                if (filter_var($request->email, FILTER_VALIDATE_EMAIL)) {
                    $validEmail = true;
                }

                $headersetting = Headersetting::where('store_id', $store->id)->first();

                if (isset($request->email) && !empty($request->email) && $validEmail && is_null($user->email_verified_at)) {
                    if (!is_null($headersetting->email) && !empty($headersetting->email)) {
                        $data['email'] = $request->email;
                        $data['FormEmail'] = $headersetting->email;
                        $data['orderInfo'] = $text;
                        $data["title"] = ucfirst($store->name);
                        $data["subtitle"] = "Registration OTP";

                        Mail::send('clientOrderNotifyMail', $data, function ($message) use ($data) {
                            $message->from($data['FormEmail'], $data["title"])->to($data["email"], $data["email"])
                                ->subject($data["subtitle"]);
                        });
                    }
                }

                $text = "Thank You for register to " . $store->name . " Your Login Details is Phone : " . $user->phone . " Password : " . $pass;

                if (addonSmsCount($store->id) && isset($user->phone) && !empty($user->phone)) {
                    // Send SMS if the limit not 0
                    if ($isLimitReached) {
                        $smsresult = SendSms($user->phone, $text); // phone text
                        $p = explode("|", $smsresult);
                        $sendstatus = $p[0];

                        smsLogger($user->phone, $text, "Customer Registration Details", 0, $store->id);
                    }
                }

                if (isset($request->email) && !empty($request->email) && $validEmail) {
                    if (!is_null($headersetting->email) && !empty($headersetting->email)) {
                        $data['email'] = $request->email;
                        $data['FormEmail'] = $headersetting->email;
                        $data['orderInfo'] = $text;
                        $data["title"] = ucfirst($store->name);
                        $data["subtitle"] = "Welcome to " . (ucfirst($store->name) ?? "");

                        Mail::send('clientOrderNotifyMail', $data, function ($message) use ($data) {
                            $message->from($data['FormEmail'], $data["title"])->to($data["email"], $data["email"])
                                ->subject($data["subtitle"]);
                        });
                    }
                }

                $uid = $user->id;

                $addressData = $this->saveUserAddress($request, $uid);
                $request->address_id = $addressData->id ?? NULL;
            }

            // Check coupon start
            if (!is_null($request->coupon) && !empty($request->coupon)) {
                $orderCoupon = Order::where('uid', $uid)->where('coupon', $request->coupon)->where('store_id', $store->id)->count();
                $coupon = Coupon::where('store_id', $store->id)->where('status', 'active')->where('code', $request->coupon)->whereDate('end_date', '>=', Carbon::today()->toDateString())->first();

                if (isset($coupon) && $coupon->max_use <= $orderCoupon) {
                    return sendError("Sorry! You exit the MAXIMUM Coupon limit.");
                }
            }
            // Check coupon end

            $lastOrder = Order::where('store_id', $store->id)->latest('order_no')->first();
            $newOrderNo = $lastOrder ? $lastOrder->order_no + 1 : 1;

            $order = new Order();
            $current_currency = Currency::find($store->currency);
            $order->uid = $uid;
            $order->subtotal = $request->subtotal;
            $order->tax = $request->tax;
            $order->currency_id = $store->currency;
            $order->shipping = $request->shipping;
            $order->discount = $request->discount;
            $order->due = $request->total;
            $order->total = $request->total;
            $order->reference_no = generateShortReferenceNo();
            $order->order_no = $newOrderNo;
            $order->name = $request->name;
            $order->phone = $request->phone;
            $order->address = $request->address ?? "";
            $order->note = $request->note;
            $order->district = $request->district ?? NULL;
            $order->address_id = $request->address_id ?? NULL;

            $sessionId = $request->header('X-Session-ID') ?? NULL;
            $ip = $request->ip();
            $order->session_id = $sessionId;
            $order->ip = $ip;

            if (ModulusStatus($store->id, 108)) {
                if ($request->from_type == 1 || $request->from_type == 0) {
                    $order->status = "Booked";
                }
            } else {
                $order->status = "Pending";
            }

            $order->creator = $uid;
            $order->editor = $uid;
            $order->customer_id = $store->customer_id;
            $order->store_id = $store->id;
            $order->type = "customer";
            $order->coupon = $request->coupon;
            $order->save();

            $booking = new Booking();
            $booking->user_id = $uid;
            $booking->store_id = $store->id;
            $booking->order_id = $order->id;
            $booking->name = $request->name;
            $booking->phone = $request->phone;
            $booking->email = $request->email;
            $booking->date = $request->date;
            $booking->start_date = $request->start_date;
            $booking->end_date = $request->end_date;
            $booking->pickup_location = $request->pickup_location;
            $booking->drop_location = $request->drop_location;
            $booking->time = $request->time;
            $booking->comment = $request->comment;
            $booking->save();

            foreach ($request->product as $key => $item) {
                $orderItem = new Orderitem();
                if (!empty($item['items'][0])) {
                    if (!empty($item['items'][0]['files'])) {
                        foreach ($item['items'][0]['files'] as $lk => $fil) {
                            $file = $fil;
                            $fileName = mt_rand(100, 999) + time() . '.' . $fil->extension();
                            $file->move('orders/', $fileName);
                            $orderImage[$lk] = $fileName;
                        }

                        if (!empty($orderImage)) {
                            $orderItem->orderfiles = implode(",", $orderImage);
                            $orderImage = [];
                        }
                    }

                    if (!empty($item['items'][0]['description'])) {
                        $orderItem->sampleDescription = $item['items'][0]['description'];
                    }
                }

                $variant = Veriant::convertCurrency($item['id'], $request->store_id)
                    ->where('veriants.pid', $item['id'])
                    ->where('veriants.id', $item['variant_id'])
                    ->first();

                $orderItem->product_id = $item['id'];
                $orderItem->order_id = $order->id;
                $orderItem->currency_id = $store->currency;
                $orderItem->price = $item['price'] ?? 0;
                $orderItem->quantity = $item['quantity'];

                $orderItem->color = $variant->color ?? '';
                $orderItem->size = $variant->size ?? '';
                $orderItem->additional_price = $variant->additional_price ?? 0;
                $orderItem->unit = $variant->unit ?? '';
                $orderItem->volume = $variant->volume ?? '';

                $orderItem->discount = $item['discount'] ?? 0;
                $product = Product::where('id', $item['id'])->first();
                $orderItem->cost = $product->cost ?? 0;

                $snapshot = [
                    'name' => $product->name,
                    'SKU' => $product->SKU ?? null,
                    'regular_price' => $product->regular_price ?? null,
                ];

                $orderItem->product_snapshot = json_encode($snapshot);

                $orderItem->variant_id = $variant->id ?? NULL;

                $orderItem->save();

                if (isset($item['referral_code']) && !is_null($item['referral_code'])) {
                    $info = ProductAffiliateInfo::where('referral_code', $item['referral_code'])->first();

                    if ($info) {
                        $commissionPercent = (float)$info->commission_percent;
                        $productOrderPrice = $this->calculateProductAmount($item);
                        $commission_amount = ($productOrderPrice * $commissionPercent / 100);
                        $info->total_earning = (float)$info->total_earning + $commission_amount;
                        $info->final_amount = (float)$info->final_amount + $commission_amount;
                        $info->save();

                        $commission = new ProductAffiliateCommission();
                        $commission->affiliate_user_id = $info->user_id;
                        $commission->order_id = $order->id;
                        $commission->product_id = $item['id'];
                        $commission->product_price = $productOrderPrice;
                        $commission->store_id = $info->store_id;
                        $commission->commission_percent = $commissionPercent;
                        $commission->amount = $commission_amount;
                        $commission->currency = $current_currency->code;
                        $commission->save();
                    }
                }

                if (isset($product)) {
                    $product->quantity = $product->quantity - $item['quantity'];
                    $product->save();
                }

                if (isset($variant)) {
                    $variant->quantity = $variant->quantity - $item['quantity'];
                    $variant->save();
                }
            }

            $transaction = new Transaction();
            $transaction->uid = $uid;
            $transaction->order_id = $order->id ?? '';
            $transaction->mode = $request->payment_type ?? '';
            $transaction->status = "pending";
            $transaction->save();

            $invoiceNo = generateInvoiceNo();
            $invoice = new Invoice;
            $invoice->reference_no = $invoiceNo;
            $invoice->order_id = $order->id;
            $invoice->type = "Website";
            $invoice->uid = $uid;
            $invoice->customer_id = $store->customer_id;
            $invoice->store_id = $store->id;
            $invoice->creator = $uid;
            $invoice->editor = $uid;
            $invoice->save();

            $text = sendOrderConfirmationText($store, $order);

            if (isset($store->plan_id) && ($store->plan_id == 8 || $store->plan_id == 9)) {
                if (isset($store->order_pull) && $store->order_pull == 0) {
                    AccountJournal::saveJournal($order); // Place dropshipper order in account journal
                }
            }

            // Create an instance of CheckClientSms
            $smsChecker = new CheckClientSms($store->id, 5);
            // Check the SMS limit
            $isLimitReached = $smsChecker->checkSmsLimit();

            // Send SMS if the limit not 0
            if ($isLimitReached) {
                if (addonSmsCount($store->id) && isset($user->phone) && !empty($user->phone)) {
                    $smsresult = SendSms($user->phone, $text); // phone, text
                    smsLogger($user->phone, $text, "Order Confirmation", 0, $store->id);
                }
            }

            $headersetting = Headersetting::where('store_id', $store->id)->first();
            if (isset($headersetting) && isset($headersetting->email)) {
                $data['email'] = $user->email;
                $data['FormEmail'] = $headersetting->email;
                $data['orderInfo'] = $text;
                $data["title"] = ucfirst($store->name);

                if (isset($user->email)) {
                    if (filter_var($user->email, FILTER_VALIDATE_EMAIL)) {
                        try {
                            Mail::send('clientOrderNotifyMail', $data, function ($message) use ($data) {
                                $message->from($data['FormEmail'], $data["title"])->to($data["email"], $data["email"])
                                    ->subject('Order Placed');
                            });
                        } catch (\Exception $e) {
                            // Log or handle the error
//                            Log::error('Mail sending failed: ' . $e->getMessage());
                        }
                    } else {
//                        Log::error('Invalid email address: ' . $user->email);
                    }
                }

                if (ModulusStatus($store->id, 4)) {
                    $data['orderInfo'] = "New Order has been placed to " . $store->name . ". \nOrder Id: " . $order->reference_no . "\nPrice: " . $order->total;
                    $data['email'] = $headersetting->email;
                    $data['FormEmail'] = env("MAIL_FROM_ADDRESS") ?? $headersetting->email;
                    if (filter_var($headersetting->email, FILTER_VALIDATE_EMAIL)) {
                        try {
                            Mail::send('clientOrderNotifyMail', $data, function ($message) use ($data) {
                                $message->from($data['FormEmail'], $data["title"])
                                    ->to($data["email"])
                                    ->subject('Order placed');
                            });
                        } catch (\Exception $e) {
                            // Log or handle the error
//                            Log::error('Mail sending failed: ' . $e->getMessage());
                        }
                    } else {
//                        Log::error('Invalid email address: ' . $headersetting->email);
                    }

                }
            }


            // Create notification
            $orderURL = route("admin.order.view", ['id' => $order->id ?? ""]);
            $notificationData = [
                "title" => "New Order Placed (" . ($order->reference_no ?? '') . ") - " . formatDateWithTime($order->created_at),
                "type" => "store_order",
                "user_type" => "admin",
                "store_id" => $store->id ?? NULL,
                "link" => $orderURL,
            ];

            if (isset($notificationData['title']) && !empty($notificationData['title'])) {
                createNotification($notificationData);
            }

            Session::forget('payment_amount');
            Session::put('payment_amount', $request->total);
            Session::forget('invoice');
            Session::put('invoice', $invoiceNo);

            $url = null;

            if ($request->payment_type == 'bkash') {
                $url = route('bkash.payment') . '?order=' . $order->id;
            } elseif ($request->payment_type == 'online') { // this is foe ssl
                $url = route('ssl.create-payment') . "?order_id=$order->id&store_id=$store->id";
            } elseif ($request->payment_type == 'amarpay') {
                $url = route('amarpay.payment') . '?order_id=' . $order->id;
            } elseif ($request->payment_type == 'merchant_bkash') {
                $url = route('ebitans-bkash.payment') . '?order_id=' . $order->id;
            } elseif ($request->payment_type == 'merchant_nagad') {
                $url = route('ebitans-nagad.payment') . '?order_id=' . $order->id;
            } elseif ($request->payment_type == 'uddoktapay') {
                $url = route('uddoktapay.payment') . '?order_id=' . $order->id;
            } elseif ($request->payment_type == 'paypal') {
                $url = route('paypal.payment') . '?order_id=' . $order->id;
            } elseif ($request->payment_type == 'stripe') {
                $url = route('stripe.payment') . '?order_id=' . $order->id;
            } elseif (ModulusStatus($store->id, 106) && $request->payment_type == 'ap') {
                if (isset($headersetting->payment_method)) {
                    if ($headersetting->payment_method == 'bKash') {
                        $url = route('bkash.payment') . '?order=' . $order->id;
                    } elseif ($headersetting->payment_method == 'SSL') { // this is foe ssl
                        $url = route('ssl.create-payment') . "?order_id=$order->id&store_id=$store->id";
                    } elseif ($headersetting->payment_method == 'ebitans_amarpay') {
                        $url = route('amarpay.payment') . '?order_id=' . $order->id;
                    } elseif ($headersetting->payment_method == 'ebitans_bkash') {
                        $url = route('ebitans-bkash.payment') . '?order_id=' . $order->id;
                    } elseif ($headersetting->payment_method == 'ebitans_nagad') {
                        $url = route('ebitans-nagad.payment') . '?order_id=' . $order->id;
                    } elseif ($headersetting->payment_method == 'uddoktapay') {
                        $url = route('uddoktapay.payment') . '?order_id=' . $order->id;
                    } elseif ($headersetting->payment_method == 'paypal') {
                        $url = route('paypal.payment') . '?order_id=' . $order->id;
                    } elseif ($headersetting->payment_method == 'stripe') {
                        $url = route('stripe.payment') . '?order_id=' . $order->id;
                    }
                }
            }

            $responseData['order'] = $order;
            $responseData['url'] = $url;
            if (!$authenticate) {
                $userData['token'] = $user->createToken('AuthToken')->plainTextToken;
                $userData['verify'] = is_null($user->email_verified_at) ? false : true;
                $userData['referral'] = null;
                $userData['user'] = new UserResourceV2($user);
                $responseData['userData'] = $userData;
            }

            return sendResponse("Order placed successfully.", $responseData);
        } catch (\Exception $exception) {
            return serverError();
        }
    }

    public function saveUserAddress($request, $user_id)
    {
        $ad = new Address();
        $ad->name = $request->name ?? "";
        $ad->phone = $request->phone ?? "";
        $ad->email = $request->email ?? "";
        $ad->address = $request->address ?? "";
        $ad->note = $request->note ?? "";
        $ad->district_id = $request->district ?? null;
        $ad->phone_code = $request->phone_code ?? null;
        $ad->uid = $user_id;
        $ad->save();

        return $ad;
    }

    public function userInfoValidation($request)
    {
        $country_code = $request->country_code ?? "BD";

        $rules = [
            'email' => ['string'],
            'phone' => ['string'],
        ];

        $rules['email'][] = 'email';
        $rules['phone'][] = 'phone:' . $country_code; // Basic phone validation

        Validator::extend('phone', function ($attribute, $value, $parameters, $validator) {
            $country = $parameters[0] ?? 'the country';
            return phone($value, [$country]); // This uses the phone validation logic
        }, 'The phone number must be a valid phone number for :country.');

        Validator::replacer('phone', function ($message, $attribute, $rule, $parameters) use ($country_code) {
            $countryName = getCountryName($country_code);
            return str_replace(':country', $countryName, $message);
        });


        $message = [
            'email.email' => 'Enter valid email address.',
        ];

        $validator = Validator::make($request->all(), $rules, $message);


        if ($validator->fails()) {
            $error = $validator->getMessageBag()->getMessages();
            if (isset($request->email) && !empty($request->email) && isset($error['email'][0])) {
                return $error['email'][0];
            }

            if (isset($request->phone) && !empty($request->phone) && isset($error['phone'][0])) {
                return $error['phone'][0];
            }
        }

        if (isset($request->email) && !empty($request->email)) {
            if (!filter_var($request->email, FILTER_VALIDATE_EMAIL)) {
                return "Invalid email address.";
            }
        }

        if (isset($request->phone) && !empty($request->phone)) {
            // Parse the phone number to get only the local number (without country code)
            $phoneUtil = \libphonenumber\PhoneNumberUtil::getInstance();
            $parsedNumber = $phoneUtil->parse($request->phone, $country_code);
            $request->phone = $phoneUtil->getNationalSignificantNumber($parsedNumber);

            if (isset($country_code) && $country_code == "BD") {
                $request->phone = '0' . $request->phone;
            }
        }
    }


    /**
     * calculate product price
     *
     * @param $item
     * @return float
     */
    public function calculateProductAmount($item)
    {
        $productPrice = $item['price'] ?? 0;
        $quantity = (float)$item['quantity'];
        $additional_price = isset($item['additional_price']) && $item['additional_price'] != 'null' ? $item['additional_price'] : 0;
        $discount = $item['discount'] ?? 0;

        $price = ((float)$productPrice + (float)$additional_price - (float)$discount);
        $price = $price * $quantity;
        return $price;
    }


    public function getorder($store)
    {
        try {
            if (empty($store) || is_null($store)) {
                return sendError("Store ID is required", '', 422);
            }

            $order = Order::where('uid', Auth::user()->id)->where('store_id', $store)->get();

            return sendResponse('Success', $order);
        } catch (\Exception $e) {
            return serverError();
        }
    }

    public function getOrderStatus()
    {
        try {
            $order = OrderStatus::all();

            return sendResponse('Success', $order);
        } catch (\Exception $e) {
            return serverError();
        }
    }

    public function orderdetails($id)
    {
        try {
            if (empty($id) || is_null($id)) {
                return sendError("Order ID is required", '', 422);
            }

            $order = Order::where('id', $id)->where('uid', Auth::user()->id)->first();

            if ($order) {
                $orderiem = Orderitem::where('order_id', $order->id)->get();
                $transaction = Transaction::where('order_id', $order->id)->first();
                $booking = Booking::where('order_id', $order->id)->where('store_id', $order->store_id)->first();

                $order = [
                    'order' => $order,
                    'orderitem' => $orderiem,
                    'transaction' => $transaction,
                    'booking' => $booking
                ];

                return sendResponse('Success', $order);
            }

            return sendError('Order not found');
        } catch (\Exception $e) {
            return serverError();
        }
    }

    public function cancelorder(Request $request)
    {
        try {
            if (!Auth::check()) {
                return sendError("Unauthorized access.", [], 401);
            }

            $id = $request->order_id ?? "";
            if (empty($id) || is_null($id)) {
                return sendError("Order ID is required", '', 422);
            }

            $order = Order::where('id', $id)->where('uid', Auth::user()->id)->first();
            if (isset($order->status) && ($order->status == 'Delivered' || $order->status == 'Shipping')) {
                return sendError("You can not cancel this order now. Order already Shipped!", '', 422);
            }

            if (isset($order)) {
                $order->status = "Cancelled";
                $order->save();

                $orderItems = Orderitem::where("order_id", $order->id)->get();
                foreach ($orderItems as $orderItem) {
                    if (isset($orderItem->variant_id)) {
                        $variant = Veriant::where("id", $orderItem->variant_id)->first();
                        if (isset($variant)) {
                            $variant->quantity = (float)$variant->quantity + (float)$orderItem->quantity;
                            $variant->save();
                        }
                    }

                    if (isset($orderItem->product_id)) {
                        $product = Product::where("id", $orderItem->product_id)->first();
                        if (isset($product)) {
                            $product->quantity = (float)$product->quantity + (float)$orderItem->quantity;
                            $product->save();
                        }
                    }
                }

                return sendResponse("Successfully Cancel Order");
            } else {
                return sendError("Order not found");
            }

        } catch (\Exception $exception) {
            serverError();
        }
    }

    public function review(Request $request)
    {
        $order = Order::find($request->order_id);
        if ($order->status == 'Delivered') {
            $review = new Review();
            $review->uid = Auth::user()->id;
            $review->order_id = $request->order_id;
            $review->product_id = $request->product_id;
            $user = User::find(Auth::user()->id);
            $review->name = $user->name ?? "";
            $review->comment = $request->comment;
            $review->rating = $request->rating;
            $review->store_id = $request->store_id;
            $review->save();
            $oitm = Orderitem::where('product_id', $request->product_id)->where('order_id',
                $request->order_id)->get();
            foreach ($oitm as $itm) {
                $ot = Orderitem::where('id', $itm->id)->first();
                $ot->review = 1;
                $ot->save();
            }
            return response()->json(['success' => ' Successfully Submitted Review']);
        } else {
            return response()->json(['error' => ' Invalid']);
        }
    }
}
