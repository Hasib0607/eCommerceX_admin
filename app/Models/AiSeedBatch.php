<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AiSeedBatch extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'blueprint' => 'array',
        'image_width' => 'integer',
        'image_height' => 'integer',
    ];
}
