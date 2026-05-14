<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderStatus extends Model
{
    use HasFactory;

    protected $guarded = [];

    public static function getOrderStatus($status = 1)
    {
        return OrderStatus::where('status', $status)->get();
    }

}
