<?php

namespace App\Http\Controllers\Api\v2;

use App\Http\Controllers\Controller;
use App\Http\Resources\BuyModuleResource;
use App\Http\Resources\UserResourceV2;
use App\Mail\OPTSendMail;
use App\Models\ProductAffiliateInfo;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Customer;
use App\Models\Store;
use App\Models\Address;
use App\Models\Headersetting;
use App\Models\Brand;
use App\Models\BuyModulus;
use Illuminate\Support\Str;
use App\Models\Modulus;
use App\Rules\PhoneNumber;
use App\Models\Prereguser;
use App\Models\QuickLogin;
use Illuminate\Support\Facades\Hash;
use Validator;
use Auth;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;
use Propaganistas\LaravelPhone\Rules\Phone;

class UserController extends Controller
{
    public function updateuser(Request $request)
    {
        try {
            $rules = array(
                'store_id' => ['required'],
            );
            $message = array(
                'store_id.required' => "Store id is required",
            );

            $validation = \Illuminate\Support\Facades\Validator::make($request->all(), $rules, $message);

            if ($validation->fails()) {
                $errors = $validation->getMessageBag()->toArray();
                return sendError("Validation error", $errors, 422);
            }

            $store = Store::find($request->store_id);

            if (isset($store)) {
                $user = User::where('id', Auth::user()->id)->where('store_id', $request->store_id)->first();
                if (isset($user)) {
                    $user->name = $request->name;
                    if ($store->auth_type == 'email') {
                        $user->phone = $request->phone ?? $user->phone;
                    } else {
                        $user->email = $request->email ?? $user->email;
                    }
                    $user->address = $request->address;
                    if ($request->image) {
                        $img = substr($request->image, strpos($request->image, ",") + 1);
                        $file = base64_decode($img);
                        $safeName = Str::random(10) . '.' . 'png';

                        $success = file_put_contents(public_path() . '/assets/images/img/' . $safeName, $file);
                        $user->image = $safeName;
                    }
                    $user->save();

                    return sendResponse("Success", new UserResourceV2($user));
                } else {
                    return sendError("User Not found!");
                }
            } else {
                return sendError("Store not found!");
            }
        } catch (\Exception $e) {
            return serverError();
        }
    }

    public function modules($store)
    {
        try {
            if (empty($store) || is_null($store)) {
                return response()->json(['status' => false, 'message' => 'Store ID is required']);
            }

            $modules = BuyModulus::where('store_id', $store)->rightJoin('moduluses', 'moduluses.id', '=', 'buy_moduluses.modulus_id')
                ->select('buy_moduluses.id', 'buy_moduluses.store_id', 'moduluses.id as modulus_id', 'moduluses.name', 'buy_moduluses.price', 'buy_moduluses.type', 'buy_moduluses.start_date', 'buy_moduluses.end_date', 'buy_moduluses.sms_count', 'buy_moduluses.status')
                ->get();

            return response()->json(['status' => true, 'message' => 'Success', 'data' => $modules]);
        } catch (\Exception $exception) {
            return serverError();
        }

    }


    /**
     * Get all module info by store url
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getModuleInfo($store)
    {
        try {
            if (empty($store) || is_null($store)) {
                return response()->json(['status' => false, 'message' => 'Store ID is required']);
            }

            $buyModulus = BuyModulus::with("module")->where('store_id', $store)->get();

            if (count($buyModulus) > 0) {
                return response()->json(["status" => true, 'message' => 'Success', 'data' => BuyModuleResource::collection($buyModulus)]);
            }
            return response()->json(["status" => false, 'message' => 'Module not found']);

        } catch (\Exception $exception) {
            return serverError();
        }

    }


    /**
     * Get single module is active or inactive
     *
     * @param Request $request
     * @param $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function getModuleById($store, $id)
    {
        try {
            if (empty($store) || is_null($store)) {
                return response()->json(['status' => false, 'message' => 'Store ID is required']);
            }

            if (empty($id) || is_null($id)) {
                return response()->json(['status' => false, 'message' => 'Module ID is required']);
            }

            $buyModulus = BuyModulus::where('store_id', $store)->where('modulus_id', $id)->first();
            $modulus = Modulus::find($id);

            if (isset($modulus->status) && isset($buyModulus->status) && $modulus->status == 1 && $buyModulus->status == 1) {
                return response()->json(["status" => true, 'message' => 'Module Active']);
            }
            return response()->json(["status" => false, 'message' => 'Module Inactive']);
        } catch (\Exception $exception) {
            return serverError();
        }

    }

    public function userdetails(Request $request)
    {
        $user = User::where('id', $request->user_id)->where('store_id', $request->store_id)->first();
        if (isset($user)) {
            return response()->json($user);
        } else {
            return response()->json(['error' => 'User Not found']);
        }
    }

    public function changepass(Request $request)
    {
        try {
            $rules = array(
                'current_password' => ['required', 'string'],
                'password' => ['required', 'string', 'min:6'], // You can add additional rules like min length, etc.
                'confirm_password' => ['required', 'string', 'same:password'],
            );

            $message = array(
                'current_password.required' => 'Please enter current-password!!',
                'password.required' => 'Please enter password!!',
                'confirm_password.required' => 'Please enter confirm password!!',
                'confirm_password.same' => 'Confirm password does not match.',
                'password.min' => 'The password must be at least 6 characters.',
            );


            $validation = \Illuminate\Support\Facades\Validator::make($request->all(), $rules, $message);

            if ($validation->fails()) {
                $errors = $validation->getMessageBag()->toArray();
                return sendError("Validation error", $errors, 422);
            }


            if (\Illuminate\Support\Facades\Auth::Check()) {
                $current_password = Auth::User()->password;
                if (Hash::check($request->current_password, $current_password)) {
                    if (Hash::check($request->password, $current_password)) {
                        $validation->errors()->add('password', 'You can not set same password!');
                        $errors = $validation->getMessageBag()->toArray();
                        return sendError('Validation Error', $errors, 422);
                    }

                    $user = Auth::User();
                    $user->password = Hash::make($request->password);
                    $user->save();

                    return sendResponse('Password has been changed Successfully');

                } else {
                    $validation->errors()->add('current_password', 'Please enter correct current password');
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

    public function address()
    {
        try {
            $uid = Auth::user()->id ?? "";
            $addresses = Address::with("district")->where('uid', $uid)->get();

            return sendResponse("Success", $addresses);
        } catch (\Exception $e) {
            return serverError();
        }
    }

    public function saveaddress(Request $request)
    {
        $authenticate = false;
        if (Auth::check()) {
            $user = Auth::user();
            $user_id = $user->id;
            $authenticate = true;
        } else {
            // User is not authenticated, create a new user
            $store = Store::find($request->store_id);
            if ($store->auth_type == 'EasyOrder') {
                $existing_user = User::where('phone', $request->phone)
                    ->where('type', 'customer')
                    ->where('store_id', $store->id)
                    ->first();

                if (empty($existing_user)) {
                    // Create new user
                    $user = new User;
                    $user->phone = $request->phone;
                    $code = sixDigitRandCode();
                    $pass = $store->name . "@" . $code;
                    $newpass = Hash::make($pass);
                    $user->password = $newpass;
                    $user->type = "customer";
                    $otp = sixDigitRandCode();
                    $user->otp = 'NULL';
                    $user->store_id = $store->id;
                    $user->auth_type = 'EasyOrder';
                    $user->customer_id = $store->customer_id;
                    $user->save();

                    $notificationData = [
                        "title" => "New customer register (" . getUserNameOrPhone($user) . ") - " . formatDateWithTime($user->created_at),
                        "type" => "user_create",
                        "user_type" => "admin",
                        "store_id" => $user->store_id,
                    ];

                    if (isset($notificationData['title']) && !empty($notificationData['title'])) {
                        createNotification($notificationData);
                    }

                    $text = "Thank You for register to " . $store->name . "  Your Login Details is Phone : " . $request->phone . " Password : " . $pass;

                    if (addonSmsCount($store->id) && isset($user->phone) && !empty($user->phone)) {
                        $smsresult = SendSms($user->phone, $text); // phone text
                        $p = explode("|", $smsresult);
                        $sendstatus = $p[0];
                        smsLogger($user->phone, $text, "Customer Registration Details", 0, $store->id);
                    }
                } else {
                    // Use existing user
                    $user = $existing_user;
                }
            } else {
                // Handle other authentication types if necessary
                return sendError("Unauthorized access!", '', 401);
            }

            // Retrieve user ID
            $user_id = $user->id;
        }

        // Now that we have the user ID, we can proceed with saving the address
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

        $data['address'] = $ad;

        if (!$authenticate) {
            $userData['token'] = $user->createToken('AuthToken')->plainTextToken;
            $userData['verify'] = 'true';
            $userData['referral'] = null;
            $userData['user'] = new UserResourceV2($user);

            $data['userData'] = $userData;
        }

        return sendResponse('Successfully save address', $data);
    }


    public function updateaddress(Request $request)
    {
        $user_id = Auth::user()->id;
        $ad = Address::where('id', $request->id)->where('uid', $user_id)->first();
        if (isset($ad)) {
            $ad->name = $request->name;
            $ad->phone = $request->phone;
            $ad->email = $request->email;
            $ad->address = $request->address;
            $ad->note = $request->note;
            $ad->district_id = $request->district ?? null;
            $ad->phone_code = $request->phone_code ?? null;
            $ad->save();

            return sendResponse("Address Updated Successfully", $ad);
        } else {
            return sendError("Record not found!");
        }
    }

    public function getBrand($store)
    {
        try {
            if (empty($store) || is_null($store)) {
                return response()->json(['status' => false, 'message' => 'Store ID is required']);
            }

            $brand = Brand::where('store_id', $store)->get(['name', 'image']);

            return sendResponse("success", $brand);
        } catch (\Exception $exception) {
            return serverError();
        }
    }

    public function deleteaddress(Request $request)
    {
        try {
            if (empty($request->id) || is_null($request->id)) {
                return response()->json(['status' => false, 'message' => 'Record ID is required']);
            }

            $user_id = Auth::user()->id;
            $ad = Address::where('id', $request->id)->where('uid', $user_id)->first();
            if (isset($ad)) {
                $ad->delete();
                return sendResponse("Address Deleted Successfully");
            } else {
                return sendError("Record not found");
            }
        } catch (\Exception $exception) {
            return serverError();
        }
    }

    public function userinfo(Request $request)
    {
        $rules = array(
            'phone' => ['required', new PhoneNumber],
            'store_id' => ['required'],
        );
        $message = array(
            'phone.required' => "Phone number is required",
            'store_id.required' => "Store id is required",
        );

        $validation = \Illuminate\Support\Facades\Validator::make($request->all(), $rules, $message);

        if ($validation->fails()) {
            $errors = $validation->getMessageBag()->toArray();
            return response()->json(['message' => 'Validation error', 'errors' => $errors], 409);
        }

        $store = Store::find($request->store_id);
        $user = User::where('phone', $request->phone)->where('store_id', $request->store_id)->where(function ($q) use ($request) {
            $q->where('type', "customer")->orWhere('type', "customerAffiliate");
        })->first();
        if (isset($user)) {
            return response()->json(['message' => 'Already Registered'], 409);
        }
        $existuser = Prereguser::where('phone', $request->phone)->where('store_id', $request->store_id)->first();
        if (isset($existuser)) {
            $existuser->delete();
        }
        $otp = sixDigitRandCode();
        $pre = new Prereguser();
        $pre->phone = $request->phone;
        $code = sixDigitRandCode();
        $pass = $store->name . "@" . $code;
        $pre->password = $pass;
        $pre->store_id = $request->store_id;
        $pre->type = $request->type ?? "customer";
        $pre->otp = $otp;
        $token = encrypt($pass);
        $pre->token = $token;
        $pre->save();

        $text = $store->name . " OTP code is " . $pre->otp;

        if (addonSmsCount($store->id) && isset($pre->phone) && !empty($pre->phone)) {
            $smsresult = SendSms($pre->phone, $text); // phone, text
            smsLogger($pre->phone, $text, "OTP Send", 0, $store->id);
        }

        return response()->json(['message' => 'Success', 'token' => $token], 200);
    }

    public function userRegistrationEmail(Request $request)
    {
        $rules = array(
            'email' => ['required'],
            'password' => ['required'],
            'store_id' => ['required'],
        );

        $message = array(
            'email.required' => "Email is required",
            'password.required' => "Password is required",
            'store_id.required' => "Store id is required",
        );

        $validation = \Illuminate\Support\Facades\Validator::make($request->all(), $rules, $message);

        if ($validation->fails()) {
            $errors = $validation->getMessageBag()->toArray();
            return response()->json(['message' => 'Validation error', 'errors' => $errors], 409);
        }

        $store = Store::find($request->store_id);

        $user = User::where('email', $request->email)->where('store_id', $request->store_id)->where(function ($q) use ($request) {
            $q->where('type', "customer")->orWhere('type', "customerAffiliate");
        })->first();
        if (isset($user)) {
            return response()->json(['message' => 'Already Registered'], 409);
        }

        $existuser = Prereguser::where('email', $request->email)->where('store_id', $request->store_id)->first();
        if (isset($existuser)) {
            $existuser->delete();
        }

        $otp = sixDigitRandCode();
        $pre = new Prereguser();
        $pre->email = $request->email;
        $code = sixDigitRandCode();
        $pass = $request->password;
        $pre->password = $pass;
        $pre->store_id = $request->store_id;
        $pre->type = $request->type ?? "customer";
        $pre->otp = $otp;
        $token = encrypt($pass);
        $pre->token = $token;
        $pre->save();

        $text = $store->name . " OTP code is " . $pre->otp;

        $headersetting = Headersetting::where('store_id', $store->id)->first();

        if (isset($request->email)) {
            if (is_null($headersetting->email) || empty($headersetting->email)) {
                return response()->json(['status' => false, 'message' => 'Admin email not set yet'], 409);
            }

            $data['email'] = $request->email;
            $data['FormEmail'] = $headersetting->email;
            //            $data['orderInfo'] = "Registration OTP code is - " . $pre->otp . " From " . $store->name .
//                "\nWe will get in touch with you shortly.\nFor Help:" . $headersetting->phone;

            $data["title"] = $store->name;

            $data["store_name"] = $store->name;
            $data["app_url"] = $store->url;
            $data["otp"] = $pre->otp;
            $data["help_number"] = $headersetting->phone ?? "";

            Mail::send('emailNotify.registrationOTP', $data, function ($message) use ($data) {
                $message->from($data['FormEmail'], $data["title"])->to($data["email"], $data["email"])
                    ->subject('Registration OTP');
            });
        }

        return response()->json(['message' => 'Success', 'token' => $token], 200);
    }

    public function checkotps(Request $request)
    {
        $Prereguser = Prereguser::where('token', $request->token)->first();
        if (isset($Prereguser) && $Prereguser->otp == $request->otp) {
            $store = Store::where('id', $Prereguser->store_id)->first();

            $registerType = "customer";
            if ($Prereguser->type == 'customer') {
                $registerType = "customer";
            } elseif ($Prereguser->type == 'customerAffiliate') {
                $registerType = "customerAffiliate";
            }


            $user = new User;
            $user->phone = $Prereguser->phone;
            $user->email = $Prereguser->email;
            $code = sixDigitRandCode();
            $pass = $Prereguser->password;
            $newpass = Hash::make($pass);
            $user->password = $newpass;
            $user->type = $registerType;
            $user->otp = 'NULL';
            $user->store_id = $store->id;
            if (!empty($Prereguser->email)) {
                $user->auth_type = 'email';
            } elseif (!empty($Prereguser->phone)) {
                $user->auth_type = 'phone';
            }
            $user->save();

            if ($user->type == 'customer') {
                $userType = "Customer";
            } elseif ($user->type == 'customerAffiliate') {
                $userType = "Affiliate Customer";
            }

            $notificationData = [
                "title" => "New customer register as " . $userType . " (" . getUserNameOrPhone($user) . ") - " . formatDateWithTime($user->created_at),
                "type" => "user_create",
                "user_type" => "admin",
                "store_id" => $user->store_id,
            ];

            if (isset($notificationData['title']) && !empty($notificationData['title'])) {
                createNotification($notificationData);
            }

            $text = "Thank You for register to " . $store->name .
                "  Your Login Details is
            Phone : " . $user->phone .
                " Password : " . $pass;

            if (isset($Prereguser->email)) {
                $user->auth_type = 'email';

                $headersetting = Headersetting::where('store_id', $store->id)->first();

                if (isset($Prereguser->email)) {
                    if (is_null($headersetting->email) || empty($headersetting->email)) {
                        return response()->json(['status' => false, 'message' => 'Admin email not set yet'], 409);
                    }
                    $data['email'] = $Prereguser->email;
                    $data['FormEmail'] = $headersetting->email;
                    $data['orderInfo'] = $text .
                        "\nWe will get in touch with you shortly.\nFor Help:" . $headersetting->phone;

                    $data["title"] = "From " . $store->name;

                    Mail::send('clientOrderNotifyMail', $data, function ($message) use ($data) {
                        $message->from($data['FormEmail'], $data["title"])->to($data["email"], $data["email"])
                            ->subject('OTP ...');
                    });
                }
            } else {
                $user->auth_type = 'phone';
                if (addonSmsCount($store->id) && isset($user->phone) && !empty($user->phone)) {
                    $smsresult = SendSms($user->phone, $text); // phone, text

                    smsLogger($user->phone, $text, "Customer Registration Details", 0, $store->id);
                }
            }


            $Prereguser->delete();
            if ($user) {
                if ($registerType == "customerAffiliate") {
                    // Get visitor info by IP address
                    $visitorInfo = getVisitorInfo();

                    $info = new ProductAffiliateInfo();
                    $info->user_id = $user->id;
                    $info->store_id = $store->id;
                    $info->referral_code = Str::upper(Str::random(10));
                    if (isset($visitorInfo->countryCode) && $visitorInfo->countryCode !== "BD") {
                        $info->currency = 'USD';
                    }
                    $info->save();
                }

                $referralCode = null;
                $token = null;
                $verify = false;

                if ($user->otp == 'NULL') {
                    $verify = true;
                    $productAffiliateUser = ProductAffiliateInfo::where("user_id", $user->id)->first();

                    if ($user->type == "customerAffiliate") {
                        if (isset($productAffiliateUser) && $productAffiliateUser->status == 0) {
                            return $this->userReturnAfterRegister(true, 'Registration Successful, Wait for approval!.', $verify);
                        }

                        if (isset($productAffiliateUser)) {
                            $referralCode = $productAffiliateUser->referral_code ?? null;
                        }
                    }

                    Auth::login($user);
                    $token = Auth::user()->createToken('AuthToken')->plainTextToken;
                    $user = new UserResourceV2($user);

                    return $this->userReturnAfterRegister(true, 'Registration Successful', $verify, $user, $token, $referralCode);
                }
            }
        }

        return $this->userReturnAfterRegister(false, 'OTP Doesn"t Match');
    }


    public function userReturnAfterRegister($status, $message, $verify = false, $user = null, $token = null, $referralCode = null)
    {
        return response()->json([
            'status' => $status,
            'user' => $user,
            'token' => $token,
            'verify' => $verify,
            'referral' => $referralCode,
            'message' => $message
        ]);
    }

    public function rsendotps(Request $request)
    {
        $otp = sixDigitRandCode();
        $Prereguser = Prereguser::where('token', $request->token)->first();
        if (isset($Prereguser)) {
            $Prereguser->otp = $otp;
            $Prereguser->save();
            $store = Store::find($Prereguser->store_id);
            $text = $store->name . " OTP code is " . $Prereguser->otp;

            if (isset($Prereguser->email)) {
                $headersetting = Headersetting::where('store_id', $store->id)->first();

                if (is_null($headersetting->email) || empty($headersetting->email)) {
                    return response()->json(['status' => false, 'message' => 'Admin email not set yet'], 409);
                }

                $emailForm = $headersetting->email;
                $data['email'] = $Prereguser->email;
                $data['FormEmail'] = $headersetting->email;
                $data['orderInfo'] = $text .
                    "\nWe will get in touch with you shortly.\nFor Help:" . $headersetting->phone;

                $data["title"] = "From " . $store->name;

                Mail::send('clientOrderNotifyMail', $data, function ($message) use ($data) {
                    $message->from($data['FormEmail'], $data["title"])->to($data["email"], $data["email"])
                        ->subject('OTP ...');
                });
            } else {
                if (addonSmsCount($store->id) && isset($Prereguser->phone) && !empty($Prereguser->phone)) {
                    $smsresult = SendSms($Prereguser->phone, $text); // phone, text

                    smsLogger($Prereguser->phone, $text, "OTP Send", 0, $store->id);
                }
            }

            return response()->json(['status' => true, 'message' => 'OTP Send Successfully.', 'token' => $request->token], 200);
        } else {
            return sendError("User Not Found");
        }
    }

    public function registers(Request $request)
    {
        if (is_numeric($request->email_or_phone)) {
            $isEmail = false;
        } else {
            $isEmail = true;
        }
        $country_code = $request->country_code ?? "BD";
        $type = $request->type ?? "admin";

        $rules = [
            'email_or_phone' => ['required'],
            'password' => ['required', 'string'],
        ];

        if ($isEmail) {
            $rules['email_or_phone'][] = 'email';
        } else {
            $rules['email_or_phone'][] = 'phone:' . $country_code; // Basic phone validation

            \Illuminate\Support\Facades\Validator::extend('email_or_phone', function ($attribute, $value, $parameters, $validator) {
                $country = $parameters[0] ?? 'the country';
                return phone($value, [$country]); // This uses the phone validation logic
            }, 'The phone number must be a valid phone number for :country.');

            \Illuminate\Support\Facades\Validator::replacer('phone', function ($message, $attribute, $rule, $parameters) use ($country_code) {
                $countryName = getCountryName($country_code);
                return str_replace(':country', $countryName, $message);
            });
        }

        $message = [
            'email_or_phone.required' => 'Email/Phone is required.',
            'password.required' => 'Password is required.',
            'email_or_phone.email' => 'Enter a valid email address.',
        ];

        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), $rules, $message);

        if ($validator->fails()) {
            $errors = $validator->getMessageBag();
            return sendError("Validation Error", $errors, 422);
        }

        if ($isEmail) {
            if (!filter_var($request->email_or_phone, FILTER_VALIDATE_EMAIL)) {
                $validator->getMessageBag()->add('email_or_phone', "Invalid email address.");
                $errors = $validator->getMessageBag();
                return sendError("Validation Error", $errors, 422);
            }
        } else {
            // Parse the phone number to get only the local number (without country code)
            $phoneUtil = \libphonenumber\PhoneNumberUtil::getInstance();
            $parsedNumber = $phoneUtil->parse($request->email_or_phone, $country_code);
            $request->email_or_phone = $phoneUtil->getNationalSignificantNumber($parsedNumber);

            if ($country_code == "BD") {
                $request->email_or_phone = '0' . $request->email_or_phone;
            }
        }

        // user find in the database
        $user = User::where(function ($q) use ($request, $isEmail) {
            if ($isEmail) {
                $q->where('email', $request->email_or_phone);
            } else {
                $q->where('phone', $request->email_or_phone);
            }
        })->where(function ($q) {
            $q->where('type', "admin")
                ->orWhere('type', "affiliate")
                ->orWhere('type', "superadmin")
                ->orWhere('type', "dropshipper");
        })->first();


        if (isset($user)) {
            if ($isEmail) {
                $errorMsg = 'This Email Already Registered';

                if ($user->type == 'admin') {
                    $errorMsg = 'This Email Already Registered as Admin';
                } elseif ($user->type == 'affiliate') {
                    $errorMsg = 'This Email Already Registered as Affiliate';
                } elseif ($user->type == 'superadmin') {
                    $errorMsg = 'This Email Already Registered';
                } elseif ($user->type == 'dropshipper') {
                    $errorMsg = 'This Email Already Registered as Drop Shipper';
                }
            } else {
                $errorMsg = 'This Phone Number Already Registered';

                if ($user->type == 'admin') {
                    $errorMsg = 'This Phone Number Already Registered as Admin';
                } elseif ($user->type == 'affiliate') {
                    $errorMsg = 'This Phone Number Already Registered as Affiliate';
                } elseif ($user->type == 'superadmin') {
                    $errorMsg = 'This Phone Number Already Registered';
                } elseif ($user->type == 'dropshipper') {
                    $errorMsg = 'This Phone Number Already Registered as Drop Shipper';
                }
            }

            return sendError($errorMsg, '', 422);
        } else {
            $regEmail = "";
            $regPhone = "";

            if ($isEmail) {
                $regEmail = $request->email_or_phone ?? "";
            } else {
                $regPhone = $request->email_or_phone ?? "";
            }

            if (!empty($regEmail) || !empty($regPhone)) {
                $originDomain = isset($request->origin_domain) && !empty($request->origin_domain) ? $request->origin_domain : getOriginDomain();
                $user = new User;
                $user->name = $request->name != null ? $request->name : '';
                $user->email = $regEmail ?? null;
                $user->password = Hash::make($request->password);
                $user->type = $type;
                $user->phone = $regPhone ?? "";
                $user->otp = "NULL";
                $user->offertime = $request->time != null ? Carbon::parse($request->time) : null;
                $user->register_from = $originDomain;
                $user->save();

                $notificationData = [
                    "title" => "New user register as " . ucfirst($user->type) . " (" . getUserNameOrPhone($user) . ") - " . formatDateWithTime($user->created_at),
                    "type" => "user_create",
                    "user_type" => "superadmin",
                ];

                if (isset($notificationData['title']) && !empty($notificationData['title'])) {
                    createNotification($notificationData);
                }

                $customer = new Customer;
                $customer->uid = $user->id;
                $customer->phone = $user->phone ?? "";
                $customer->plan_id = "NULL";
                $customer->purchase_date = "NULL";
                $customer->active_store = "0";
                $customer->ref_code = Str::random(8);
                $customer->points = "200";
                $customer->save();

                // Issue Sanctum token
                $token = $user->createToken('authToken')->plainTextToken;

                event(new Registered($user));

                return sendResponse("Registration successful", ["token" => $token]);
            }

            return sendError("Something wrong. Try again!", '', 422);
        }
    }

    public function registerscheck(Request $request)
    {
        if (is_numeric($request->email_or_phone)) {
            $isEmail = false;
        } else {
            $isEmail = true;
        }

        $country_code = $request->country_code ?? "BD";

        $rules = [
            'email_or_phone' => ['required'],
        ];

        if ($isEmail) {
            $rules['email_or_phone'][] = 'email';
        } else {
            $rules['email_or_phone'][] = 'phone:' . $country_code; // Basic phone validation

            \Illuminate\Support\Facades\Validator::extend('email_or_phone', function ($attribute, $value, $parameters, $validator) {
                $country = $parameters[0] ?? 'the country';
                return phone($value, [$country]); // This uses the phone validation logic
            }, 'The phone number must be a valid phone number for :country.');

            \Illuminate\Support\Facades\Validator::replacer('phone', function ($message, $attribute, $rule, $parameters) use ($country_code) {
                $countryName = getCountryName($country_code);
                return str_replace(':country', $countryName, $message);
            });
        }

        $message = [
            'email_or_phone.required' => 'Email/Phone is required.',
            'email_or_phone.email' => 'Enter a valid email address.',
            'password.required' => 'Password is required.',
        ];

        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), $rules, $message);

        if ($validator->fails()) {
            $errors = $validator->getMessageBag();
            return sendError("Validation Error", $errors, 422);
        }

        if (is_null($request->code) || empty($request->code)) {
            return sendError("Something wrong. Try again", '', 422);
        }

        if ($isEmail) {
            if (!filter_var($request->email_or_phone, FILTER_VALIDATE_EMAIL)) {
                $validator->getMessageBag()->add('email_or_phone', "Invalid email address.");
                $errors = $validator->getMessageBag();
                return sendError("Validation Error", $errors, 422);
            }
        } else {
            // Parse the phone number to get only the local number (without country code)
            $phoneUtil = \libphonenumber\PhoneNumberUtil::getInstance();
            $parsedNumber = $phoneUtil->parse($request->email_or_phone, $country_code);
            $request->email_or_phone = $phoneUtil->getNationalSignificantNumber($parsedNumber);

            if ($country_code == "BD") {
                $request->email_or_phone = '0' . $request->email_or_phone;
            }
        }

        // user find in the database
        $user = User::where(function ($q) use ($request, $isEmail) {
            if ($isEmail) {
                $q->where('email', $request->email_or_phone);
            } else {
                $q->where('phone', $request->email_or_phone);
            }
        })->where(function ($q) {
            $q->where('type', "admin")
                ->orWhere('type', "affiliate")
                ->orWhere('type', "superadmin")
                ->orWhere('type', "dropshipper");
        })->first();

        if (isset($user)) {
            if ($isEmail) {
                $errorMsg = 'This Email Already Registered';

                if ($user->type == 'admin') {
                    $errorMsg = 'This Email Already Registered as Admin';
                } elseif ($user->type == 'affiliate') {
                    $errorMsg = 'This Email Already Registered as Affiliate';
                } elseif ($user->type == 'superadmin') {
                    $errorMsg = 'This Email Already Registered';
                } elseif ($user->type == 'dropshipper') {
                    $errorMsg = 'This Email Already Registered as Drop Shipper';
                }
            } else {
                $errorMsg = 'This Phone Number Already Registered';

                if ($user->type == 'admin') {
                    $errorMsg = 'This Phone Number Already Registered as Admin';
                } elseif ($user->type == 'affiliate') {
                    $errorMsg = 'This Phone Number Already Registered as Affiliate';
                } elseif ($user->type == 'superadmin') {
                    $errorMsg = 'This Phone Number Already Registered';
                }
            }

            return sendError($errorMsg, '', 422);
        } else {
            $text = "Ebitans OTP code is " . $request->code;

            if ($isEmail) {
                $data['name'] = $request->email_or_phone ?? "";
                $data['subject'] = "Registration";
                $data['text'] = $text;
                $data['formEmail'] = env('MAIL_FROM_ADDRESS');

                Mail::to($request->email_or_phone)->send(new OPTSendMail($data));
            } else {
                SendSms($request->email_or_phone, $text);

                smsLogger($request->email_or_phone, $text, "OTP Send");
            }

            if ($isEmail) {
                $successMsg = "This email is available for registration.";
            } else {
                $successMsg = "This phone is available for registration.";
            }

            return sendResponse($successMsg);
        }
    }


    public function checkUserPhone(Request $request)
    {
        $country_code = $request->country_code ?? "BD";

        $rules = [
            'phone' => ['required'],
        ];

        $rules['phone'][] = 'phone:' . $country_code; // Basic phone validation

        \Illuminate\Support\Facades\Validator::extend('phone', function ($attribute, $value, $parameters, $validator) {
            $country = $parameters[0] ?? 'the country';
            return phone($value, [$country]); // This uses the phone validation logic
        }, 'The phone number must be a valid phone number for :country.');

        \Illuminate\Support\Facades\Validator::replacer('phone', function ($message, $attribute, $rule, $parameters) use ($country_code) {
            $countryName = getCountryName($country_code);
            return str_replace(':country', $countryName, $message);
        });

        $message = [
            'phone.required' => 'Phone is required.',
        ];

        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), $rules, $message);

        if ($validator->fails()) {
            return sendError($validator->getMessageBag()->first() ?? "Invalid phone number", $validator->getMessageBag());
        }

        // Parse the phone number to get only the local number (without country code)
        $phoneUtil = \libphonenumber\PhoneNumberUtil::getInstance();
        $parsedNumber = $phoneUtil->parse($request->phone, $country_code);
        $request->phone = $phoneUtil->getNationalSignificantNumber($parsedNumber);
        if ($country_code == "BD") {
            $request->phone = '0' . $request->phone;
        }

        // user find in the database
        $user = User::where(function ($q) use ($request) {
            $q->where('phone', $request->phone);
        })->where(function ($q) {
            $q->where('type', "admin")
                ->orWhere('type', "affiliate")
                ->orWhere('type', "superadmin")
                ->orWhere('type', "dropshipper");
        })->first();

        if (isset($user)) {
            return sendError("This phone number is already registered.", '', 422);
        }

        return sendResponse("This phone number is available", $request->phone);

    }

}
