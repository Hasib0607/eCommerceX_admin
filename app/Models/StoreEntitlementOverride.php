<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StoreEntitlementOverride extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id',
        'feature_key',
        'is_enabled',
        'limit_value',
    ];
}

