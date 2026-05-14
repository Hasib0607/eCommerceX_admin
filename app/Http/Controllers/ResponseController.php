<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Page;
use App\Models\User;
use App\Models\Address;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Validator;
use Session;


class ResponseController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function create()
    {
        $urls = "customer";
        return view('admin.customer.create')->with('urls', $urls);
    }

    public function store(Request $request)
    {
        $rules = array(
            'name' => 'required',
        );
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            Session::flash('message', 'Name and Title Required');
            return redirect()->back()
                ->withErrors($validator);
        } else {
            $customer = new User();
            $customer->name = $request->name;
            $customer->email = $request->email;
            $customer->phone = $request->phone;
            $customer->password = Hash::make($request->password);
            $customer->type = 'customer';
            $customer->save();
            $address = new Address;
            $address->uid = $customer->id;
            $address->country = $request->country;
            $address->state = $request->state;
            $address->proper = $request->proper;
            $address->save();
            Session::flash('message', 'Successfully created!');
            return redirect()->route('admin.customer');
        }
    }

    public function edit($id)
    {
        $urls = "customer";
        $singleData = User::find($id);
        $singleDatass = Address::where('uid', $singleData->id)->first();
        return view('admin.customer.edit')
            ->with('singleData', $singleData)
            ->with('singleDatass', $singleDatass)->with('urls', $urls);
    }

    public function update(Request $request, $id)
    {
        $rules = array(
            'name' => 'required',
        );
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            Session::flash('message', 'Name and Title Required');
            return redirect()->back()
                ->withErrors($validator);
        } else {
            $customer = User::find($id);
            $customer->name = $request->name;
            $customer->email = $request->email;
            $customer->phone = $request->phone;
            $customer->password = Hash::make($request->password);
            $customer->type = 'customer';
            $customer->save();
            $address = Address::where('uid', $id)->first();
            $address->uid = $customer->id;
            $address->country = $request->country;
            $address->state = $request->state;
            $address->proper = $request->proper;
            $address->save();
            Session::flash('message', 'Successfully Updated!');
            return redirect()->route('admin.customer');
        }
    }

    public function destroy($id)
    {
        $user = User::find($id);
        $user->delete();
        $address = Address::where('uid', $user->id)->first();
        $address->delete();
        Session::flash('success_message', 'Successfully Deleted!');
        return redirect('customer');
    }

    public function getsearch()
    {
        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => "https://seo-keyword-research.p.rapidapi.com/keyword?keyword=email%20marketing&country=us",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "GET",
            CURLOPT_HTTPHEADER => [
                "X-RapidAPI-Host: seo-keyword-research.p.rapidapi.com",
                "X-RapidAPI-Key: SIGN-UP-FOR-KEY"
            ],
        ]);

        $response = curl_exec($curl);
        $err = curl_error($curl);

        curl_close($curl);

        if ($err) {
            return "cURL Error #:" . $err;
        } else {
            return $response;
        }
    }
}
