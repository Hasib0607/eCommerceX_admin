<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AiSeedImageLibrary extends Model
{
    use HasFactory;

    protected $table = 'ai_seed_image_libraries';

    protected $guarded = [];

    protected $casts = [
        'status' => 'boolean',
        'width' => 'integer',
        'height' => 'integer',
        'business_category_id' => 'integer',
        'business_category_ids' => 'array',
    ];
}
