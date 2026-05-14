<?php

namespace App\Http\Controllers\Api\v2;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResourceV2;
use App\Models\Headersetting;
use App\Models\Paymenttoken;
use App\Models\ProductAffiliateInfo;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use App\Models\Store;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Validator;
use JWTAuth;


class LoginController extends Controller
{
    /**
     * Get the guard to be used during authentication.
     *
     * @return \Illuminate\Contracts\Auth\Guard
     */
    public function guard()
    {
        return Auth::guard();
    }

    public function getuser()
    {
        $user = User::with('affiliate_info')->find(Auth::id());
        $user = new UserResourceV2($user);
        return response()->json($user);
    }

    protected function authPayload(User $user, ?string $token, bool $verify, ?string $referralCode = null, ?string $message = null): array
    {
        return array_filter([
            "status" => true,
            "message" => $message,
            "user" => new UserResourceV2($user),
            "token" => $token,
            "access_token" => $token,
            "token_type" => "bearer",
            "verify" => $verify,
            "referral" => $referralCode,
        ], static fn ($value) => !is_null($value));
    }

    public function index(Request $request)
    {
        $request->validate([
            'phone' => ['required'],
            'password' => ['required'],
            'store_id' => ['required']
        ]);

        $store = Store::find($request->store_id);

        if (!$store) {
            return response()->json(["status" => false, "message" => "Something wrong. Try again"], 404);
        }

        if (isset($store->auth_type) && $store->auth_type == 'EasyOrder') {
            $auth_type = 'EasyOrder';
        } else {
            $auth_type = $store->auth_type;
        }

        $user = User::where(function ($q) use ($request) {
            $q->where('phone', $request->phone)->orWhere("email", $request->phone);
        })->where('store_id', $request->store_id)->first();


        if (isset($user) && $user->type == "customerAffiliate") {
            $productAffiliateUser = ProductAffiliateInfo::where("user_id", $user->id)->first();

            if ($productAffiliateUser->status == 1) {
                $module_id = 120;
                if (!ModulusStatus($request->store_id, $module_id)) {
                    return response()->json(["status" => false, "message" => "Credential Doesn't Match"], 422);
                } else {
                    $auth_type = $user->auth_type;
                }
            } else {
                return response()->json(["status" => false, "message" => "Credential Doesn't Match"], 422);
            }
        }

        $authCheck = Auth::attempt(['phone' => $request->phone, 'password' => $request->password, 'store_id' => $request->store_id, 'auth_type' => $auth_type]) || Auth::attempt(['email' => $request->phone, 'password' => $request->password, 'store_id' => $request->store_id, 'auth_type' => $auth_type]);

        if ($authCheck) {
            // $token = Auth::user()->createToken('AuthToken')->accessToken;
            $token = null;
            $userData = null;

            $user = Auth::user();
            if ($user->otp == 'NULL') {
                $verify = true;
                $payt = new Paymenttoken();
                $payt->token = $token;
                $payt->uid = $user->id;
                $payt->save();
            } else {
                $verify = false;
            }

            if ($auth_type == "EasyOrder" || $auth_type == "EmailEasyOrder") {
                $verify = true;
            }

            $token = Auth::user()->createToken('AuthToken')->plainTextToken;

            $referralCode = null;
            $productAffiliateUser = ProductAffiliateInfo::where("user_id", $user->id)->first();
            if (isset($productAffiliateUser)) {
                $referralCode = $productAffiliateUser->referral_code ?? null;
            }

            $msg = $verify ? "Successfully Login" : "Please verify your account first!";

            return response()->json($this->authPayload($user, $token, $verify, $referralCode, $msg));
        } else {
            return sendError("Credential Doesn't Match");
        }
    }

    public function paymentlogin(Request $request)
    {
        $users = User::find($request->user_id);
        Auth::login($users);
        $user = Auth::user();
        $token = $user->createToken('AuthToken')->plainTextToken;
        if ($user->otp == 'NULL') {
            $verify = true;
        } else {
            $verify = false;
        }
        return response()->json([
            'token' => $token,
            'access_token' => $token,
            'token_type' => 'bearer',
            'verify' => $verify
        ]);
    }

    public function register(Request $request)
    {
        $credentials = $request->validate([
            'phone' => ['required'],
            'store_id' => ['required'],
        ]);
        $user = User::where('phone', $request->phone)->where('store_id', $request->store_id)->first();
        if (isset($user)) {
            return response()->json(['error' => 'User Already Exist, Please Log In']);
        } else {
            $store = Store::where('id', $request->store_id)->first();
            $user = new User;
            $user->phone = $request->phone;
            $code = sixDigitRandCode();
            $pass = $store->name . "@" . $code;
            $newpass = Hash::make($pass);
            $user->password = $newpass;
            $user->type = "customer";
            $otp = sixDigitRandCode();
            $user->otp = $otp;
            $user->store_id = $store->id;
            $user->customer_id = $store->customer_id;
            $user->save();

            $notificationData = [
                "title" => "New customer register (" . ($user->name ?? '') . ") - " . formatDateWithTime($user->created_at),
                "type" => "user_create",
                "user_type" => "admin",
                "store_id" => $store->id ?? NULL,
            ];

            if (isset($notificationData['title']) && !empty($notificationData['title'])) {
                createNotification($notificationData);
            }

            $text = ($store->name ?? "Your") . " OTP code is " . $user->otp;

            if (addonSmsCount($store->id) && isset($user->phone) && !empty($user->phone)) {
                $smsresult = SendSms($user->phone, $text); //phone , text
                $p = explode("|", $smsresult);
                $sendstatus = $p[0];

                smsLogger($user->phone, $text, "OTP Send", 0, $store->id);
            }


            $text = "Thank You for register to " . $store->name .
                "  Your Login Details is Phone : " . $user->phone .
                " Password : " . $pass;

            if (addonSmsCount($store->id) && isset($user->phone) && !empty($user->phone)) {
                $smsresult = SendSms($user->phone, $text);

                smsLogger($user->phone, $text, "Customer Registration Details", 0, $store->id);
            }

            //number, text
            // $p = explode("|",$smsresult);
            // $sendstatus = $p[0];
            if ($user) {
                $referralCode = null;

                Auth::login($user);
                // $token = Auth::user()->createToken('AuthToken')->accessToken;
                $token = Auth::user()->createToken('AuthToken')->plainTextToken;
                $user = Auth::user();
                if ($user->otp == 'NULL') {
                    $verify = true;
                } else {
                    $verify = false;
                }

                return response()->json([
                    'token' => $token,
                    'access_token' => $token,
                    'token_type' => 'bearer',
                    'verify' => $verify,
                    'referral' => $referralCode
                ]);
            } else {
                return response()->json(['success' => 'Registration Successfully, Please Login']);
            }
        }
    }

    public function verifyotp(Request $request)
    {
        $user_id = Auth::user()->id;
        $otp = $request->otp;
        $user = User::where('id', $user_id)->where('otp', $otp)->first();
        if (isset($user)) {
            $user->otp = 'NULL';
            $user->save();
            $verify = true;
            $users = Auth::user();

            $users->tokens()->where('id', $users->currentAccessToken()->id)->delete();
            $token = Auth::user()->createToken('AuthToken')->plainTextToken;
            return response()->json([
                'token' => $token,
                'access_token' => $token,
                'token_type' => 'bearer',
                'verify' => $verify
            ], 200);
        } else {
            return response()->json(['error' => 'Incorrect Otp']);
        }
    }

    public function forget(Request $request)
    {
        $phone = $request->phone;
        $store_id = $request->store_id;

        $user = User::where('phone', $phone)->where('store_id', $store_id)->first();

        if (empty($user)) {
            $user = User::where('email', $phone)->where('store_id', $store_id)->first();
        }

        if (isset($user)) {
            if ($user->auth_type == 'google' || $user->auth_type == 'facebook') {
                return response()->json(['error' => 'You can not forget password Because your are login for social.']);
            }

            $otp = sixDigitRandCode();
            $user->otp = $otp;
            $user->save();

            $store = Store::find($request->store_id);
            $text = ($store->name ?? "Your") . " OTP code is " . $user->otp;
            $headersetting = Headersetting::where('store_id', $store_id)->first();

            if ($user->auth_type == 'phone' || $user->auth_type == 'EasyOrder') {
                if (addonSmsCount($store->id) && isset($user->phone) && !empty($user->phone)) {
                    $smsresult = SendSms($user->phone, $text);
                    smsLogger($user->phone, $text, "OTP Send", 0, $store->id);
                }
                // phone text
            } else {
                if (isset($user->email)) {
                    $data['email'] = $user->email;
                    $data['orderInfo'] = $text .
                        "\nWe will get in touch with you shortly.\nFor Help:" . $headersetting->phone;

                    $data["title"] = "From " . $store->name;

                    Mail::send('clientOrderNotifyMail', $data, function ($message) use ($data) {
                        $message->from('orderinfo@ebitans.com', $data["title"])->to($data["email"], $data["email"])
                            ->subject('OTP ...');
                    });
                }
            }

            return response()->json(['user_id' => $user->id]);
        } else {
            return response()->json(['error' => 'Incorrect information'], 405);
        }
    }

    public function forgetverify(Request $request)
    {
        $user = User::find($request->user_id);
        if ($user->otp == $request->otp) {
            $user->otp = "NULL";
            $user->save();
            $verify = true;

            return response()->json(['user_id' => $user->id, 'verify' => $verify, 'success' => 'Successfully Verified']);
        } else {
            $verify = false;
            return response()->json(['error' => 'Otp Not Match Please try again', 'verify' => $verify]);
        }
    }

    public function changepass(Request $request)
    {
        try {
            $rules = array(
                'user_id' => ['required', 'numeric'], // You can add additional rules like min length, etc.
                'password' => ['required', 'string', 'min:6'], // You can add additional rules like min length, etc.
                'confirm_password' => ['required', 'string', 'same:password'],
            );

            $message = array(
                'user_id.required' => 'User ID is required.',
                'password.required' => 'Please enter password!!',
                'confirm_password.required' => 'Please enter confirm password!!',
                'password.min' => 'The password must be at least 6 characters.',
                'confirm_password.same' => 'Confirm password does not match.',
            );

            $validation = \Illuminate\Support\Facades\Validator::make($request->all(), $rules, $message);

            if ($validation->fails()) {
                $errors = $validation->getMessageBag()->toArray();
                return sendError("Validation error", $errors, 422);
            }

            $user = User::find($request->user_id);

            if (isset($user)) {
                $password = $request->password;
                $confirm_password = $request->confirm_password;
                if ($password == $confirm_password) {
                    $user->password = Hash::make($password);
                    $user->save();

                    return response()->json(['success' => 'Password Change Successfully']);
                } else {
                    $validation->errors()->add('password', 'Password and Confirm Password not match.');
                    $errors = $validation->getMessageBag()->toArray();
                    return sendError('Validation Error', $errors, 422);
                }
            } else {
                return sendError('User Not Found!!');
            }

        } catch (\Exception $e) {
            return serverError();
        }
    }

    public function logout(Request $request)
    {
        $user = Auth::user();
        $token = $user->currentAccessToken();
        if ($token && $token instanceof \Laravel\Sanctum\PersonalAccessToken) {
            $user->tokens()->where('id', $token->id)->delete();
        }

        return response()->json(['success' => 'Successfully Logout']);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }

    protected function createNewToken($token)
    {
        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'user' => auth()->user()
        ]);
    }
}
