<?php

use App\Models\AdminUserAnalytics;
use App\Models\Customer;
use App\Models\Store;
use Illuminate\Support\Facades\Auth;
use Stevebauman\Location\Facades\Location;
use Jenssegers\Agent\Agent;

function _getUserUsingInfo($user)
{

    $twoDayStart = date('Y-m-d');
    $user_id = $user->id ?? 0;
    $user_type = $user->type ?? 0;

    if ($user_type == "superadmin" || $user_type == "superstaff" || $user_type == "staff") {
        return 0;
    }
    $customer = Customer::where('uid', $user_id)->first();
    $store = Store::where('id', $customer->active_store ?? 0)->first();


    $ip = \Request::ip();
    $info = Location::get($ip);


    $macAddress = '';
    // Execute platform-specific command to get network interface information
    if (stristr(PHP_OS, 'win')) {
        // Windows
        $ipConfigCommand = 'ipconfig /all';
    } elseif (stristr(PHP_OS, 'darwin') || stristr(PHP_OS, 'mac')) {
        // macOS
        $ipConfigCommand = 'ifconfig';
    } else {
        // Linux/Unix
        $ipConfigCommand = 'ifconfig';
    }

    if (function_exists('shell_exec')) {
        $ifconfigResult = shell_exec($ipConfigCommand);
        // Search for the MAC address pattern using regular expressions
        $pattern = '/([0-9A-Fa-f]{2}[:-]){5}([0-9A-Fa-f]{2})/';
        preg_match_all($pattern, $ifconfigResult, $matches);
        // Extract the first MAC address found
        if (!empty($matches[0][0])) {
            $macAddress = $matches[0][0];
        }
    }


    $agent = new Agent();
    $device = $agent->device();             // Get the device type (e.g., iPhone, Samsung, etc.)
    $platform = $agent->platform();         // Get the operating system (e.g., Windows, macOS, Android)
    $browser = $agent->browser();           // Get the browser name (e.g., Chrome, Firefox, Safari)
    $browserVersion = $agent->version($browser);  // Get the browser version

    $storeID = null;

    if (isset($store->id)) {
        $storeID = $store->id;
    }

    $numberOfVisits = AdminUserAnalytics::where('store_id', $storeID)->where('user_type', $user_type)->whereDate('created_at', $twoDayStart)->where('url', url()->current())->first();

    if (isset($numberOfVisits)) {
        $numberOfVisits->number_of_visits = $numberOfVisits->number_of_visits + 1;
        $numberOfVisits->user_id = $user_id ?? 0;
        $numberOfVisits->user_type = $user_type ?? 0;
        $numberOfVisits->store_id = $store->id ?? 0;
        $numberOfVisits->ip = $ip ?? '';
        $numberOfVisits->mac = $macAddress ?? '';
        $numberOfVisits->url = url()->current() ?? '';
        $numberOfVisits->countryName = $info->countryName ?? '';
        $numberOfVisits->countryCode = $info->countryCode ?? '';
        $numberOfVisits->regionCode = $info->regionCode ?? '';
        $numberOfVisits->regionName = $info->regionName ?? '';
        $numberOfVisits->cityName = $info->cityName ?? '';
        $numberOfVisits->zipCode = $info->zipCode ?? '';
        $numberOfVisits->isoCode = $info->isoCode ?? '';
        $numberOfVisits->postalCode = $info->postalCode ?? '';
        $numberOfVisits->latitude = $info->latitude ?? '';
        $numberOfVisits->longitude = $info->longitude ?? '';
        $numberOfVisits->metroCode = $info->metroCode ?? '';
        $numberOfVisits->areaCode = $info->areaCode ?? '';
        $numberOfVisits->timezone = $info->timezone ?? '';
        $numberOfVisits->device = $device ?? '';
        $numberOfVisits->platform = $platform ?? '';
        $numberOfVisits->browser = $browser ?? '';
        $numberOfVisits->browser_version = $browserVersion ?? '';
        $numberOfVisits->location = $location ?? '';
        $numberOfVisits->update();
        return 0;
    } else {
        $adminAnalytic = new AdminUserAnalytics();
    }


    $adminAnalytic->user_id = $user_id ?? 0;
    $adminAnalytic->user_type = $user_type ?? 0;
    $adminAnalytic->store_id = $store->id ?? 0;
    $adminAnalytic->number_of_visits = $number_of_visits ?? 1;
    $adminAnalytic->ip = $ip ?? '';
    $adminAnalytic->mac = $macAddress ?? '';
    $adminAnalytic->url = url()->current() ?? '';
    $adminAnalytic->countryName = $info->countryName ?? '';
    $adminAnalytic->countryCode = $info->countryCode ?? '';
    $adminAnalytic->regionCode = $info->regionCode ?? '';
    $adminAnalytic->regionName = $info->regionName ?? '';
    $adminAnalytic->cityName = $info->cityName ?? '';
    $adminAnalytic->zipCode = $info->zipCode ?? '';
    $adminAnalytic->isoCode = $info->isoCode ?? '';
    $adminAnalytic->postalCode = $info->postalCode ?? '';
    $adminAnalytic->latitude = $info->latitude ?? '';
    $adminAnalytic->longitude = $info->longitude ?? '';
    $adminAnalytic->metroCode = $info->metroCode ?? '';
    $adminAnalytic->areaCode = $info->areaCode ?? '';
    $adminAnalytic->timezone = $info->timezone ?? '';
    $adminAnalytic->device = $device ?? '';
    $adminAnalytic->platform = $platform ?? '';
    $adminAnalytic->browser = $browser ?? '';
    $adminAnalytic->browser_version = $browserVersion ?? '';
    $adminAnalytic->location = $location ?? '';
    $adminAnalytic->save();
}
