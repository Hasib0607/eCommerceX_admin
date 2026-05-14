<?php

namespace App\Http\Controllers\Api\v2;

use App\Http\Controllers\Controller;
use App\Models\AdminNotification;
use App\Models\NewsLetter;
use Illuminate\Http\Request;

class NewsLetterController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $newsLetter = NewsLetter::orderBy('id', 'DESC')->get();
        return $newsLetter;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function getNotiFica($id)
    {
        $noti = AdminNotification::where('store_id', $id)->orderBy('id', 'DESC')->get();
        return response()->json($noti);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
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
        $newsLetter = NewsLetter::where('store_id', $request->store_id)->where('email', $request->email)->first();

        if ($newsLetter) {
            return response()->json(['error' => 'Your email has already been taken, bro']);
        } else {
            $newsLetter = new NewsLetter();
            $newsLetter->store_id = $request->store_id;
            $newsLetter->email = $request->email;
            $newsLetter->save();
        }
        return $newsLetter;
    }

    /**
     * Display the specified resource.
     *
     * @param \App\Models\NewsLetter $newsLetter
     * @return \Illuminate\Http\Response
     */
    public function show(NewsLetter $newsLetter)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param \App\Models\NewsLetter $newsLetter
     * @return \Illuminate\Http\Response
     */
    public function edit(NewsLetter $newsLetter)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param \App\Models\NewsLetter $newsLetter
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, NewsLetter $newsLetter)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param \App\Models\NewsLetter $newsLetter
     * @return \Illuminate\Http\Response
     */
    public function destroy(NewsLetter $newsLetter)
    {
        //
    }
}
