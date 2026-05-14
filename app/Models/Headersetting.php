<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Headersetting extends Model
{
    use HasFactory;

    // In your HeaderSetting model
    protected $casts = [
        'shipping_methods' => 'array',
    ];

    public function getShippingMethodsAttribute($value)
    {
        return $value ? json_decode($value, true) : [
            ["id" => 1, "area" => "Shipping Area 1", "cost" => 0]
        ];
    }

    public function scopeConvertCurrency($query, $store_id)
    {
        $store = Store::with('current_currency')->find($store_id);
        $current_currency = $store->current_currency;
        return $query->select("headersettings.*", 'currencies.symbol', 'currencies.code')
            ->join('currencies', 'headersettings.currency_id', '=', 'currencies.id')
            ->when('headersettings.currency_id' !== $store->currency && $current_currency->customize_rate_status === 0,
                function ($query) use ($current_currency) {
                    $query->addSelect([
                        DB::raw("ROUND(headersettings.shipping_area_1_cost / currencies.rate * " . $current_currency->rate . " , 2) as shipping_area_1_cost"),
                        DB::raw("ROUND(headersettings.shipping_area_2_cost / currencies.rate * " . $current_currency->rate . " , 2) as shipping_area_2_cost"),
                        DB::raw("ROUND(headersettings.shipping_area_3_cost / currencies.rate * " . $current_currency->rate . " , 2) as shipping_area_3_cost"),
                        DB::raw("ROUND(headersettings.prepayment / currencies.rate * " . $current_currency->rate . " , 2) as prepayment"),
                        DB::raw("'{$current_currency->symbol}' as symbol"),
                        DB::raw("'{$current_currency->code}' as code")
                    ]);
                })
            ->when('headersettings.currency_id' !== $store->currency && $store->current_currency->customize_rate_status,
                function ($query) use ($store, $current_currency) {
                    $query->addSelect([
                        DB::raw("ROUND(headersettings.shipping_area_1_cost / {$store->currency_rate}, 2) as shipping_area_1_cost"),
                        DB::raw("ROUND(headersettings.shipping_area_2_cost / {$store->currency_rate}, 2) as shipping_area_2_cost"),
                        DB::raw("ROUND(headersettings.shipping_area_3_cost / {$store->currency_rate}, 2) as shipping_area_3_cost"),
                        DB::raw("ROUND(headersettings.prepayment / {$store->currency_rate}, 2) as prepayment"),
                        DB::raw("'{$current_currency->symbol}' as symbol"),
                        DB::raw("'{$current_currency->code}' as code")
                    ]);
                })
            ->where('store_id', $store_id);
    }

    public function store()
    {
        return $this->belongsTo(Store::class, 'store_id');
    }


}
