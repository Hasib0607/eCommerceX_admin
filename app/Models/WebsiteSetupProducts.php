<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WebsiteSetupProducts extends Model
{
    use HasFactory;


    public function image()
    {
        return $this->hasMany(WebsiteSetupImage::class, 'product_id', 'id');
    }


}
