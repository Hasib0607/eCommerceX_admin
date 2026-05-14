<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cart extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id',
        'user_id',
        'ip_address',
        'session_id',
        'product_id',
        'qty',
        'price',
        'variant_id',
        'phone',
        'email'
    ];

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function variant()
    {
        return $this->belongsTo(Veriant::class, 'variant_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

}
