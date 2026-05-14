<?php

namespace App\Http\Controllers\PaymentGateway;

use App\Http\Controllers\Controller;
use App\Models\AddonsOrder;
use App\Models\BkashIDToken;
use App\Models\Paymenttoken;
use App\Models\User;
use App\Util\BkashCredential;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\URL;
use Log;

class AdminBkashController extends Controller
{

    private $base_url;
    private $app_key;
    private $app_secret;
    private $username;
    private $password;

    public function __construct()
    {
        // Live
        if (env('BKASH_SANDBOX')) {
            $this->base_url = 'https://tokenized.sandbox.bka.sh/v1.2.0-beta';
        } else {
            $this->base_url = 'https://tokenized.pay.bka.sh/v1.2.0-beta';
        }

        $this->app_key = env('BKASH_APP_KEY');
        $this->app_secret = env('BKASH_APP_SECRET');
        $this->username = env('BKASH_USERNAME');
        $this->password = env('BKASH_PASSWORD');
    }

    public function payment(Request $request)
    {
        return view('CheckoutURL.pay');
    }

    public function orderPay(Request $req)
    {
        $order = AddonsOrder::find($req->order);
        $amount = $order->total;

        $inv = 'EB' . Carbon::now()->timestamp;
        Session::forget('payment_amount');
        Session::put('payment_amount', $amount);

        Session::forget('invoice');
        Session::put('invoice', $inv);

        Session::forget('order_id');
        Session::put('order_id', $order->id);

        return $this->createPayment();
    }

    public function createPayment()
    {
        try {
            $header = $this->authHeaders();

            $website_url = URL::to("/");

            $body_data = array(
                'mode' => '0011',
                'payerReference' => ' ',
                'callbackURL' => $website_url . '/admin/bkash/callback?order=' . Session::get('order_id'),
                'amount' => Session::get('payment_amount'),
                'currency' => 'BDT',
                'intent' => 'sale',
                'merchantInvoiceNumber' => "Inv" . Session::get('invoice') // you can pass here OrderID
            );
            $body_data_json = json_encode($body_data);

            $response = $this->curlWithBody('/tokenized/checkout/create', $header, 'POST', $body_data_json);

            $responseData = json_decode($response);

            // Check if the response contains the expected property
            if (isset($responseData->bkashURL)) {
                return redirect($responseData->bkashURL);
            } else {
                // Log the unexpected response
//                Log::error('Unexpected API response: ' . $response);

                // Handle the error gracefully, for example, by redirecting to an error page
//              return view('error')->with('message', 'Unexpected API response');
                Session::flash('error', 'Something went wrong');
                return Redirect::to('/');
            }
        } catch (Exception $e) {
            Session::flash('error', 'Something went wrong');
            return Redirect::to('/');
        }
    }

    public function authHeaders()
    {
        return array(
            'Content-Type:application/json',
            'Authorization:' . $this->grant(),
            'X-APP-Key:' . $this->app_key
        );
    }

    public function getBkashToken()
    {
        $lastTwoHours = Carbon::now()->subHours(2);

        $isTokenValid = BkashIDToken::whereNull("store_id")
            ->where("isAdmin", 1)
            ->where("updated_at", ">", $lastTwoHours)
            ->first();

        return $isTokenValid ? $isTokenValid->id_token : null;
    }

    public function grant()
    {
        try {
            $isTokenValid = $this->getBkashToken();
            if ($isTokenValid) {
                return $isTokenValid;
            }


            $header = array(
                "Content-Type:application/json",
                "username:$this->username",
                "password:$this->password"
            );
            $header_data_json = json_encode($header);

            $body_data = array(
                'app_key' => $this->app_key,
                'app_secret' => $this->app_secret
            );
            $body_data_json = json_encode($body_data);

            $response = $this->curlWithBody('/tokenized/checkout/token/grant', $header, 'POST', $body_data_json);

            $token = json_decode($response)->id_token;

            $store = BkashIDToken::whereNull("store_id")
                ->where("isAdmin", 1)
                ->first();

            if (isset($store)) {
                $store->id_token = $token;
                $store->update();
            } else {
                $store = new BkashIDToken();
                $store->store_id = NULL;
                $store->isAdmin = 1;
                $store->id_token = $token;
                $store->save();
            }

            return $token;
        } catch (Exception $e) {
            Session::flash('error', 'Something went wrong');
            return Redirect::to('/');
        }
    }

    public function curlWithBody($url, $header, $method, $body_data_json)
    {
        $curl = curl_init($this->base_url . $url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $body_data_json);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($curl, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
        $response = curl_exec($curl);
        curl_close($curl);

        $res = json_decode($response);
        if (isset($res->message)) {
            $token = BkashIDToken::whereNull("store_id")
                ->where("isAdmin", 1)
                ->first();

            if (isset($token)) {
                $token->delete();
            }

            $header = $this->authHeaders();
            return $this->curlWithBody($url, $header, $method, $body_data_json);
        }

        return $response;
    }

    public function callback(Request $request)
    {
        $allRequest = $request->all();
        $order = AddonsOrder::find($request->order);

        if (isset($allRequest['status']) && ($allRequest['status'] == 'cancel' || $allRequest['status'] == 'failure')) {
            $msg = "Transaction failed.";
            if ($allRequest['status'] == 'cancel') {
                $msg = "Transaction cancel.";
            }

            return redirect()->route('payment.payments')->with('error', $msg);
        } else {
            $response = $this->executePayment($allRequest['paymentID']);

            if (!isset($response)) {
                $response = $this->queryPayment($allRequest['paymentID']);
            }

            if (isset($response['statusCode']) && $response['statusCode'] == "0000" && $response['transactionStatus'] == "Completed") {
                // your database insert operation
                // insert $response to your db
                $order = AddonsOrder::find($order->id);
                $order->payment_method = "bkash";
                $order->transaction_id = $response['trxID'];
                $order->payment_number = $response["customerMsisdn"];
                $order->update();

                (new AcceptPlanController())->acceptPlanOrder($order->id);

                $msg = "Payment successful.";
                return redirect()->route('payment.payments')->with('success', $msg);
            }

            return Redirect::to('/');
        }

    }

    public function executePayment($paymentID)
    {
        $header = $this->authHeaders();

        $body_data = array(
            'paymentID' => $paymentID
        );
        $body_data_json = json_encode($body_data);

        $response = $this->curlWithBody('/tokenized/checkout/execute', $header, 'POST', $body_data_json);

        return json_decode($response, true);
    }

    public function queryPayment($paymentID)
    {
        $header = $this->authHeaders();

        $body_data = array(
            'paymentID' => $paymentID,
        );

        $body_data_json = json_encode($body_data);

        $response = $this->curlWithBody('/tokenized/checkout/payment/status', $header, 'POST', $body_data_json);

        return json_decode($response, true);
    }


    public function getRefund(Request $request)
    {
        return view('CheckoutURL.refund');
    }

    public function refundPayment(Request $request)
    {
        $header = $this->authHeaders();

        $body_data = array(
            'paymentID' => $request->paymentID,
            'amount' => $request->amount,
            'trxID' => $request->trxID,
            'sku' => 'sku',
            'reason' => 'Quality issue'
        );

        $body_data_json = json_encode($body_data);

        $response = $this->curlWithBody('/tokenized/checkout/payment/refund', $header, 'POST', $body_data_json);

        return view('CheckoutURL.refund')->with([
            'response' => $response,
        ]);
    }
}
