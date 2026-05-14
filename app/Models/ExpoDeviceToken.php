<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExpoDeviceToken extends Model
{
    use HasFactory;
    
    protected $fillable = ['store_id', 'expo_token'];
}
