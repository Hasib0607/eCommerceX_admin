<?php

namespace App\Http\Controllers\PaymentGateway;

use App\Http\Controllers\Controller;
use App\Models\BkashIDToken;
use App\Models\Headersetting;
use App\Models\Order;
use App\Models\Paymentgateway;
use App\Models\Store;
use App\Models\Transaction;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\URL;

class BkashController extends Controller
{
    private $base_url;

    public function __construct()
    {
        // Live
        $this->base_url = 'https://tokenized.pay.bka.sh/v1.2.0-beta';
    }

    public function payment(Request $request)
    {
        return view('CheckoutURL.pay');
    }

    public function orderPay(Request $req)
    {
        $order = Order::find($req->order);

        $paymentGet = Paymentgateway::where('store_id', $order->store_id)
            ->where('payment_company', 'Bkash')
            ->first();

        // store credential in session for later calls
        Session::put('app_key', $paymentGet->app_key);
        Session::put('app_secret', $paymentGet->app_secret);
        Session::put('api_username', $paymentGet->api_username);
        Session::put('api_password', $paymentGet->api_password);

        $amount = $order->total;
        $inv = $order->reference_no;

        $paymentTy = Transaction::where('order_id', $order->id)->first();

        Session::forget('payment_amount');

        if (isset($paymentTy->mode) && $paymentTy->mode == 'ap') {
            $amo = Headersetting::convertCurrency($order->store_id)->first();

            if (isset($amo->payment_type) && $amo->payment_type == 1) {
                $advance = ceil($amount * $amo->prepayment / 100);
                $order->paid = $advance;
                $order->due = $amount - $advance;
                $order->save();
            } else {
                $advance = $amo->prepayment;
                $order->paid = $advance;
                $order->due = $amount - $advance;
                $order->save();
            }
            Session::put('payment_amount', $advance);
        } else {
            $order->due = $amount;
            $order->save();
            Session::put('payment_amount', $amount);
        }

        Session::put('invoice', $inv);
        Session::put('order_id', $order->id);
        Session::put('store_id', $order->store_id);

        return $this->createPayment();
    }

    public function createPayment()
    {
        $store = Store::find(Session::get('store_id') ?? "");

        try {
            $header = $this->authHeaders();

            $website_url = URL::to("/");

            $body_data = array(
                'mode' => '0011',
                'payerReference' => ' ',
                'callbackURL' => $website_url . '/bkash/callback?order=' . Session::get('order_id'),
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
                $URLquery = "/payment/failed?error_msg=Something went wrong!";
                return Redirect::to('https://' . $store->url . $URLquery);
            }

        } catch (\Exception $e) {
            \Log::error('createPayment Exception: ' . $e->getMessage());
            $URLquery = "/payment/failed?error_msg=Something went wrong!";
            return Redirect::to('https://' . $store->url . $URLquery);
        }
    }

    public function authHeaders()
    {
        return array(
            'Content-Type:application/json',
            'Authorization:' . $this->grant(),
            'X-APP-Key:' . Session::get('app_key')
        );
    }

    public function getBkashToken()
    {
        $lastTwoHours = Carbon::now()->subHours(2);

        $isTokenValid = BkashIDToken::where("store_id", Session::get('store_id'))
            ->where("updated_at", ">", $lastTwoHours)
            ->first();

        return $isTokenValid ? $isTokenValid->id_token : null;
    }

    public function grant()
    {
        $store = Store::find(Session::get('store_id') ?? "");

        try {
            $isTokenValid = $this->getBkashToken();
            if ($isTokenValid) {
                return $isTokenValid;
            }

            $header = array(
                'Content-Type:application/json',
                'username:' . Session::get('api_username'),
                'password:' . Session::get('api_password')
            );
            $body_data = array('app_key' => Session::get('app_key'), 'app_secret' => Session::get('app_secret'));
            $body_data_json = json_encode($body_data);

            $response = $this->curlWithBody('/tokenized/checkout/token/grant', $header, 'POST', $body_data_json);

            $token = json_decode($response)->id_token ?? null;

            if ($token) {
                $storeToken = BkashIDToken::where("store_id", Session::get('store_id'))->first();
                if (isset($storeToken)) {
                    $storeToken->id_token = $token;
                    $storeToken->update();
                } else {
                    $storeToken = new BkashIDToken();
                    $storeToken->store_id = Session::get('store_id') ?? NULL;
                    $storeToken->id_token = $token;
                    $storeToken->save();
                }
                return $token;
            }

            // fallback
            return null;

        } catch (\Exception $e) {
            \Log::error('grant Exception: ' . $e->getMessage());
            $URLquery = "/payment/failed?error_msg=Something went wrong!";
            return Redirect::to('https://' . $store->url . $URLquery);
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

        if ($response === false) {
            $err = curl_error($curl);
            curl_close($curl);
            \Log::error("cURL error: " . $err);
            return json_encode(['message' => 'curl_error', 'detail' => $err]);
        }

        curl_close($curl);

        $res = json_decode($response);
        if (isset($res->message)) {
            // if bkash returns message (token expired or other), delete stored token and retry once
            $token = BkashIDToken::where("store_id", Session::get('store_id'))->first();

            if (isset($token)) {
                $token->delete();
            }

            // try to regenerate header and call again once
            $header = $this->authHeaders();
            $responseRetry = curl_init($this->base_url . $url);
            curl_setopt($responseRetry, CURLOPT_HTTPHEADER, $header);
            curl_setopt($responseRetry, CURLOPT_CUSTOMREQUEST, $method);
            curl_setopt($responseRetry, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($responseRetry, CURLOPT_POSTFIELDS, $body_data_json);
            curl_setopt($responseRetry, CURLOPT_FOLLOWLOCATION, 1);
            curl_setopt($responseRetry, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
            $response2 = curl_exec($responseRetry);
            if ($response2 === false) {
                $err = curl_error($responseRetry);
                curl_close($responseRetry);
                \Log::error("cURL retry error: " . $err);
                return json_encode(['message' => 'curl_error', 'detail' => $err]);
            }
            curl_close($responseRetry);
            return $response2;
        }

        return $response;
    }

    /**
     * Callback entry from bKash after user completes / cancels / fails payment on bKash page
     */
    public function callback(Request $request)
    {
        $allRequest = $request->all();

        // normalize incoming paymentID names
        $paymentID = $allRequest['paymentID'] ?? $allRequest['paymentId'] ?? $allRequest['payment_id'] ?? null;

        $orderId = $request->order ?? $allRequest['order'] ?? $allRequest['order_id'] ?? null;
        $order = Order::find($orderId);

        if (!$order) {
            \Log::error("Bkash callback: Order not found for id: " . $orderId);
            return redirect('/')->with('error', 'Order not found');
        }

        // ensure we have credentials for this store
        $paymentGet = Paymentgateway::where('store_id', $order->store_id)
            ->where('payment_company', 'Bkash')
            ->first();
        $store = Store::find($order->store_id);

        if ($paymentGet) {
            Session::put('app_key', $paymentGet->app_key);
            Session::put('app_secret', $paymentGet->app_secret);
            Session::put('api_username', $paymentGet->api_username);
            Session::put('api_password', $paymentGet->api_password);
            Session::put('store_id', $order->store_id);
        }

        // handle explicit failure/cancel from bkash callback (some flows send status param)
        if (isset($allRequest['status']) && $allRequest['status'] == 'failure') {
            $order->status = 'Payment Failure';
            $order->update();
            $URLquery = "/payment/failed?error_msg=Transaction failed!";
            return Redirect::to('https://' . $store->url . $URLquery);
        } elseif (isset($allRequest['status']) && $allRequest['status'] == 'cancel') {
            $order->status = 'Payment Cancel';
            $order->update();
            $URLquery = "/payment/failed?error_msg=Transaction Cancel!";
            return Redirect::to('https://' . $store->url . $URLquery);
        }

        if (!$paymentID) {
            \Log::error("Bkash callback: Missing paymentID for order {$order->id}");
            $URLquery = "/payment/failed?error_msg=Missing paymentID!";
            return Redirect::to('https://' . $store->url . $URLquery);
        }

        try {
            // try execute first
            $executeRaw = $this->executePayment($paymentID);
            $executeArr = json_decode($executeRaw, true) ?? [];

            // If execute returned a success (trxID present or statusCode indicates success) then save
            if ($this->isSuccessfulResponse($executeArr)) {
                $this->saveTransactionAndOrder($executeArr, $order);
                $transactionId = $this->getValue($executeArr, 'trxID');
                $amountPaid = $this->getValue($executeArr, 'amount');
                $URLquery = "/payment/success?msg=Payment success.&transaction_id={$transactionId}&total={$amountPaid}";
                return Redirect::to('https://' . $store->url . $URLquery);
            }

            // If execute didn't return final success, query status endpoint (some providers do this)
            sleep(1); // small pause before query
            $queryRaw = $this->queryPayment($paymentID, $order->id, $executeArr);
            $queryArr = json_decode($queryRaw, true) ?? [];

            if ($this->isSuccessfulResponse($queryArr)) {
                $this->saveTransactionAndOrder($queryArr, $order);
                $transactionId = $this->getValue($queryArr, 'trxID');
                $amountPaid = $this->getValue($queryArr, 'amount');
                $URLquery = "/payment/success?msg=Payment success.&transaction_id={$transactionId}&total={$amountPaid}";
                return Redirect::to('https://' . $store->url . $URLquery);
            }

            // nothing successful
            \Log::warning("Bkash callback: Payment not successful for order {$order->id}. execute:", $executeArr);
            \Log::warning("Bkash callback: Query result:", $queryArr);
            $URLquery = "/payment/failed?error_msg=Transaction failed!";
            return Redirect::to('https://' . $store->url . $URLquery);

        } catch ( \Exception $e) {
            \Log::error("Bkash callback exception: " . $e->getMessage());
            $URLquery = "/payment/failed?error_msg=Transaction failed!";
            return Redirect::to('https://' . $store->url . $URLquery);
        }
    }

    /**
     * Execute payment (checkout/execute)
     */
    public function executePayment($paymentID)
    {
        $header = $this->authHeaders();

        $body_data = array(
            'paymentID' => $paymentID
        );
        $body_data_json = json_encode($body_data);

        $response = $this->curlWithBody('/tokenized/checkout/execute', $header, 'POST', $body_data_json);

        // return raw response (string) to preserve original shape for logging if needed
        return $response;
    }

    /**
     * Query payment status endpoint
     * returns raw response string
     */
    public function queryPayment($paymentID, $order_id = null, $allInfo = [])
    {
        $header = $this->authHeaders();

        $body_data = array(
            'paymentID' => $paymentID,
        );
        $body_data_json = json_encode($body_data);

        $response = $this->curlWithBody('/tokenized/checkout/payment/status', $header, 'POST', $body_data_json);

        $res_array = json_decode($response, true) ?? [];

        // If trxID present, we also attempt to save here (safer to call from callback)
        if (isset($res_array['trxID'])) {
            if ($order_id) {
                $order = Order::find($order_id);
                if ($order) {
                    $this->saveTransactionAndOrder($res_array, $order, $allInfo);
                }
            }
        }

        return $response;
    }

    /**
     * Centralized DB update for Transaction and Order
     * Accepts array (decoded response) and Order model
     */
    protected function saveTransactionAndOrder(array $responseArr, Order $order, $allInfo = [])
    {
        // normalize fields
        $trxId = $this->getValue($responseArr, 'trxID');
        $amount = $this->getValue($responseArr, 'amount');
        $paymentExecuteTime = $this->getValue($responseArr, 'paymentExecuteTime') ?? $this->getValue($responseArr, 'paymentExecutionTime') ?? null;
        $customerMsisdn = $this->getValue($responseArr, 'customerMsisdn') ?? $this->getValue($responseArr, 'payerMsisdn') ?? null;

        try {
            // ensure Transaction row exists
            $tra = Transaction::where('order_id', $order->id)->first();
            if (!$tra) {
                $tra = new Transaction();
                $tra->order_id = $order->id;
                $tra->store_id = $order->store_id;
                $tra->status = 'Pending';
                $tra->save();
            }

            // update transaction fields safely
            if ($customerMsisdn) {
                $tra->number = $customerMsisdn;
            }

            if ($trxId) {
                $tra->transaction_id = $trxId;
            }
            $tra->status = 'Paid';
            $tra->update();

            // update order
            // if you use 'ap' (advance payment) logic, keep it as partial – else mark fully paid
            $paymentTy = Transaction::where('order_id', $order->id)->first();
            if (isset($paymentTy->mode) && $paymentTy->mode == 'ap') {
                $amo = Headersetting::where('store_id', $order->store_id)->first();
                if (isset($amo->prepayment)) {
                    $order->paid = $amo->prepayment;
                    $order->due = $order->total - $amo->prepayment;
                    $order->status = 'Partial Paid';
                } else {
                    // fallback
                    $order->paid = $amount ?? $order->total;
                    $order->due = $order->total - $order->paid;
                    $order->status = 'Partial Paid';
                }
            } else {
                $order->paid = $amount ?? $order->total;
                $order->due = max(0, ($order->total - $order->paid));
                //$order->status = 'Payment Success';
                $order->status = $order->total == $amount ? 'Payment Success' : 'Partial Paid';
                $order->update();
            }

            if ($trxId) {
                $order->transaction_id = $trxId;
            }

            // Optionally save processed time if present
            if ($paymentExecuteTime) {
                // try to format if necessary, else store raw
                $order->date_processed = $paymentExecuteTime;
            }

            $order->update();

            return true;
        } catch (Exception $e) {
            \Log::error("saveTransactionAndOrder Exception: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Utility: check whether parsed response indicates success
     */
    protected function isSuccessfulResponse(array $arr)
    {
        if (empty($arr)) return false;

        // direct trxID is strongest signal
        if (isset($arr['trxID']) && !empty($arr['trxID'])) {
            return true;
        }

        // other possible indicators
        if (isset($arr['statusCode']) && ($arr['statusCode'] === '0000' || $arr['statusCode'] === 0)) {
            return true;
        }

        if (isset($arr['transactionStatus']) && in_array(strtolower($arr['transactionStatus']), ['completed', 'success'])) {
            return true;
        }

        if (isset($arr['status']) && in_array(strtolower($arr['status']), ['completed', 'success'])) {
            return true;
        }

        return false;
    }

    /**
     * Utility: safely get variant keys
     */
    protected function getValue(array $arr, $key)
    {
        return $arr[$key] ?? null;
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
        // TODO: handle response and save refund info if needed
    }
}
