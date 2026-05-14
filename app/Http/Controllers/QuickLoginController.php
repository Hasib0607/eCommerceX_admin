<?php

namespace App\Http\Controllers;

use App\Models\BookingCustomerFiled;
use App\Models\Customer;
use App\Models\Headersetting;
use App\Models\Paymenttoken;
use App\Models\QuickLogin;
use App\Models\Staff;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Response;
use Laravel\Socialite\Facades\Socialite;

class QuickLoginController extends Controller
{


    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function sociaId(Request $request)
    {
        $QuickLogin = QuickLogin::where('store_id', $request->store_id)->get();

        return response()->json(['sociaId' => $QuickLogin], 200);

    }


    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function googleLogin(Request $user)
    {
        try {

            // $googleUser = Socialite::driver('google')->stateless()->user();
            // $user = null;
            // $user = Socialite::driver('google')->user();
            $finduser = User::where('google_id', $user->google_id)->first();

            if ($finduser) {

                Auth::login($finduser);
                $token = Auth::user()->createToken('AuthToken')->plainTextToken;

                $user = Auth::user();

                $verify = true;
                $payt = new Paymenttoken();
                $payt->token = $token;
                $payt->uid = $user->id;
                $payt->save();
                $verify = true;
                // return response()->json(['token' => $token->token, 'details' => $user], 200);
                return response()->json(['token' => $token, 'verify' => true], 200);

                // return redirect()->intended('dashboard');

            } else {
                $newUser = User::create([
                    'name' => $user->name,
                    'email' => $user->email,
                    'social_img' => $user->picture,
                    'google_id' => $user->google_id,
                    'store_id' => $user->store_id,
                    'type' => 'customer',
                    'auth_type' => $user->auth_type,
                    'otp' => 'NULL',
                    'password' => Hash::make($user->google_id)
                ]);

                Auth::login($newUser);

                $token = Auth::user()->createToken('AuthToken')->plainTextToken;

                $user = Auth::user();
                $verify = true;
                $payt = new Paymenttoken();
                $payt->token = $token;
                $payt->uid = $user->id;
                $payt->save();

                // return response()->json(['token' => $token->token, 'details' => $user], 200);
                return response()->json(['token' => $token, 'verify' => true], 200);


                // return redirect()->intended('dashboard');
            }

        } catch (Exception $e) {
            // dd($e->getMessage());
            return response()->json(['error' => 'Sorry System error'], 200);
        }
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
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function quickLoginInfo()
    {
        //
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function quickLoginInfoStore(Request $request)
    {
        $user_type = Auth::user()->type;
        if ($user_type == "admin" || $user_type == "dropshipper") {
            $customer = Customer::where('uid', Auth::user()->id)->first();
            $store_id = $customer->active_store;
            $customer_id = $customer->id;
        } elseif ($user_type == 'staff') {
            $staff = Staff::where('uid', Auth::user()->id)->first();
            $store_id = $staff->store_id;
            $customer_id = $staff->customer_id;
        }

        $QuickLogin = QuickLogin::firstOrNew(
            ['modulus_id' => $request->modulus_id, 'store_id' => $store_id, 'type' => $request->type]
        );

        if ($request->modulus_id == 108) {
            foreach ($request->name as $key => $name) {
                $tagId = isset($request->tagId[$key]) ? $request->tagId[$key] : null;

                if ($tagId !== null) {
                    // Validate and sanitize is_checked value
                    $is_checked = isset($request->is_checked[$key]) ? intval($request->is_checked[$key]) : 0;
                    $is_checked = ($is_checked === 1) ? 1 : 0;

                    // If is_checked is checked, update is_required
                    $is_required = $is_checked ? (isset($request->is_required[$key]) ? intval($request->is_required[$key]) : 0) : 0;
                    $is_required = ($is_required === 1) ? 1 : 0;

                    // Map activation type to 0 or 1
                    $from_type = ($request->from_type === 'double') ? 0 : 1;

                    BookingCustomerFiled::updateOrCreate(
                        [
                            'modulus_id' => $request->modulus_id,
                            'uId' => Auth::user()->id,
                            'tagId' => $tagId,
                        ],
                        [
                            'name' => $name,
                            'is_checked' => $is_checked,
                            'is_required' => $is_required,
                            'store_id' => $store_id,
                            'customer_id' => $customer_id,
                            'is_single' => $from_type,
                        ]
                    );
                }
            }
        }

        if ($request->modulus_id == 7 || $request->modulus_id == 5) {
            $hd = Headersetting::where('store_id', $store_id)->first();

            if (isset($request->client_secret)) {
                $hd->messenger_link = $request->client_secret;
            }
            if (isset($request->client_id)) {
                $hd->facebook_app_id = $request->client_id;
            }
            if (isset($request->app_id)) {
                $hd->facebook_login = $request->app_id;
            }

            $hd->update();
        }

        $QuickLogin->modulus_id = $request->modulus_id;
        $QuickLogin->store_id = $store_id;
        $QuickLogin->type = $request->type;
        $QuickLogin->client_id = $request->client_id;
        $QuickLogin->client_secret = $request->client_secret;
        $QuickLogin->app_id = $request->app_id;

        $QuickLogin->facebook_pixel = $request->facebook_pixel;
        $QuickLogin->general_access_token = $request->general_access_token ?? NULL;
        $QuickLogin->test_event_code = $request->test_event_code ?? NULL;
        $QuickLogin->domain_verification_code = $request->domain_verification_code ?? NULL;
        $QuickLogin->google_analytics = $request->google_analytics;
        $QuickLogin->google_tag_manager = $request->google_tag_manager;
        $QuickLogin->google_search_console = $request->google_search_console;
        $QuickLogin->save();
        return back();
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
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
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
     * Remove the specified resource from storage.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
}
