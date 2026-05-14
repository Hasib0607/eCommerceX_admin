<?php

namespace App\Http\Controllers;

use App\Models\AddonsOrder;
use App\Models\Design;
use App\Models\Headersetting;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Orderitem;
use App\Models\Store;
use App\Models\Transaction;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class MailController extends Controller
{


    /**
     * Upload multiple file
     *
     * @param Request $request
     * @return string
     */
    public function fileUpload(Request $request)
    {

        if ($request->filedata) {
            foreach ($request->filedata as $key => $file) {
                $fileName = time() + $key . '.' . $file->extension();
                $file->move('orders/', $fileName);
            }

            return 'success';
        } else {
            return 'sorry! ' . $request->filedata;
        }

    }


    /**
     * Send mail function
     *
     * @return response()
     */
    public function index()
    {
        $mailData = [
            'title' => 'New Order',
            'from_name' => 'Orderinfs',
            'fromMail' => 'Orderinfos@ebitans.com.bd',
            'subject' => 'Orderinfoset',
            'body' => 'This is for testing email using smtp.'
        ];
        // $data = [];
        //         Mail::to('kabir@ebitans.com.bd')->send(new ClientMail($mailData, $data));

        $headersetting = Headersetting::where('store_id', 14)->first();
        $store = Store::find(14);


        $id = 14;
        $data['invoices'] = Invoice::where('id', 51)->first();
        $data['invoice'] = Invoice::where('id', 51)->first();
        // dd($data['invoice']);

        $data['email'] = 'humayonkobir8@gmail.com';
        $data['store'] = Store::find($id);
        $data['design'] = Design::where('store_id', $data['store']->id)->first();
        $data['order'] = Order::find(5);
        $data['orderitems'] = Orderitem::where('order_id', $data['order']->id)->get();
        $data['transaction'] = Transaction::where('order_id', $data['order']->id)->first();

        if ($data['design']->invoice == 'one') {
            $data['invoiceNo'] = 2;
        } elseif ($data['design']->invoice == 'two') {
            $data['invoiceNo'] = 3;
        } elseif ($data['design']->invoice == 'three') {
            $data['invoiceNo'] = 4;
        } elseif ($data['design']->invoice == 'four') {
            $data['invoiceNo'] = 5;
        } elseif ($data['design']->invoice == 'six') {
            $data['invoiceNo'] = 6;
        } else {
            $data['invoiceNo'] = 2;
        }


        // if(isset($headersetting->email)){
        //     $mailData = [
        //         'title' => 'New Order from '. $store->name,
        //         'from_name' => 'eBitans',
        //         'fromMail' => 'ordersinfo@ebitans.com',
        //         'subject' => 'Your Order has been placed to...',
        //         'body' => "Your Order has been placed to ".$store->name. ". \nOrder Id: "  . "\nPrice: ".  "\nWe will get in touch with you shortly.\nFor Help:". $headersetting->phone

        //     ];

        //     Mail::to($headersetting->email)->send(new ClientMail($mailData, $data));
        //     // Mail::to('kabir@ebitans.com.bd')->send(new ClientMail($mailData, $data));
        // }

        // if(isset($user->email)){
        //     $mailData = [
        //         'title' => $store->name,
        //         'from_name' => $store->name,
        //         'fromMail' => $headersetting->email,
        //         'subject' => 'Your Order has been placed to...',
        //         'body' => "Your Order has been placed to ".$store->name. ". \nOrder Id: "  . "\nPrice: ".  "\nWe will get in touch with you shortly.\nFor Help:". $headersetting->phone
        //     ];

        //     Mail::to($user->email)->send(new ClientMail($mailData));
        // }

        $data['orderInfo'] = "Your Order has been placed to " . $store->name . ". \nOrder Id: " . "\nPrice: " . "\nWe will get in touch with you shortly.\nFor Help:" . $headersetting->phone;

        $data["title"] = "From " . $store->name;

        $pdf = Pdf::loadView('admin.invoice.index' . $data['invoiceNo'], $data);

        $data['data'] = AddonsOrder::find($id);
        Mail::send('clientPaymentMail', $data, function ($message) use ($data) {
            $message->from('orderinfo@ebitans.com', $data["title"])->to($data["email"], $data["email"])
                ->subject('Place a new order...');
        });

        /*dd('Mail sent successfully');


        dd($headersetting->email);*/
    }
}
