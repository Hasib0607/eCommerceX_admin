<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Coupon extends Model
{
    use HasFactory;

    public function scopeConvertCurrency($query, $store_id)
    {
        $store = Store::with('current_currency')->find($store_id);
        $current_currency = $store->current_currency;
        return $query->select("coupons.*", 'currencies.symbol', 'currencies.id as currency')
            ->join('currencies', 'coupons.currency_id', '=', 'currencies.id')
            ->when('coupons.currency_id' !== $store->currency && $current_currency->customize_rate_status === 0,
                function ($query) use ($current_currency) {
                    $query->addSelect([
                        DB::raw("ROUND(coupons.min_purchase / currencies.rate * " . $current_currency->rate . " , 2) as min_purchase"),
                        DB::raw("ROUND(coupons.max_purchase / currencies.rate * " . $current_currency->rate . " , 2) as max_purchase"),
                        DB::raw("CASE WHEN coupons.discount_type = 'fixed' THEN ROUND(coupons.discount_amount / currencies.rate * " . $current_currency->rate . " , 2) ELSE coupons.discount_amount END as discount_amount"),
                        DB::raw("CASE WHEN coupons.discount_type = 'fixed' THEN '{$current_currency->symbol}' ELSE '%' END as symbol")
                    ]);
                })
            ->when('coupons.currency_id' !== $store->currency && $store->current_currency->customize_rate_status,
                function ($query) use ($store, $current_currency) {
                    $query->addSelect([
                        DB::raw("ROUND(coupons.min_purchase / {$store->currency_rate}, 2) as min_purchase"),
                        DB::raw("ROUND(coupons.max_purchase / {$store->currency_rate}, 2) as max_purchase"),
                        DB::raw("CASE WHEN coupons.discount_type = 'fixed' THEN ROUND(coupons.discount_amount / {$store->currency_rate}, 2) ELSE coupons.discount_amount END as discount_amount"),
                        DB::raw("CASE WHEN coupons.discount_type = 'fixed' THEN '{$current_currency->symbol}' ELSE '%' END as symbol")
                    ]);
                })
            ->where('coupons.store_id', $store_id);
    }
}
