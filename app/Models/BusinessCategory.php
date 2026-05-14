<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BusinessCategory extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'slug', 'parent_id', 'position'];

    public function subcategories()
    {
        return $this->hasMany(BusinessCategory::class, 'parent_id');
    }

}
