<?php


/* Get country name by code */
if (!function_exists('getCountryName')) {
    function getCountryName($country_code = "BD")
    {
        return \App\Models\Country::where("countryCode", $country_code)->value('countryName') ?? 'the country';
    }
}
