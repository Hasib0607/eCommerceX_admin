<?php

use App\Models\BuyModulus;
use App\Models\MarchantPaymentGetway;
use App\Models\Modulus;

function ModulusStatus($store_id, $modulus_id)
{
    $buyModulus = BuyModulus::where('store_id', $store_id)->where('modulus_id', $modulus_id)->first();
    $modulus = Modulus::find($modulus_id);

    if (isset($modulus->status) && isset($buyModulus->status) && $modulus->status == 1 && $buyModulus->status == 1) {
        return true;
    }
    return false;
}

function smsCount($store_id, $modulus_id)
{
    $buyModulus = BuyModulus::where('store_id', $store_id)->where('modulus_id', $modulus_id)->first();
    $buyModulus->sms_count += 1;
    $buyModulus->update();
    return $buyModulus->status ?? 0;
}

function merchantPaymentStatus($store_id, $module_id, $key, $status)
{
    if (ModulusStatus($store_id, $module_id) && $status == "active") {
        $marchenPayment = MarchantPaymentGetway::where("store_id", $store_id)->where("payment_gatway", $key)->first();
        return isset($marchenPayment->status) && $marchenPayment->status == 1 ? 'active' : 'deactive';
    } else {
        return 'deactive';
    }
}

function merchantPaymentModulusStatus($store_id, $module_id, $key)
{
    if (ModulusStatus($store_id, $module_id)) {
        $marchenPayment = MarchantPaymentGetway::where("store_id", $store_id)->where("payment_gatway", $key)->first();
        return isset($marchenPayment->status) && $marchenPayment->status == 1 ? 1 : 0;
    } else {
        return 0;
    }
}

