<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScrapedProduct extends Model
{
    protected $fillable = [
        'title',
        'description',
        'keywords',
        'url',
        'image',
        'original_price',
        'price',
        'currency',
        'in_stock',
        'product_id',
        'sku_id',
        'source_site',
        'source_url',
        'brand_name',
        'seller_name',
        'location',
    ];
}
