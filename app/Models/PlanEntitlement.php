<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlanEntitlement extends Model
{
    use HasFactory;

    protected $fillable = [
        'plan_id',
        'feature_key',
        'is_enabled',
        'limit_value',
    ];
}

