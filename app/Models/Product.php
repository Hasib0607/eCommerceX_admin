<?php

namespace App\Models;

use Haruncpi\LaravelIdGenerator\IdGenerator;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Product extends Model
{
    use HasFactory;

    protected $table = 'products';

    protected $guarded = [];

    public function scopeConvertCurrency($query, $store_id)
    {
        $store = Store::with('current_currency')->find($store_id);
        $current_currency = $store->current_currency;
        if (!$current_currency) {
            $current_currency = Currency::where("id", 1)->first();
        }
        return $query->select("products.*", 'currencies.symbol', 'currencies.code')
            ->join('currencies', 'products.currency_id', '=', 'currencies.id')
            ->when('products.currency_id' !== $store->currency && $current_currency->customize_rate_status === 0,
                function ($query) use ($current_currency) {
                    $query->addSelect([
                        DB::raw("ROUND(products.regular_price / currencies.rate * " . $current_currency->rate . " , 2) as regular_price"),
                        DB::raw("CASE WHEN products.discount_type = 'fixed' THEN ROUND(products.promotional_price / currencies.rate * " . $current_currency->rate . " , 2) ELSE products.promotional_price END as promotional_price"),
                        DB::raw("CASE WHEN products.tax_type = 'fixed' THEN ROUND(products.tax_rate / currencies.rate * {$current_currency->rate}, 2) ELSE products.tax_type END as tax_rate"),
                        DB::raw("'{$current_currency->symbol}' as symbol")
                    ]);
                })
            ->when('products.currency_id' !== $store->currency && $store->current_currency->customize_rate_status,
                function ($query) use ($store, $current_currency) {
                    $query->addSelect([
                        DB::raw("ROUND(products.regular_price / {$store->currency_rate}, 2) as regular_price"),
                        DB::raw("CASE WHEN products.discount_type = 'fixed' THEN ROUND(products.promotional_price / {$store->currency_rate}, 2) ELSE products.promotional_price END as promotional_price"),
                        DB::raw("CASE WHEN products.tax_type = 'fixed' THEN ROUND(products.tax_rate / {$store->currency_rate}, 2) ELSE products.tax_type END as tax_rate"),
                        DB::raw("'{$current_currency->symbol}' as symbol")
                    ]);
                })
            ->where('products.store_id', $store_id);
    }

    public function storeInfo()
    {
        return $this->hasOne(Store::class, 'id', 'store_id');
    }

    public function totalSell($id)
    {
        return Orderitem::where('product_id', $id)->sum('quantity');
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }


    public function getGetCategoriesAttribute()
    {
        // Split the comma-separated category IDs into an array
        $categoryIds = explode(',', $this->category);

        // Query the categories table to get the relevant categories
        return DB::table('categories')
            ->whereIn('id', $categoryIds)
            ->where('status', 'active')
            ->select('id', 'name', 'status')
            ->get();
    }


    public function getGetSubcategoriesAttribute()
    {
        // Split the comma-separated category IDs into an array
        $categoryIds = explode(',', $this->subcategory);

        // Query the categories table to get the relevant categories
        return DB::table('categories')
            ->whereIn('id', $categoryIds)
            ->where('status', 'active')
            ->select('id', 'name', 'status')
            ->get();
    }

    public function getCategory()
    {
        return $this->belongsTo(Category::class, 'category', 'id');
    }

    public function getSubcategory()
    {
        return $this->belongsTo(Category::class, 'subcategory', 'id');
    }

    public function getBrand()
    {
        return $this->belongsTo(Brand::class, 'brand', 'id');
    }

    public function getSupplier()
    {
        return $this->belongsTo(Supplier::class, 'supplier', 'id');
    }

    public function currency()
    {
        return $this->belongsTo(Currency::class, 'currency_id');
    }

    public function layout()
    {
        return $this->hasMany(ProductLayout::class, 'product_id', 'id')->with("design");
    }

    public function variant()
    {
        return $this->hasMany(Veriant::class, 'pid', 'id');
    }

    public function getVariantsWithConversion($storeId)
    {
        return $this->variant()
            ->with(['getColor' => function ($query) use ($storeId) {
                $query->where('store_id', $storeId);
            }])
            ->convertCurrency($this->id, $storeId); // Assuming this is a query builder
    }



    // Define the reviews relationship
    public function reviews()
    {
        return $this->hasMany(Review::class, 'product_id');
    }

}
