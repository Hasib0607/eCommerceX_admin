<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductAffiliateCommission extends Model
{
    use HasFactory;
    public function order()
    {
        return $this->hasOne(Order::class, 'id', 'order_id');
    }
    public function product()
    {
        return $this->hasOne(Product::class, 'id', 'product_id');
    }
}
