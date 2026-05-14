<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BookingCustomerFiled extends Model
{
    use HasFactory;
    
    protected $table = 'booking_customer_fields';

    protected $fillable = [
        'modulus_id',
        'uId',
        'name',
        'tagId',
        'is_checked',
        'is_required',
        'store_id',
        'customer_id',
        'is_single',
    ];
}