<?php

namespace App\Http\Controllers\Api\v2;

use App\Http\Controllers\Controller;
use App\Models\Headersetting;
use App\Models\ProductAffiliateCommission;
use App\Models\ProductAffiliateInfo;
use App\Models\ProductAffiliateWithdrawRequest;
use App\Models\Store;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ProductAffiliateController extends Controller
{
    /**
     * get product affiliate users
     *
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View
     */

    public function getUsers(Request $request)
    {
        $urls = 'product_affiliate_users';

        $userData = getUserData();
        $store_id = $userData['store_id'] ?? "";


        // Step 1: Paginate the ProductAffiliateInfo model
        $affiliates = DB::table('product_affiliate_infos as info')
            ->select(
                'info.id',
                'info.user_id',
                'info.referral_code',
                'info.commission_percent',
                'info.status',
                'users.name',
                'users.phone',
                'users.email',
                'users.created_at'
            )
            ->join('users', 'users.id', '=', 'info.user_id')
            ->where('info.store_id', $store_id)
            ->paginate(10); // Paginate affiliates
        return view('admin.productAffiliateMarketing.users.index', compact('affiliates', 'urls'));

    }

    public function getUserCommissions(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()->all()]);
        }

        $commissions = ProductAffiliateCommission::with('product', 'order')
            ->where('affiliate_user_id', $validator->validated()['user_id'])
            ->orderBy('id', "DESC")
            ->paginate(10); // Adjust commission pagination
        if (count($commissions) > 0) {
            return response()->json(['success' => true, 'data' => $commissions]);
        }
        return response()->json(['success' => false, 'data' => []]);
    }


    // Operations

    /**
     * change affiliate user status
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \Illuminate\Validation\ValidationException
     */

    public function changeUserStatus(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required',
        ], [
            'id.required' => 'User Not Found.',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors()->toJson(), 400);
        }
        $info = ProductAffiliateInfo::find($validator->validated()['id']);
        if (isset($info)) {
            $info->status = !$info->status;
            $info->save();
            return response()->json(['message' => 'User Status Changed Successfully.', 'status' => 200], 200);
        } else {
            return response()->json(['message' => 'User Not Found.'], 404);
        }
    }

    public function changeUserCommission(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|numeric',
            'commission_percent' => 'required|numeric'
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors()->toJson(), 400);
        }
        $info = ProductAffiliateInfo::find($validator->validated()['id']);
        if (!isset($info)) {
            return response()->json(['message' => 'User Not Found.'], 404);
        }
        $info->commission_percent = $validator->validated()['commission_percent'];
        $info->save();
        if (!$info) {
            return response()->json(['message' => 'User Commission Not Set.'], 400);
        }
        return response()->json(['message' => 'User Commission Set Successfully.'], 200);
    }

    public function withdrawRequestOperations(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|numeric',
            'status' => 'required|numeric|between:1,2',
            'comment' => 'string'
        ], [
            'id.required' => 'User Not Found.',
            'id.numeric' => 'Not User ID.',
            'status.required' => 'Status Required.',
            'status.between' => 'Status Between 1 and 2.',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors()->toJson(), 400);
        }
        $withdraw = ProductAffiliateWithdrawRequest::find($validator->validated()['id']);

        if (!isset($withdraw)) {
            return response()->json(['message' => 'User Not Found.'], 404);
        }
        $withdraw->status = $validator->validated()['status'];
        if ($validator->validated()['status'] == 1) {
            $withdraw->admin_id = Auth::id();
            $withdraw->note = $validator->validated()['comment'];
        }
        $info = ProductAffiliateInfo::find($withdraw->affiliate_info_id);
        if (!isset($info)) {
            return response()->json(['message' => 'User Not Found.'], 404);
        }
        $info->final_amount = $info->final_amount - $withdraw->amount;
        $info->save();
        $withdraw->save();

        if ($withdraw) {
            return response()->json(['message' => 'Withdraw Request Change Successfully.', 'status' => 200], 200);
        }
        return response()->json(['message' => 'Withdraw Request Change Failed.'], 404);

    }

    /*both*/
    /**
     * get withdraw requests
     *
     * @param Request $request
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View|\Illuminate\Http\JsonResponse
     */
    public function getWithdrawRequest($status = null)
    {
        try {
            if (!Auth::check()) {
                return sendError("Unauthorized access.", [], 401);
            }

            $user = Auth::user();
            $user_id = $user->id;

            $withdraws = DB::table('product_affiliate_withdraw_requests as withdraw')
                ->select(
                    'withdraw.id',
                    'users.name',
                    'users.phone',
                    'users.email',
                    'withdraw.amount',
                    'withdraw.comment',
                    'withdraw.currency',
                    'currencies.symbol as currency_symbol',
                    'currencies.code as currency_name',
                    'withdraw.created_at',
                    'withdraw.status'
                )
                ->join('product_affiliate_infos as info', 'info.user_id', '=', 'withdraw.affiliate_info_id')
                ->join('users', 'users.id', '=', 'info.user_id')
                ->leftJoin('currencies', 'currencies.id', '=', 'withdraw.currency');

            $withdraws->where('info.user_id', $user_id);
            $withdraws->where('info.status', 1);


            switch ($status) {
                case 'pending':
                    $withdraws->where('withdraw.status', 0)
                        ->orderBy('withdraw.id', 'desc');
                    break;
                case 'approved':
                    $withdraws->where('withdraw.status', 1)
                        ->orderBy('withdraw.id', 'desc');
                    break;
                case 'rejected':
                    $withdraws->where('withdraw.status', 2)
                        ->orderBy('withdraw.id', 'desc');
                    break;
                default:
                    $withdraws->orderBy('withdraw.id', 'desc');
            }
            $withdraws = $withdraws->paginate(10);

            return sendResponse("Successful", $withdraws);

        } catch (\Exception $exception) {
            serverError();
        }

    }

    //api section

    /**
     * Affiliate User Registrations
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \Illuminate\Validation\ValidationException
     */
    public function register(Request $request)
    {
        $rules = [
            'name' => 'required|string',
            'url' => 'required|string',
            'email' => [
                'nullable',
                'email',
                'unique:users,email',
                'required_without:phone' // Email required if phone is not provided
            ],
            'phone' => [
                'nullable',
                'string',
                'unique:users,phone',
                'regex:/^(?:\+?\d{1,3})?[1-9]\d{5,14}$/', // Custom regex for phone number
                'required_without:email' // Phone required if email is not provided
            ],
            'password' => 'required|string|min:8',
            'image' => 'nullable|string',
        ];

        $messages = [
            'name.required' => 'Must be needed Name',
            'name.string' => 'Must be needed Name',
            'url.required' => 'Must be needed Domain URL',
            'url.string' => 'Must be needed Domain URL',
            'email.unique' => 'Email address already exists',
            'email.required_without' => 'Phone or email must be provided',
            'email.email' => 'Email address must be valid',
            'phone.unique' => 'Phone number already exists',
            'phone.required_without' => 'Phone or email must be provided',
            'phone.string' => 'Phone number must be provided',
            'phone.regex' => 'Invalid phone number format.',
            'password.required' => 'Must be needed Password',
            'password.min' => 'Password must be at least 8 characters',
            'password.string' => 'Password must be a string',
            'image.string' => 'Must be send Image Name string',
        ];


        $validator = Validator::make($request->all(), $rules, $messages);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()->all()]);
        }

        // Get visitor info by IP address
        $visitorInfo = getVisitorInfo();
        if (isset($visitorInfo->countryCode) && $visitorInfo->countryCode == "BD") {
            if (isset($request->phone) && empty($request->phone)) {
                $validator->getMessageBag()->add('phone', "Phone number is required");
                return response()->json(['errors' => $validator->errors()->all()]);
            }
        } else {
            if (isset($request->email) && empty($request->email)) {
                $validator->getMessageBag()->add('email', "Email address is required");
                return response()->json(['errors' => $validator->errors()->all()]);
            }
        }

        $store = Store::where('url', $request->url)->first();
        $user = new User;
        $user->name = $validator->validated()['name'];
        $user->image = $validator->validated()['image'] ?? '';
        $user->phone = $validator->validated()['phone'];
        $user->password = Hash::make($validator->validated()['password']);
        $user->type = 'customerAffiliate';
        $user->auth_type = $store->auth_type;
        $otp = sixDigitRandCode();
        $user->otp = $otp;
        $user->store_id = $store->id;
        $user->customer_id = $store->customer_id;
        $user->save();

        $notificationData = [
            "title" => "New Affiliate customer register (" . getUserNameOrPhone($user) . ") - " . formatDateWithTime($user->created_at),
            "type" => "user_create",
            "user_type" => "admin",
            "store_id" => $user->store_id,
        ];

        if (isset($notificationData['title']) && !empty($notificationData['title'])) {
            createNotification($notificationData);
        }

        $text = ($store->name ?? "Your") . " OTP code is " . $user->otp;

        if (addonSmsCount($store->id) && isset($user->phone) && !empty($user->phone)) {
            SendSms($user->phone, $text); //phone , text

            smsLogger($user->phone, $text, "OTP Send", 0, $store->id);
        }

        if ($user) {
            $info = new ProductAffiliateInfo();
            $info->user_id = $user->id;
            $info->store_id = $store->id;
            $info->referral_code = Str::upper(Str::random(10));
            if (isset($visitorInfo->countryCode) && $visitorInfo->countryCode !== "BD") {
                $info->currency = 'USD';
            }
            $info->save();

            Auth::login($user);
            // $token = Auth::user()->createToken('AuthToken')->accessToken;
            $token = Auth::user()->createToken('AuthToken')->plainTextToken;
            $user = Auth::user();
            if ($user->otp == 'NULL') {
                $verify = true;
            } else {
                $verify = false;
            }
            // return response()->json(['token' => $token->token, 'details' => $user], 200);
            return response()->json(['message' => 'Success', 'token' => $token, 'verify' => $verify], 200);
        } else {
            return response()->json(['success' => 'Registration Successfully, Please Login']);
        }
    }

    /**
     * Create affiliate withdraw request
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \Illuminate\Validation\ValidationException
     */
    public function createWithdrawRequest(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|numeric',
            'phone' => 'required|string',
            'amount' => 'required',
            'currency' => 'required'
        ], [
            'user_id.required' => 'Affiliate customer ID is required',
            'phone.required' => 'Phone number is required',
            'amount.required' => 'Withdraw amount is required',
            'currency.required' => 'Currency is required'
        ]);
        if ($validator->fails()) {
            $errors = $validator->getMessageBag()->toArray();
            return sendError("Validation error", $errors, 422);
        }
        $info = ProductAffiliateInfo::where('user_id', $validator->validated()['user_id'])->where('status', 1)->first();
        $store_id = $info->store_id ?? "";

        $minWithdrawAmount = 100;
        $headerSetting = Headersetting::where("store_id", $store_id)->first();
        if (isset($headerSetting)) {
            $minWithdrawAmount = $headerSetting->affiliate_min_withdraw;
        }

        if (!isset($info)) {
            return sendError("Affiliate User Info not found!", '', 200);
        } elseif ($info->final_amount <= $validator->validated()['amount']) {
            return sendError("Insufficient funds", '', 200);
        } elseif ($minWithdrawAmount > $validator->validated()['amount']) {
            return sendError("Withdraw amount must be greater than minimum withdraw amount", '', 200);
        }

        $withdraw = new ProductAffiliateWithdrawRequest();
        $withdraw->affiliate_info_id = $validator->validated()['user_id'];
        $withdraw->phone = $validator->validated()['phone'];
        $withdraw->amount = $validator->validated()['amount'];
        $withdraw->currency = $validator->validated()['currency'];
        $withdraw->save();
        if ($withdraw) {
            return sendResponse("Withdraw Request Successfully", $withdraw);
        }

        return sendError("Something Went Wrong");
    }


    /**
     * Get affiliate order details
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAffiliateOrderDetails()
    {
        try {
            if (!Auth::check()) {
                return sendError("Unauthorized access.", [], 401);
            }

            $user = Auth::user();

            $user_id = $user->id;
            $perPage = 10;

            $query = ProductAffiliateCommission::with(['product', 'order'])
                ->where('affiliate_user_id', $user_id)
                ->join('orderitems', 'product_affiliate_commissions.product_id', '=', 'orderitems.product_id')
                ->whereColumn('orderitems.order_id', 'product_affiliate_commissions.order_id')
                ->select('product_affiliate_commissions.*', 'orderitems.*');

            // Get paginate data
            $data['productList'] = $query->paginate($perPage);

            // Calculate total earnings
            $totalEarning = $query->get()->sum('amount');
            $data['totalEarning'] = number_format($totalEarning, 2);

            // Get the current date
            $currentDate = Carbon::now();

            // Get the date one month ago
            $oneMonthAgo = $currentDate->subMonth();
            $last_month = $query->where('product_affiliate_commissions.created_at', '>=', $oneMonthAgo)->get();
            $monthlyEarning = $last_month->sum('amount');
            $data['monthlyEarning'] = number_format($monthlyEarning, 2);

            // Get total customer
            $data['totalCustomer'] = $query->whereHas('order')
                ->get()->pluck('order.user_id')->unique()
                ->count();

            // Get total montly customer
            $data['monthlyCustomer'] = $query->where('product_affiliate_commissions.created_at', '>=', $oneMonthAgo)
                ->whereHas('order')
                ->get()->pluck('order.user_id')->unique()
                ->count();

            $withdrawPending = ProductAffiliateWithdrawRequest::where("affiliate_info_id", $user_id)->where("status", 0)->first();
            $data['withdrawPending'] = $withdrawPending ?? false;

            $productAffiliateInfo = ProductAffiliateInfo::where('user_id', $user_id)->first();
            $store_id = $productAffiliateInfo->store_id ?? "";
            $data['totalBalance'] = $productAffiliateInfo->final_amount ?? 0;

            $data['minWithdrawAmount'] = 100;
            $headerSetting = Headersetting::where("store_id", $store_id)->first();
            if (isset($headerSetting)) {
                $data['minWithdrawAmount'] = $headerSetting->affiliate_min_withdraw;
            }

            return sendResponse("Successful", $data);
        } catch (\Exception $exception) {
            serverError();
        }
    }

    /**
     * Set affiliate minimum withdraw request
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function setMinWithDrawAmount(Request $request)
    {
        if (!isset($request->affiliate_min_withdraw) || empty($request->affiliate_min_withdraw) || $request->affiliate_min_withdraw == 0) {
            Session::flash("error", "Min withdraw amount must be greater than 0!");
            return back();
        }

        $store_id = getUserData()['store_id'] ?? "";
        $headerSetting = Headersetting::where("store_id", $store_id)->first();
        if (isset($headerSetting)) {
            $headerSetting->affiliate_min_withdraw = $request->affiliate_min_withdraw;
            $headerSetting->update();

            Session::flash("success", "Min withdraw amount set successfully!");
            return back();
        }

        Session::flash("error", "Something went wrong. Please try again later.");
        return back();
    }


    /**
     * Product affiliate withdraw request rejected successfully
     *
     * @param $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function rejectWithdraw($id)
    {
        if ($id) {
            $requestInfo = ProductAffiliateWithdrawRequest::where("id", $id)->first();
            if ($requestInfo) {
                $requestInfo->status = 2;
                $requestInfo->save();

                Session::flash("success", "Withdraw request rejected!.");
                return redirect()->back();
            }
            Session::flash("error", "Record not found.");
            return redirect()->back();
        }

        Session::flash("error", "Record ID missing.");
        return redirect()->back();
    }


    /**
     * Approved affiliate withdraw request
     *
     * @param $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function approvedWithdraw(Request $request)
    {
        $id = $request->id ?? "";
        $comment = $request->comment ?? "";
        if ($id) {
            $requestInfo = ProductAffiliateWithdrawRequest::where("id", $id)->first();
            if ($requestInfo) {
                $amount = (float)$requestInfo->amount;
                $user_id = $requestInfo->affiliate_info_id;

                $info = ProductAffiliateInfo::where("user_id", $user_id)->first();
                if ($info) {
                    $balance = $info->final_amount ?? 0;

                    $newBalance = ((float)$balance - $amount);
                    $info->final_amount = $newBalance ?? 0;
                    $info->save();

                    $requestInfo->status = 1;
                    $requestInfo->comment = $comment;
                    $requestInfo->save();

                    Session::flash("success", "Withdraw request approved!.");
                    return redirect()->back();
                }
            }
            Session::flash("error", "Record not found.");
            return redirect()->back();
        }

        Session::flash("error", "Record ID missing.");
        return redirect()->back();
    }

}
