<?php

namespace App\Http\Controllers\Api\v2;

use App\Http\Controllers\Controller;
use App\Models\District;
use Illuminate\Http\Request;

class DistrictController extends Controller
{

    /**
     *
     * Get all district
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAllDistrict()
    {
        try {
            $districts = District::all();

            return response()->json(['status' => true, 'data' => $districts]);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => "Something went wrong"]);
        }
    }

    /**
     * Get districts by id
     *
     * @param $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function getDistrictById($id)
    {
        try {
            if (!isset($id) || empty($id)) {
                return response()->json(['status' => false, 'message' => "District id is required"]);
            }

            $district = District::where('id', $id)->first();

            return response()->json(['status' => true, 'data' => $district]);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => "Something went wrong"]);
        }
    }


}
