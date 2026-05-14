<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Veriant extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function scopeConvertCurrency($query, $pid = null, $store_id = null)
    {
        if (is_null($store_id)) {
            $userData = getUserData();
            $storeID = $userData['store_id'] ?? "";
        }
        $store_id = $store_id ?? $storeID ?? "";
        $store = Store::with('current_currency')->find($store_id);
        if (isset($store)) {
            $current_currency = $store->current_currency;
            $query->select('veriants.*', 'c.symbol', 'c.code')
                ->join('products as p', 'p.id', '=', 'veriants.pid')
                ->join('currencies as c', 'p.currency_id', '=', 'c.id')
                ->when('p.currency_id' !== $store->currency && $current_currency->customize_rate_status === 0,
                    function ($query) use ($current_currency) {
                        $query->addSelect([
                            DB::raw("ROUND(veriants.additional_price / c.rate * " . $current_currency->rate . " , 2) as additional_price"),
                            DB::raw("'{$current_currency->symbol}' as symbol"),
                            DB::raw("'{$current_currency->code}' as code"),
                        ]);
                    })
                ->when('p.currency_id' !== $store->currency && $store->current_currency->customize_rate_status,
                    function ($query) use ($store, $current_currency) {
                        $query->addSelect([
                            DB::raw("ROUND(veriants.additional_price / {$store->currency_rate}, 2) as additional_price"),
                            DB::raw("'{$current_currency->symbol}' as symbol"),
                            DB::raw("'{$current_currency->code}' as code"),
                        ]);
                    });
        }

        if (!is_null($pid)) {
            $query->where('veriants.pid', $pid);
        }

        return $query;

    }


    // Define the product relationship
    public function product()
    {
        return $this->belongsTo(Product::class, 'pid');
    }

    public function getColor()
    {
        return $this->belongsTo(Color::class, 'color', 'code');
    }


}
