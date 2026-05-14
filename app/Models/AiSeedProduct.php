<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AiSeedProduct extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'is_demo' => 'boolean',
    ];
}
