<?php

use App\Models\AddonsExpired;
use App\Models\Headersetting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Twilio\Rest\Client;

function SendSms($number, $text)
{
    $apiKey = env('BULKSMS_API_KEY', "");
    $senderId = env('SENDER_ID', "");

    if (env('APP_ENV') == "production") {
        return Http::get("http://bulksmsbd.net/api/smsapi?api_key=$apiKey&type=text&number=$number&senderid=$senderId&message=$text");
    }

//    return Http::get("http://bulksmsbd.net/api/smsapi?api_key=rrxyCNCq7qiG6NUQXKRc&type=text&number=" . $number . "&senderid=8809617611036&message=" . $text);
}

function sendOrderConfirmationText($store, $order)
{
    $headersetting = Headersetting::where('store_id', $store->id)->first();

    $message = "Your Order has been placed to " . $store->name .
        ". \nOrder Id: " . $order->reference_no . "\nPrice: " . $order->total . "\nWe will get in touch with you shortly.\nFor Help:" . $headersetting->phone;

    if (ModulusStatus($store->id, 119)) {
        $placeholders = [
            '{{store_name}}' => $store->name,
            '{{store_phone}}' => $headersetting->phone,
            '{{store_email}}' => $headersetting->email,
            '{{store_address}}' => $headersetting->address,
            '{{order_invoice}}' => $order->reference_no,
            '{{order_total}}' => $order->total,
            '{{customer_name}}' => $order->name,
            '{{customer_phone}}' => $order->phone,
            '{{customer_email}}' => $order->email,
            '{{customer_address}}' => $order->address,
            '{{line_break}}' => "\n",
        ];

        if (!is_null($headersetting->order_sms)) {
            $message = str_replace(array_keys($placeholders), array_values($placeholders), $headersetting->order_sms);
        }
    }

    return $message;
}


function addonSmsCount($store_id)
{
    $smsCount = AddonsExpired::where('store_id', $store_id)->where('addons_id', 5)->where('status', 1)->first();
    if (isset($smsCount)) {
        if ($smsCount->used < $smsCount->total) {
            $smsCount->used += 1;
            $smsCount->update();
            return 1;
        } else {
            $smsCount->status = 0;
            $smsCount->update();
            return 0;
        }
    }

    return 0;
}

function haveSMS($store_id)
{
    $smsCount = AddonsExpired::where('store_id', $store_id)->where('addons_id', 5)->where('status', 1)->first();
    if (isset($smsCount)) {
        if ($smsCount->used < $smsCount->total) {
            return 1;
        }
    }

    return 0;
}


function getSmsCount($store_id)
{
    $smsCount = AddonsExpired::where('store_id', $store_id)->where('addons_id', 5)->where('status', 1)->first();
    if (isset($smsCount)) {
        $sms = (int)$smsCount->total - (int)$smsCount->used;
        return $sms;
    }

    return 0;
}


// Renew sms send
function packageExpiryNotification($store, $day = 7)
{
    $user = User::find($store->user_id);

    if ($day == 7 || $day == 3) {
        $message = "প্রিয় " . ($user->name ?? "eBitans User,") . ", আপনার স্টোর $store->name আগামী $day দিনের মধ্যে মেয়াদ শেষ হতে যাচ্ছে। নিরবচ্ছিন্ন সেবা নিশ্চিত করতে অনুগ্রহ করে এখনই payment করুন " . env('BKASH_PAYMENT_NUMBER') . " (bKash Payment)";
    } else if ($day == 1) {
        $message = "প্রিয় " . ($user->name ?? "eBitans User,") . ", আপনার স্টোর $store->name এর সেবা আজ শেষ হতে চলেছে। নিরবচ্ছিন্ন সেবা নিশ্চিত করতে অনুগ্রহ করে এখনই payment করুন " . env('BKASH_PAYMENT_NUMBER') . " (bKash Payment)";
    } else {
        $message = "প্রিয় " . ($user->name ?? "eBitans User,") . ", আপনার স্টোর $store->name এর সেবা শেষ হয়ে গেছে পুনরায় চালু করতে এখনই payment করুন " . env('BKASH_PAYMENT_NUMBER') . " (bKash Payment)";
    }

    $store->pay_mail_status = 1;
    $store->save();

    $phone = $user->phone ?? "";
    $email = $user->email ?? "";

    if (isset($phone) && !empty($phone)) {
        SendSms($phone, $message); // phone, text
        smsLogger($phone, $message, "Payment notification");
    }

    if (isset($email) && !empty($email)) {
        $data['name'] = $user->name ?? "User";
        $data['subject'] = "Payment Notification";
        $data['text'] = $message;
        $data['formEmail'] = env('MAIL_FROM_ADDRESS');
        $data['email'] = $email;

        try {
            Mail::send('email.payment-notification', $data, function ($message) use ($data) {
                $message->from($data['formEmail'], $data["subject"])->to($data["email"], $data["email"])
                    ->subject('Payment Notification');
            });
        } catch (\Exception) {

        }

    }
}


function smsLogger($phone, $text, $purpose = null, $user_type = 1, $store_id = null)
{
    $sms = new \App\Models\SMSLogger();
    $sms->store_id = $store_id ?? null;
    $sms->user_type = $user_type ?? null;
    $sms->purpose = $purpose ?? null;
    $sms->phone = $phone;
    $sms->text = $text;
    $sms->save();
}

function TwilioSms($number = "+8801712714334", $text = "Hello this is a test message from eBitans")
{
    try {
        $sid = env('TWILIO_ACCOUNT_SID');
        $token = env('TWILIO_AUTH_TOKEN');
        $formNumber = env('TWILIO_FROM_NUMBER');

        if (empty($sid) || empty($token) || empty($formNumber)) {
            return;
        }

        $twilio = new Client($sid, $token);
        $twilio->messages->create(
            $number,
            [
                "from" => $formNumber,
                "body" => $text,
            ]
        );
    } catch (\Twilio\Exceptions\RestException $e) {
        // Twilio API error — log in app channel if needed
    } catch (\Exception $e) {
        // General error
    }
}
