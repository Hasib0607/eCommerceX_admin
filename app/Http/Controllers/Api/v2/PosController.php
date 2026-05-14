<?php

namespace App\Http\Controllers\Api\v2;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Branchproduct;
use App\Models\Cartitem;
use App\Models\Category;
use App\Models\Headersetting;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Orderitem;
use App\Models\Product;
use App\Models\Store;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Veriant;
use Auth;
use Cart;
use Hash;
use Illuminate\Http\Request;

class PosController extends Controller
{
    public function getcat(Request $request)
    {
        $bid = decrypt($request->id);
        $branch = Branch::find($bid);
        $category = Category::where('store_id', $branch->store_id)->get();
        if (isset($category) && count($category) > 0) {
            foreach ($category as $key => $cats) {
                $cat[$key]['id'] = $cats->id;
                $cat[$key]['name'] = $cats->name;
                $cat[$key]['banner'] = $cats->banner;
                $cat[$key]['icon'] = $cats->icon;
                $product = Product::where('category', $cats->id)->orWhere('subcategory', $cats->id)->count();
                $cat[$key]['product'] = $product;
                $product = 0;
            }
        }
        return response()->json([
            'data' => $cat
        ]);
    }

    public function getproducts(Request $request)
    {
        $bid = decrypt($request->id);
        $branch = Branch::find($bid);
        $product = Branchproduct::where('branch_id', $bid)->get();
        if (isset($product) && count($product) > 0) {
            foreach ($product as $key => $pps) {
                $pp = Product::where('id', $pps->product_id)->first();
                if (isset($pp)) {
                    if ($pp->images) {
                        $img = explode(',', $pp->images);
                        $data[$key]['image'] = $img[0] ?? 'Product_03.jpg';
                    }
                    $data[$key]['id'] = $pp->id;
                    $data[$key]['name'] = $pp->name;
                    $data[$key]['regular_price'] = $pp->regular_price;
                    $data[$key]['discount_type'] = $pp->discount_type;
                    $data[$key]['promotional_price'] = $pp->promotional_price;
                    $veriant = Veriant::convertCurrency($pp->id)->get();
                    if (isset($veriant) && count($veriant) > 0) {
                        $data[$key]['vr'] = 1;
                        $data[$key]['veriant'] = $veriant;
                    } else {
                        $data[$key]['vr'] = 0;
                        $data[$key]['veriant'] = [];
                    }
                }
                $pp = null;
            }
        } else {
            $data = [];
        }

        return response()->json([
            'data' => $data
        ]);
    }

    public function addtocart(Request $request)
    {
//        $userData = getUserData();
//        $store_id = $userData['store_id'];
        $id = $request->id;
        $product = Product::find($id);
//        $product = Product::convertCurrency($store_id)->find($id);
        if ($product->discount_type == "fixed") {
            $price = $product->regular_price - $product->promotional_price;
            $discount = $product->promotional_price;
        } elseif ($product->discount_type == "percent") {
            $price = $product->regular_price - ($product->promotional_price / 100) * $product->regular_price;
            $discount = ($product->promotional_price / 100) * $product->regular_price;
        } else {
            $price = $product->regular_price;
            $discount = "0";
        }
        $exist = Cartitem::where('product_id', $id)->where('session_id', $request->session)->first();
        if (isset($exist)) {
            $exist->quantity = $exist->quantity + 1;
            $exist->save();
            $catitem = $exist;
        } else {
            $catitem = new Cartitem();
            $catitem->session_id = $request->session;
            $catitem->product_id = $id;
            $img = explode(',', $product->images);
            $catitem->image = $img[0];
            $catitem->bid = $request->bid;
            $catitem->variant_id = null;
            $catitem->name = $product->name;
            $catitem->quantity = 1;
            $catitem->price = $price;
            $catitem->discount = $discount;
            $catitem->save();
        }
        // $data=Cart::instance('cart')->add($product->id, $product->name, 1, $price,['discount'=>$discount])->associate('App\Models\Product');
        // $data=$id;
        return $catitem;
    }

    public function addveritocart(Request $request)
    {
        $id = $request->id;
        $bid = $request->bid;
        $store_id = Branch::where("id", $bid)->value("store_id");
        $veriant = Veriant::find($id);
        $product = Product::convertCurrency($store_id)->find($veriant->pid);
        if ($product->discount_type == "fixed") {
            $price = $product->regular_price - $product->promotional_price;
            $discount = $product->promotional_price;
        } elseif ($product->discount_type == "percent") {
            $price = $product->regular_price - ($product->promotional_price / 100) * $product->regular_price;
            $discount = ($product->promotional_price / 100) * $product->regular_price;
        } else {
            $price = $product->regular_price;
            $discount = "0";
        }
        $exist = Cartitem::where('product_id', $veriant->pid)->where('variant_id', $id)->where('session_id',
            $request->session)->first();
        if (isset($exist)) {
            $exist->quantity = $exist->quantity + 1;
            $exist->save();
            $catitem = $exist;
        } else {
            $catitem = new Cartitem();
            $catitem->session_id = $request->session;
            $catitem->product_id = $veriant->pid;
            $catitem->variant_id = $id;
            $catitem->name = $product->name;
            $catitem->bid = $request->bid;
            $img = explode(',', $product->images);
            $catitem->image = $img[0];
            $catitem->quantity = 1;
            $catitem->price = $price;
            $catitem->discount = $discount;
            $catitem->color = $veriant->color;
            $catitem->size = $veriant->size;
            $catitem->unit = $veriant->unit;
            $catitem->volume = $veriant->volume;
            $catitem->save();
        }

        return $catitem;
    }

    public function getcarts(Request $request)
    {
        $subtotal = 0;
        $total = 0;
        $discount = 0;
        $totaltax = 0;
        $data = Cartitem::where('session_id', $request->session)->where('bid', $request->bid)->get();
        $tax = Branch::where('id', $request->bid)->value('tax') ?? 0;
        if (isset($data) && count($data) > 0) {
            foreach ($data as $dats) {
                $subtotal = $subtotal + ($dats->quantity * ($dats->price + $dats->discount));
                $totaltax = $totaltax + ceil((float)$this->calculateTaxForPos($subtotal, $tax));
                $total = ($total + ($dats->quantity * $dats->price)) + $totaltax;
                $discount = $discount + ($dats->quantity * $dats->discount);
            }
        }
        return response()->json([
            'data' => $data,
            'subtotal' => $subtotal,
            'total' => $total,
            'discount' => $discount,
            'tax' => $totaltax
        ]);
    }

    public function incrementcart(Request $request)
    {
        $exist = Cartitem::where('id', $request->id)->where('session_id', $request->session)->first();
        $exist->quantity = $exist->quantity + 1;
        $exist->save();
        return $exist;
    }

    public function decrementcart(Request $request)
    {
        $exist = Cartitem::where('id', $request->id)->where('session_id', $request->session)->first();
        if ($exist->quantity == 1) {
            $exist->delete();
            return 1;
        } else {
            $exist->quantity = $exist->quantity - 1;
            $exist->save();
            return $exist;
        }
    }

    public function removecart(Request $request)
    {
        Cartitem::find($request->id)->delete();
        return 1;
    }

    public function getcatproduct(Request $request)
    {
        $id = $request->id;
        $datas = [];
        $products = Branchproduct::where('branch_id', $request->bid)->get();
        if (isset($products) && count($products) > 0) {
            foreach ($products as $prod) {
                $produc = Product::where('id', $prod->product_id)->first();
                if (isset($produc)) {
                    if ($produc->category == $id || $produc->subcategory == $id) {
                        if ($produc->images) {
                            $img = explode(',', $produc->images);
                            $data['image'] = $img[0];
                        }
                        $data['id'] = $produc->id;
                        $data['name'] = $produc->name;
                        $data['regular_price'] = $produc->regular_price;
                        $veriant = Veriant::convertCurrency($produc->id)->get();
                        if (isset($veriant) && count($veriant) > 0) {
                            $data['vr'] = 1;
                            $data['veriant'] = $veriant;
                        } else {
                            $data['vr'] = 0;
                            $data['veriant'] = [];
                        }
                        array_push($datas, $data);
                        $data = null;
                    }
                }
                $produc = null;
            }
        }
        return response()->json([
            'data' => $datas
        ]);
    }

    public function getcustomer(Request $req)
    {
        $phone = $req->phone;
        $bid = $req->bid;
        $branch = Branch::find($bid);
        $user = User::where('phone', $phone)->where('store_id', $branch->store_id)->first();
        return response()->json([
            'data' => $user
        ]);
    }

    public function posorder(Request $request)
    {
        if (isset($request->holdorderid)) {
            $order = Order::where('id', $request->holdorderid)->delete();
            $oitm = Orderitem::where('order_id', $request->holdorderid)->delete();
        }
        $branch = Branch::find($request->bid);
        $store = Store::findOrFail($branch->store_id);
        if (!$store) {
            $store->currency = 1;
        }

        $user = User::where('phone', $request->customer['phone'])->where('store_id', $branch->store_id)->first();
        if (isset($user)) {
            $uid = $user->id;

            if (!$user->email) {
                $user->email = $request->customer['email'];
            }

            if (!$user->name) {
                $user->name = $request->customer['name'];
            }

            if (!$user->address) {
                $user->address = $request->customer['address'];
            }

            $user->save();

        } else {
            $user = new User();
            $user->phone = $request->customer['phone'];
            $user->email = $request->customer['email'];
            $user->name = $request->customer['name'];
            $user->currency_id = $store->currency;
            $user->address = $request->customer['address'];
            $user->password = Hash::make(substr(str_shuffle("0123456789"), 0, 8));
            $user->type = 'walking_customer';
            $user->store_id = $branch->store_id;
            $user->otp = randNumberGenerate();
            $user->save();
            $uid = $user->id;

            $notificationData = [
                "title" => "New walking customer register (" . getUserNameOrPhone($user) . ") - " . formatDateWithTime($user->created_at),
                "type" => "user_create",
                "user_type" => "admin",
                "store_id" => $user->store_id,
            ];

            if (isset($notificationData['title']) && !empty($notificationData['title'])) {
                createNotification($notificationData);
            }
        }
        $order = new Order();
        $order->uid = $uid;
        $order->subtotal = $request->subtotal;
        $order->tax = $request->tax ?? 0;
        $order->shipping = 0;
        $order->currency_id = $store->currency;
        $order->discount = $request->discount;
        $order->total = $request->total;
        $digit = substr(str_shuffle("0123456789"), 0, 4);
        $order->reference_no = "BN" . $digit;
        $order->name = $request->customer['name'];
        $order->phone = $request->customer['phone'];
        $order->email = $request->customer['email'];
        $order->address = $request->customer['address'];
        $order->note = $request->note;
        $order->status = "Delivered";
        $order->extradiscount = $request->extradiscount;
        $order->paid = $request->paid;

        $due = (float)$request->total - (float)$request->paid;
        $order->due = $due;
        // $order->status="On Hold";
        // $order->creator=Auth::user()->id;
        // $order->editor=Auth::user()->id;
        $order->branch_id = $request->bid;

        // $customer=Customer::where('id',$b->customer_id)->first();
        $order->customer_id = $branch->customer_id;
        $order->store_id = $branch->store_id;
        $order->type = "walking_customer";
        $store = Store::findOrFail($branch->store_id);
        $order->currency_id = $store->currency;
        $order->save();
        foreach ($request->items as $key => $item) {
            $orderItem = new Orderitem();
            $orderItem->product_id = $item['product_id'];
            $orderItem->order_id = $order->id;
            $orderItem->currency_id = $store->currency;
            $orderItem->price = $item['price'];
            $orderItem->quantity = $item['quantity'];
            $orderItem->color = $item['color'] ?? null;
            $orderItem->size = $item['size'] ?? null;
            $orderItem->volume = $item['volume'] ?? null;
            $orderItem->unit = $item['unit'] ?? null;
            // $orderItem->additional_price= $item->additional_price ?? null;
            $orderItem->save();
        }

        if ($request->paymentmethod == 'cod') {
            $transaction = new Transaction();
            $transaction->uid = $uid;
            $transaction->order_id = $order->id;
            $transaction->mode = $request->paymentmethod;
            $transaction->status = "pending";
            $transaction->save();
        } elseif ($request->paymentmethod == 'online') {
            $transaction = new Transaction();
            $transaction->uid = $uid;
            $transaction->order_id = $order->id;
            $transaction->mode = $request->paymentmethod;
            $transaction->transaction_id = $request->transactionid;
            $transaction->status = "pending";
            $transaction->save();
        }
        $invoice = new Invoice;
        $di = substr(str_shuffle("0123456789"), 0, 4);
        $invoice->reference_no = "INV" . $di;
        $invoice->order_id = $order->id;
        $invoice->type = "POS";
        $invoice->uid = $uid;
        $invoice->customer_id = $branch->customer_id;
        $invoice->store_id = $branch->store_id;
        $invoice->save();
        $data["order"] = $order;

        $headerSetting = Headersetting::where("store_id", $branch->store_id)->first();
        $data["logo"] = asset('/assets/images/setting') . "/" . $headerSetting->logo;
        $data["cashier"] = \Illuminate\Support\Facades\Auth::user()->name ?? "";
        $data["products"] = $request->items;
        return response()->json([
            'message' => 'success',
            'data' => $data,
        ]);
    }


    /***
     * Calculate product tax
     *
     * @param $price
     * @param $tax
     * @return float|int
     */
    public function calculateTaxForPos($price, $tax)
    {
        return $price * ($tax / 100);
    }

    public function posorderhold(Request $request)
    {
        if (isset($request->holdorderid)) {
            $order = Order::where('id', $request->holdorderid)->delete();
            $oitm = Orderitem::where('order_id', $request->holdorderid)->delete();
        }
        $branch = Branch::find($request->bid);
        $store = Store::findOrFail($branch->store_id);
        if (!$store) {
            $store->currency = 1;
        }
        if (isset($request->customer)) {
            $user = User::where('phone', $request->customer['phone'])->where('store_id',
                $branch->store_id)->first();
            if (isset($user)) {
                $uid = $user->id;
            } else {
                $user = new User();
                $user->phone = $request->customer['phone'];
                $user->email = $request->customer['email'];
                $user->currency_id = $store->currency;
                $user->name = $request->customer['name'];
                $user->address = $request->customer['address'];
                $user->password = Hash::make(substr(str_shuffle("0123456789"), 0, 8));
                $user->type = 'walking_customer';
                $user->store_id = $branch->store_id;
                $user->otp = "36743";
                $user->save();
                $uid = $user->id;

                $notificationData = [
                    "title" => "New walking customer register (" . getUserNameOrPhone($user) . ") - " . formatDateWithTime($user->created_at),
                    "type" => "user_create",
                    "user_type" => "admin",
                    "store_id" => $user->store_id,
                ];

                if (isset($notificationData['title']) && !empty($notificationData['title'])) {
                    createNotification($notificationData);
                }
            }
        }

        $order = new Order();
        $order->uid = $uid ?? null;
        $order->subtotal = $request->subtotal;
        $order->tax = 0;
        $order->shipping = 0;
        $order->currency_id = $store->currency;
        $order->discount = $request->discount;
        $order->total = $request->total;
        $digit = substr(str_shuffle("0123456789"), 0, 4);
        $order->reference_no = "BN" . $digit;
        $order->name = $request->customer['name'] ?? null;
        $order->phone = $request->customer['phone'] ?? null;
        $order->email = $request->customer['email'] ?? null;
        $order->address = $request->customer['address'] ?? null;
        $order->note = $request->note;
        // $order->status="Pending";
        $order->status = "On Hold";
        // $order->creator=Auth::user()->id;
        // $order->editor=Auth::user()->id;
        $order->branch_id = $request->bid;

        // $customer=Customer::where('id',$b->customer_id)->first();
        $order->customer_id = $branch->customer_id;
        $order->store_id = $branch->store_id;
        $order->type = "walking_customer";
        $order->session_id = $request->session;
        $store = Store::findOrFail($branch->store_id);
        $order->currency_id = $store->currency;
        $order->save();
        foreach ($request->items as $key => $item) {
            $orderItem = new Orderitem();
            $orderItem->product_id = $item['product_id'];
            $orderItem->order_id = $order->id;
            $orderItem->currency_id = $store->currency;
            $orderItem->price = $item['price'];
            $orderItem->quantity = $item['quantity'];
            $orderItem->color = $item['color'] ?? null;
            $orderItem->size = $item['size'] ?? null;
            $orderItem->volume = $item['volume'] ?? null;
            $orderItem->unit = $item['unit'] ?? null;
            // $orderItem->additional_price= $item->additional_price ?? null;
            $orderItem->save();
        }

        if ($request->payment_type == 'cod') {
            $transaction = new Transaction();
            $transaction->uid = $uid ?? null;
            $transaction->order_id = $order->id;
            $transaction->mode = $request->payment_type;
            $transaction->status = "pending";
            $transaction->save();

        } elseif ($request->payment_type == 'online') {
            $transaction = new Transaction();
            $transaction->uid = $uid ?? null;
            $transaction->order_id = $order->id;
            $transaction->mode = $request->payment_type;
            $transaction->status = "pending";
            $transaction->save();
        }
        $invoice = new Invoice;
        $di = substr(str_shuffle("0123456789"), 0, 4);
        $invoice->reference_no = "INV" . $di;
        $invoice->order_id = $order->id;
        $invoice->type = "POS";
        $invoice->uid = $uid ?? null;
        $invoice->customer_id = $branch->customer_id;
        $invoice->store_id = $branch->store_id;
        $invoice->save();
        return response()->json([
            'message' => 'success',
            'data' => $order
        ]);
    }

    public function getholdorders(Request $request)
    {
        $bid = decrypt($request->id);
        $branch = Branch::find($bid);
        $order = Order::where('branch_id', $bid)->where('status', 'On Hold')->get();
        return response()->json([
            'data' => $order
        ]);
    }

    public function holdorderproduct(Request $request)
    {
        $oitm = Orderitem::where('order_id', $request->id)->get();
        if (isset($oitm) && count($oitm) > 0) {
            foreach ($oitm as $key => $oit) {
                $dataa[$key]['id'] = $oit->id;
                $product = Product::find($oit->product_id);
                $dataa[$key]['product_name'] = $product->name;
                $dataa[$key]['quantity'] = $oit->quantity;
            }
        } else {
            $dataa = [];
        }
        return response()->json([
            'data' => $dataa
        ]);
    }

    public function deleteholdorder(Request $request)
    {
        $order = Order::where('id', $request->id)->delete();
        $oitm = Orderitem::where('order_id', $request->id)->delete();
        return response()->json([
            'data' => 1
        ]);
    }

    public function id()
    {
        $digit = substr(str_shuffle("0123456789"), 0, 4);
        $reference_no = "BN" . $digit;
        return $reference_no;
    }

    public function getorderid()
    {
        do {
            $digit = substr(str_shuffle("0123456789"), 0, 4);
            $id = "BN" . $digit;
            $order = Order::where('reference_no', $id)->first();
        } while ($order); // Keep generating a new ID until it is unique

        return response()->json([
            'data' => $id
        ]);
    }

    public function editholdorders(Request $request)
    {
        $order = Order::where('id', $request->id)->first();
        $oitm = Orderitem::where('order_id', $request->id)->get();
        // $user=User::find($order->uid);
        return response()->json([
            'session' => $order->session_id,
            'user' => null
        ]);
    }

    public function getsearchproduct(Request $request)
    {
        $bid = decrypt($request->id);
        $product = Branchproduct::where('branch_id', $bid)->get();
        $data = [];

        if (isset($product) && count($product) > 0) {
            $key = 0;
            foreach ($product as $pps) {
                if ($request->name == '') {
                    $pp = Product::where('id', $pps->product_id)->first();
                } else {
                    $pp = Product::where('id', $pps->product_id)->where('barcode', $request->name)->first();
                    if ($pp == null) {
                        $pp = Product::where('id', $pps->product_id)->Where('name', 'LIKE',
                            '%' . $request->name . '%')->first();
                    }
                }
                if (isset($pp)) {
                    if ($pp->images) {
                        $img = explode(',', $pp->images);
                        $data[$key]['image'] = $img[0];
                    }
                    $data[$key]['id'] = $pp->id;
                    $data[$key]['name'] = $pp->name;
                    $data[$key]['regular_price'] = $pp->regular_price;
                    $veriant = Veriant::convertCurrency($pp->id)->get();
//                    $veriant = Veriant::where('pid', $pp->id)->get();
                    if (isset($veriant) && count($veriant) > 0) {
                        $data[$key]['vr'] = 1;
                        $data[$key]['veriant'] = $veriant;
                    } else {
                        $data[$key]['vr'] = 0;
                        $data[$key]['veriant'] = [];
                    }
                    $key++;
                }

                $pp = null;
            }
        }

        return response()->json([
            'data' => $data
        ]);
    }

    public function getsearchproductbarcode(Request $request)
    {
        $bid = decrypt($request->id);
        $product = Branchproduct::where('branch_id', $bid)->get();
        if (isset($product) && count($product) > 0) {
            foreach ($product as $key => $pps) {
                $pp = Product::where('id', $pps->product_id)->where('barcode', $request->name)->first();
                if (isset($pp)) {
                    if ($pp->images) {
                        $img = explode(',', $pp->images);
                        $data[0]['image'] = $img[0];
                    }
                    $data[0]['id'] = $pp->id;
                    $data[0]['name'] = $pp->name;
                    $data[0]['regular_price'] = $pp->regular_price;
                    $veriant = Veriant::convertCurrency($pp->id)->get();
                    if (isset($veriant) && count($veriant) > 0) {
                        $data[0]['vr'] = 1;
                        $data[0]['veriant'] = $veriant;
                    } else {
                        $data[0]['vr'] = 0;
                        $data[0]['veriant'] = [];
                    }

                } else {
                    $data = null;
                }
            }
        }
        return response()->json([
            'data' => $data
        ]);
    }

}
