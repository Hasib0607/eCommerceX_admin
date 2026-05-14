<?php

namespace App\Http\Controllers\Api\v2;

use App\Http\Controllers\Controller;
use App\Models\Country;
use Illuminate\Http\Request;

class CountryController extends Controller
{

    /**
     *
     * Get all district
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAllCountry()
    {
        try {
            $countries = Country::all();

            return sendResponse("Success", $countries);
        } catch (\Exception $e) {
            return sendError("Something went wrong");
        }
    }


}
