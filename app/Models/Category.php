<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use HasFactory;

    protected $table = 'categories';

    protected $fillable = [
        'name',
        'parent',
        'market_id',
        'banner',
        'icon',
        'status',
        'position',
        'uid',
        'customer_id',
        'store_id',
        'creator',
        'editor'
    ];

    public function products()
    {
        return $this->hasMany(Product::class, 'pse_cat_id', 'id')
            ->where('status', 'active')
            ->where('pse_status', 'Accepted');
    }

    public function subcategories()
    {
        return $this->hasMany(self::class, 'parent'); // 'parent' is the column linking parent and child categories
    }


}
