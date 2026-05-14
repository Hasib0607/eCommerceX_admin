<?php

namespace App\Http\Controllers\Api\v2\EbitansAnalytics;

use App\Http\Controllers\Controller;
use App\Models\EbitansAnalytics\EbtAnalytics;
use Illuminate\Http\Request;

class EbtAnalyticsController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $info = EbtAnalytics::get();
        return response()->json($info);
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
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        if (empty($request->ip) || empty($request->device) || empty($request->url)) {
            return response()->json(["status" => false, "message" => "Data missing please provide data!"]);
        }
        $check = EbtAnalytics::where('ip', $request->ip)
            ->where('device', $request->device)
            ->where('os', $request->os)
            ->where('url', $request->url)
            ->where('browser', $request->browser)
            ->where('country_code', $request->country_code)
            ->where('state', $request->state)
            ->where('created_at', '>=', date('Y-m-d') . ' 00:00:00')
            ->first();

        if (is_null($check)) {
            $info = new EbtAnalytics();

            $info->store_id = $request->store_id;
            $info->user_id = $request->user_id;
            $info->device = $request->device;
            $info->ip = $request->ip;
            $info->mac = $request->mac;
            $info->os = $request->os;
            $info->browser = $request->browser;
            $info->url = $request->url;
            $info->city = $request->city;
            $info->page_title = $request->page_title;
            $info->category_id = $request->category_id;
            $info->product_id = $request->product_id;
            $info->country_code = $request->country_code;
            $info->country_name = $request->country_name;
            $info->latitude = $request->latitude;
            $info->longitude = $request->longitude;
            $info->postal = $request->postal;
            $info->state = $request->state;
            $info->location = $request->location;
            $info->isTime = $request->isTime;
            $info->save();
            return response()->json(["status" => true, "message" => "success", "data" => $info]);
        }

        return response()->json(["status" => true, "message" => "success", "data" => $check]);
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
}
