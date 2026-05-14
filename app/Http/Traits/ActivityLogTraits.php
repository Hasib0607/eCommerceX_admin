<?php

    namespace App\Http\Traits;

    use Illuminate\Http\Request;
    use App\Models\Activitylog;
    use App\Models\Customer;
    use App\Models\Staff;
    use Illuminate\Support\Str;
    use Auth;

    trait ActivityLogTraits
    {
        public function saveactivity($activity)
        {
            /*extract user_id, user_type, store_id, customer_id*/
            extract(getUserData());

            $act = new Activitylog();
            $act->uid = $user_id;
            $act->ip = $_SERVER['REMOTE_ADDR'];
            //   $act->mac=exec('getmac');
            $act->activity = "user " . $user_id . " " . $activity;
            $act->is_superadmin = false;
            $act->store_id = $store_id ?? '';
            $act->save();
            return $act;

        }
    }
