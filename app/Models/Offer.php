<?php

    namespace App\Models;

    use Illuminate\Database\Eloquent\Factories\HasFactory;
    use Illuminate\Database\Eloquent\Model;
    use Illuminate\Support\Facades\DB;

    class Offer extends Model
    {
        use HasFactory;

        public function scopeConvertCurrency($query, $store_id)
        {
            $store = Store::with('current_currency')->find($store_id);
            $current_currency = $store->current_currency;
            return $query->select("offers.*", 'currencies.symbol', 'currencies.id as currency')
                ->join('currencies', 'offers.currency_id', '=', 'currencies.id')
                ->when('offers.currency_id' !== $store->currency && $current_currency->customize_rate_status === 0,
                    function ($query) use ($current_currency) {
                        $query->addSelect([
                            DB::raw("CASE WHEN offers.discount_type = 'fixed' THEN ROUND(offers.discount_amount / currencies.rate * " . $current_currency->rate . " , 2) ELSE offers.discount_amount END as discount_amount"),
                            DB::raw("CASE WHEN offers.discount_type = 'fixed' THEN currencies.symbol ELSE '%' END as symbol")
                        ]);
                    })
                ->when('offers.currency_id' !== $store->currency && $store->current_currency->customize_rate_status,
                    function ($query) use ($store, $current_currency) {
                        $query->addSelect([
                            DB::raw("CASE WHEN offers.discount_type = 'fixed' THEN ROUND(offers.discount_amount / {$store->currency_rate}, 2) ELSE offers.discount_amount END as discount_amount"),
                            DB::raw("CASE WHEN offers.discount_type = 'fixed' THEN currencies.symbol ELSE '%' END as symbol")
                        ]);
                    })
                ->where('offers.store_id', $store_id);
        }
    }
