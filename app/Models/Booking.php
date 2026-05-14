<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    use HasFactory;

    protected $table = 'booking';

    protected $fillable = [
        "user_id",
        "store_id",
        "order_id",
        'name',
        'phone',
        'email',
        'date',
        'start_date',
        'end_date',
        'pickup_location',
        'drop_location',
        'time',
        'comment',
    ];
}
