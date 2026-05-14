<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SaasFeature extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'name',
        'type',
        'enabled_by_default',
        'default_limit',
    ];
}

