<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdPse extends Model
{
    use HasFactory;
    protected $casts = [
        'category_id' => 'array',
    ];
    protected $table = 'ads_pse';
    protected $fillable = [
        'name',
        'link',
        'category_id',
        'banner',
        'status',
        'position',
        'image_type'
    ];

    public function categories()
    {
        // Assuming category_id is an array of category IDs
        if (is_array($this->category_id)) {
            return Category::whereIn('id', $this->category_id)->get();
        } else {
            // Handle the case when $this->category_id is not an array
            return [];
        }
    }
}
